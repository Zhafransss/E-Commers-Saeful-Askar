<?php

use App\Exceptions\IntegrationException;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use App\Services\Jubelio\ContactService;
use App\Services\Jubelio\SalesOrderService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
| INT-JB-09 – INT-JB-13 (docs/api/integrations.md): contact, unpaid sales order, paid, cancel, and the
| duplicate guard on `ref_no`. Jubelio is faked; its login and the responses come from this file.
*/

beforeEach(function () {
    config([
        'jubelio.base_url' => 'https://api2.jubelio.com', 'jubelio.email' => 'owner@saefulaskar.id', 'jubelio.password' => 'x',
        'jubelio.store_id' => '-102', 'jubelio.location_id' => -1, 'jubelio.tax_id' => 1,
    ]);
    $this->routes = [];
    Http::fake(function (Request $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        $key = $request->method().' '.$path;
        $default = [
            'POST /login' => Http::response(fixture('jubelio/login.json')),
            'GET /contacts/customers/' => Http::response(['data' => [], 'totalCount' => 0]),
            'POST /contacts/' => Http::response(['status' => 'ok', 'contact_id' => 5512]),
            'GET /sales/v2/orders/' => Http::response(['data' => [], 'totalCount' => 0]),
            'POST /sales/orders/' => Http::response(['id' => 98112]),
            'GET /sales/orders/98112' => Http::response(['salesorder_id' => 98112, 'salesorder_no' => 'SO-000981', 'ref_no' => 'WEB-000123']),
            'POST /sales/orders/set-as-paid' => Http::response(['status' => 'ok']),
            'POST /sales/orders/cancel/' => Http::response(['status' => 'ok']),
        ];
        $response = $this->routes[$key] ?? $default[$key] ?? Http::response(['message' => 'Not Found'], 404);

        return $response instanceof Closure ? $response($request) : $response;
    });

    $this->user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com', 'phone' => '081234567890']);
    $this->address = Address::factory()->for($this->user)->default()->create(['address_line' => 'Jl. Melati No. 12, RT 03/RW 05']);
});

function jubelioCallsTo(string $method, string $path): array
{
    return Http::recorded()
        ->filter(fn (array $pair) => $pair[0]->method() === $method && parse_url($pair[0]->url(), PHP_URL_PATH) === $path)
        ->map(fn (array $pair) => $pair[0])->values()->all();
}

function orderWithItems(User $user, array $attributes = []): Order
{
    $order = Order::factory()->for($user)->create($attributes + ['order_number' => 'WEB-000123', 'note' => 'Tolong dibungkus kardus tebal']);
    $order->items()->create([
        'jubelio_item_id' => 2976, 'sku' => 'UWF-HTM', 'product_name' => 'Sepeda Listrik Uwinfly C1 Pro', 'variant_name' => 'Hitam',
        'unit' => 'Unit', 'price' => 6750000, 'qty' => 1, 'weight_gram' => 32000, 'subtotal' => 6750000,
    ]);

    return $order;
}

// --- Contact (INT-JB-09) -------------------------------------------------------------

test('a customer without a contact gets one made from the address, and the id is stored once', function () {
    $id = app(ContactService::class)->ensure($this->user, $this->address);

    expect($id)->toBe(5512)->and($this->user->fresh()->jubelio_contact_id)->toBe(5512);

    $body = jubelioCallsTo('POST', '/contacts/')[0]->data();
    expect($body)->toMatchArray([
        'contact_id' => 0, 'contact_name' => 'Budi Santoso', 'contact_type' => 0, 'email' => 'budi@example.com',
        'phone' => '081234567890', 'payment_term' => -1, 'source_detail' => 'Website Saeful Askar',
        's_address' => 'Jl. Melati No. 12, RT 03/RW 05', 's_province' => 'JAWA BARAT', 's_city' => 'KAB. BEKASI',
        's_district_id' => '321605', 's_post_code' => '17565',
    ])
        // Province, city, and subdistrict ids are numbers; the district id is text (Jubelio's spec).
        ->and($body['s_province_id'])->toBe('32')->and($body['s_city_id'])->toBe('3216')->and($body['s_subdistrict_id'])->toBe('3216052001');
});

test('the stored contact id is reused without calling Jubelio', function () {
    $this->user->forceFill(['jubelio_contact_id' => 777])->save();

    expect(app(ContactService::class)->ensure($this->user, $this->address))->toBe(777);
    Http::assertNothingSent();
});

test('a contact Jubelio already has for the same email is reused, not duplicated', function () {
    $this->routes['GET /contacts/customers/'] = Http::response(['data' => [
        ['contact_id' => 100, 'email' => 'budi2@example.com'],
        ['contact_id' => 4321, 'email' => 'BUDI@example.com'],
    ]]);

    expect(app(ContactService::class)->ensure($this->user, $this->address))->toBe(4321)
        ->and(jubelioCallsTo('POST', '/contacts/'))->toBeEmpty()
        ->and($this->user->fresh()->jubelio_contact_id)->toBe(4321);
});

test('only an exact email match counts; a similar one does not', function () {
    $this->routes['GET /contacts/customers/'] = Http::response(['data' => [['contact_id' => 100, 'email' => 'budi@example.com.au']]]);

    expect(app(ContactService::class)->ensure($this->user, $this->address))->toBe(5512);
});

test('a Jubelio failure leaves the user without a contact id', function () {
    $this->routes['POST /contacts/'] = Http::response(['message' => 'An internal server error occurred'], 500);

    expect(fn () => app(ContactService::class)->ensure($this->user, $this->address))->toThrow(IntegrationException::class);
    expect($this->user->fresh()->jubelio_contact_id)->toBeNull();
});

test('a profile change updates the existing contact, and does nothing before the first checkout', function () {
    app(ContactService::class)->sync($this->user);
    Http::assertNothingSent();

    $this->user->forceFill(['jubelio_contact_id' => 5512, 'name' => 'Budi S.'])->save();
    app(ContactService::class)->sync($this->user->fresh());

    expect(jubelioCallsTo('POST', '/contacts/')[0]->data())->toMatchArray(['contact_id' => 5512, 'contact_name' => 'Budi S.']);
});

// --- Sales order (INT-JB-10, INT-JB-13) ------------------------------------------------

test('the payload follows the contract and the order snapshot', function () {
    $this->user->forceFill(['jubelio_contact_id' => 5512])->save();
    $order = orderWithItems($this->user);

    $payload = app(SalesOrderService::class)->payload($order);

    expect($payload)->toMatchArray([
        'salesorder_id' => 0, 'salesorder_no' => '[auto]', 'contact_id' => 5512, 'customer_name' => 'Budi Santoso',
        'note' => 'Tolong dibungkus kardus tebal', 'sub_total' => 6750000, 'total_disc' => 0, 'total_tax' => 0, 'grand_total' => 6935000,
        'ref_no' => 'WEB-000123', 'location_id' => -1, 'source' => 1, 'store_id' => '-102', 'is_paid' => false,
        'payment_method' => 'DANA Gapura', 'shipping_cost' => 185000, 'insurance_cost' => 0,
        'shipping_full_name' => $order->shipping_recipient_name, 'shipping_address' => 'Jl. Melati No. 12, RT 03/RW 05',
        'shipping_area' => 'TAMBUN UTARA', 'shipping_city' => 'KAB. BEKASI', 'shipping_subdistrict' => 'Satriajaya',
        'shipping_province' => 'JAWA BARAT', 'shipping_province_id' => '32', 'shipping_city_id' => '3216',
        'shipping_district_id' => '321605', 'shipping_subdistrict_id' => '3216052001', 'shipping_post_code' => '17565',
        'shipping_country' => 'Indonesia', 'add_disc' => 0, 'add_fee' => 0, 'service_fee' => 0, 'is_tax_included' => false,
    ])
        ->and($payload['transaction_date'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/')
        ->and($payload['items'])->toBe([[
            'salesorder_detail_id' => 0, 'item_id' => 2976, 'description' => 'Sepeda Listrik Uwinfly C1 Pro - Hitam', 'tax_id' => 1,
            'price' => 6750000, 'unit' => 'Unit', 'qty_in_base' => 1, 'disc' => 0, 'disc_amount' => 0, 'tax_amount' => 0,
            'amount' => 6750000, 'location_id' => -1, 'shipper' => 'JNE Trucking',
        ]]);
});

test('the grand total of the payload is the sum of the item amounts plus shipping', function () {
    $this->user->forceFill(['jubelio_contact_id' => 5512])->save();
    $order = orderWithItems($this->user, ['subtotal' => 6750000, 'shipping_cost' => 185000, 'insurance_cost' => 0, 'grand_total' => 6935000]);

    $payload = app(SalesOrderService::class)->payload($order);

    expect($payload['grand_total'])->toBe($payload['sub_total'] + $payload['shipping_cost'] + $payload['insurance_cost'])
        ->and($payload['sub_total'])->toBe(array_sum(array_column($payload['items'], 'amount')));
});

test('creating checks ref_no first, posts the order, and reads back the number', function () {
    $this->user->forceFill(['jubelio_contact_id' => 5512])->save();
    $order = orderWithItems($this->user);

    $result = app(SalesOrderService::class)->create($order);

    expect($result)->toBe(['id' => 98112, 'no' => 'SO-000981']);
    $search = Http::recorded()->first(fn (array $pair) => str_contains($pair[0]->url(), '/sales/v2/orders/'))[0];
    parse_str((string) parse_url($search->url(), PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(['q' => 'WEB-000123', 'store_id' => '-102']);
    expect(jubelioCallsTo('POST', '/sales/orders/'))->toHaveCount(1);
});

test('a sales order that already exists for the ref_no is reused, never created twice', function () {
    $this->user->forceFill(['jubelio_contact_id' => 5512])->save();
    $this->routes['GET /sales/v2/orders/'] = Http::response(['data' => [
        ['salesorder_id' => 1, 'salesorder_no' => 'SO-1', 'ref_no' => 'WEB-0001239'],
        ['salesorder_id' => 98112, 'salesorder_no' => 'SO-000981', 'ref_no' => 'WEB-000123'],
    ]]);

    $result = app(SalesOrderService::class)->create(orderWithItems($this->user));

    expect($result)->toBe(['id' => 98112, 'no' => 'SO-000981'])->and(jubelioCallsTo('POST', '/sales/orders/'))->toBeEmpty();
});

test('a 500 for an order Jubelio did create finds it by ref_no instead of failing', function () {
    $this->user->forceFill(['jubelio_contact_id' => 5512])->save();
    $this->routes['POST /sales/orders/'] = Http::response(['message' => 'An internal server error occurred'], 500);
    $lookups = 0;
    $this->routes['GET /sales/v2/orders/'] = function () use (&$lookups) {
        return ++$lookups === 1
            ? Http::response(['data' => []])
            : Http::response(['data' => [['salesorder_id' => 98112, 'salesorder_no' => 'SO-000981', 'ref_no' => 'WEB-000123']]]);
    };

    expect(app(SalesOrderService::class)->create(orderWithItems($this->user)))->toBe(['id' => 98112, 'no' => 'SO-000981']);
});

test('a failed create for an order Jubelio does not have is the original error', function () {
    $this->user->forceFill(['jubelio_contact_id' => 5512])->save();
    $this->routes['POST /sales/orders/'] = Http::response(['code' => '23505', 'message' => 'duplicate key value'], 500);

    $e = catchIntegration(fn () => app(SalesOrderService::class)->create(orderWithItems($this->user)));

    expect($e->providerCode)->toBe('23505');
});

test('an unreadable sales order number does not fail the order', function () {
    $this->user->forceFill(['jubelio_contact_id' => 5512])->save();
    $this->routes['GET /sales/orders/98112'] = Http::response(['message' => 'boom'], 500);

    expect(app(SalesOrderService::class)->create(orderWithItems($this->user)))->toBe(['id' => 98112, 'no' => null]);
});

test('a response without an id is not trusted', function () {
    $this->user->forceFill(['jubelio_contact_id' => 5512])->save();
    $this->routes['POST /sales/orders/'] = Http::response(['status' => 'ok']);

    expect(fn () => app(SalesOrderService::class)->create(orderWithItems($this->user)))->toThrow(IntegrationException::class);
});

test('a sales order cannot be built before the customer has a Jubelio contact', function () {
    expect(fn () => app(SalesOrderService::class)->create(orderWithItems($this->user)))->toThrow(LogicException::class, 'contact Jubelio');
});

// --- Paid and cancel (INT-JB-11, INT-JB-12) ---------------------------------------------

test('mark paid and cancel send the stored sales order id', function () {
    $order = orderWithItems($this->user, ['jubelio_salesorder_id' => 98112]);

    app(SalesOrderService::class)->markPaid($order);
    app(SalesOrderService::class)->cancel($order);

    expect(jubelioCallsTo('POST', '/sales/orders/set-as-paid')[0]->data())->toBe(['ids' => [98112]])
        ->and(jubelioCallsTo('POST', '/sales/orders/cancel/')[0]->data())->toBe(['ids' => [98112]]);
});

test('paying or cancelling an order that has no sales order is a programming error', function () {
    $order = orderWithItems($this->user);

    expect(fn () => app(SalesOrderService::class)->markPaid($order))->toThrow(LogicException::class)
        ->and(fn () => app(SalesOrderService::class)->cancel($order))->toThrow(LogicException::class);
});
