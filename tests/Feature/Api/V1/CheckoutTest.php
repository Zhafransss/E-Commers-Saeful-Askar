<?php

use App\Enums\JubelioSyncStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\Jubelio\CancelSalesOrderJob;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Gapura\GapuraClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;

/*
| ORD-01 (docs/api/checkout-orders.md): every step, every error code, and every failure midway.
| Jubelio is faked with Http::fake(); Gapura with a Guzzle mock handler behind the DANA SDK.
*/

beforeEach(function () {
    config([
        'jubelio.base_url' => 'https://api2.jubelio.com', 'jubelio.email' => 'owner@saefulaskar.id', 'jubelio.password' => 'x',
        'jubelio.store_id' => '-102', 'jubelio.location_id' => -1, 'jubelio.tax_id' => 1,
        'gapura.env' => 'sandbox', 'gapura.client_id' => 'client-123', 'gapura.merchant_id' => 'merchant-456',
        'gapura.private_key_path' => gapuraKeys('merchant')['private'], 'gapura.mcc' => '5940',
        'gapura.origin' => 'https://toko.test', 'gapura.payment_duration' => 7200,
    ]);
    Queue::fake();

    $this->user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com', 'phone' => '081234567890']);
    $this->address = Address::factory()->for($this->user)->default()->create();
    $this->bike = ProductVariant::factory()->for(Product::factory()->create(['name' => 'Sepeda Listrik Uwinfly C1 Pro', 'sell_unit' => 'Unit', 'package_weight' => 32000]))
        ->create(['sku' => 'UWF-HTM', 'name' => 'Sepeda Listrik Uwinfly C1 Pro - Hitam', 'jubelio_item_id' => 2976, 'price' => 6750000,
            'available_qty' => 4, 'package_weight' => 32000, 'variation_values' => [['label' => 'Warna', 'value' => 'Hitam']], 'image_url' => 'https://img.test/hitam.jpg']);
    $this->user->cartItems()->create(['product_variant_id' => $this->bike->id, 'qty' => 1]);

    $this->routes = [];
    $this->jubelioStock = ['UWF-HTM' => 4];
    Http::fake(function (Request $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        $key = $request->method().' '.$path;
        $default = [
            'POST /login' => Http::response(fixture('jubelio/login.json')),
            'GET /inventory/' => function (Request $request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $sku = $query['q'] ?? '';

                return Http::response(['totalCount' => 1, 'data' => [[
                    'item_id' => 2976, 'item_code' => $sku,
                    'location_stocks' => [['location_id' => -1, 'available' => $this->jubelioStock[$sku] ?? 0]],
                ]]]);
            },
            'GET /contacts/customers/' => Http::response(['data' => []]),
            'POST /contacts/' => Http::response(['status' => 'ok', 'contact_id' => 5512]),
            'GET /sales/v2/orders/' => Http::response(['data' => []]),
            'POST /sales/orders/' => Http::response(['id' => 98112]),
            'GET /sales/orders/98112' => Http::response(['salesorder_id' => 98112, 'salesorder_no' => 'SO-000981']),
            'POST /sales/orders/cancel/' => Http::response(['status' => 'ok']),
        ];
        $response = $this->routes[$key] ?? $default[$key] ?? Http::response(['message' => 'Not Found'], 404);

        return $response instanceof Closure ? $response($request) : $response;
    });

    $this->gapuraSent = [];
    useGapura($this, [new Response(200, ['Content-Type' => 'application/json'], fixtureContent('gapura/create-order-success.json'))]);
});

/**
 * Binds a GapuraClient whose SDK answers from $responses and records the requests in $test->gapuraSent.
 *
 * @param  list<Response|Throwable>  $responses
 */
function useGapura($test, array $responses): void
{
    $test->gapuraSent = new ArrayObject;
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::mapRequest(function (RequestInterface $request) use ($test) {
        $test->gapuraSent->append($request);

        return $request;
    }));
    app()->instance(GapuraClient::class, new GapuraClient(config('gapura'), new Client(['handler' => $stack])));
}

/** A rate quoted for the user's cart, as SHIP-01 gives it: the one checkout will name. */
function quotedRate($test, string $serviceType = 'cargo'): array
{
    $rates = $test->actingAs($test->user)->postJson('/api/v1/shipping/rates', ['address_id' => $test->address->id])->assertOk()->json('data.rates');

    return collect($rates)->firstWhere('service_type', $serviceType);
}

function checkout($test, array $body = [], ?string $key = null)
{
    $rate = $body['rate'] ?? quotedRate($test);
    unset($body['rate']);

    return $test->actingAs($test->user)->postJson('/api/v1/checkout', $body + [
        'address_id' => $test->address->id,
        'rate_id' => $rate['rate_id'],
        'note' => 'Tolong dibungkus kardus tebal',
        'expected_grand_total' => 6750000 + $rate['cost'],
    ], ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
}

function jubelioPosts(string $path): array
{
    return Http::recorded()
        ->filter(fn (array $pair) => $pair[0]->method() === 'POST' && parse_url($pair[0]->url(), PHP_URL_PATH) === $path)
        ->map(fn (array $pair) => $pair[0])->values()->all();
}

// --- The happy path --------------------------------------------------------------------

test('ORD-01 creates the order, the Jubelio sales order, and the Gapura order, and answers 201 with the payment page', function () {
    $rate = quotedRate($this);

    $response = checkout($this, ['rate' => $rate])->assertCreated();

    $order = Order::sole();
    $data = $response->json('data');
    expect($data['redirect_url'])->toStartWith('https://m.sandbox.dana.id/d/portal/cashier')
        ->and($data['order'])->toMatchArray([
            'order_number' => $order->order_number, 'status' => 'pending_payment', 'status_label' => 'Menunggu pembayaran',
            'note' => 'Tolong dibungkus kardus tebal', 'can_pay' => true, 'can_cancel' => true,
        ])
        ->and($data['order']['amounts'])->toBe([
            'subtotal' => 6750000, 'shipping_cost' => $rate['cost'], 'insurance_cost' => 0, 'discount' => 0, 'tax' => 0, 'grand_total' => 6750000 + $rate['cost'],
        ])
        ->and($data['order']['payment'])->toMatchArray(['status' => 'PENDING', 'checkout_url' => $data['redirect_url']])
        ->and($data['order']['items'][0])->toMatchArray([
            'sku' => 'UWF-HTM', 'product_name' => 'Sepeda Listrik Uwinfly C1 Pro', 'variant_name' => 'Hitam', 'price' => 6750000, 'qty' => 1, 'weight_gram' => 32000, 'subtotal' => 6750000,
        ])
        ->and($data['order']['shipping'])->toMatchArray(['courier_code' => $rate['courier_code'], 'service_name' => $rate['service_name'], 'tracking_no' => null])
        ->and(collect($data['order']['status_history'])->pluck('status')->all())->toBe(['pending_payment']);

    expect($order)
        ->order_number->toBe(sprintf('WEB-%06d', $order->id))
        ->status->toBe(OrderStatus::PendingPayment)
        ->user_id->toBe($this->user->id)
        ->shipping_recipient_name->toBe($this->address->recipient_name)
        ->shipping_subdistrict_id->toBe('3216052001')
        ->jubelio_salesorder_id->toBe(98112)
        ->jubelio_salesorder_no->toBe('SO-000981')
        ->jubelio_sync_status->toBe(JubelioSyncStatus::Synced)
        ->is_checkout_failed->toBeFalse();
    expect($order->items)->toHaveCount(1)
        ->and($order->items->first())->jubelio_item_id->toBe(2976)->unit->toBe('Unit')->image_url->toBe('https://img.test/hitam.jpg')
        ->and($order->statusHistories)->toHaveCount(1);
});

test('the order total, the Gapura amount, and the Jubelio sales order total are the same number', function () {
    $rate = quotedRate($this);
    checkout($this, ['rate' => $rate])->assertCreated();

    $order = Order::sole();
    $gapuraBody = json_decode((string) $this->gapuraSent[0]->getBody(), true);
    $jubelioBody = jubelioPosts('/sales/orders/')[0]->data();

    expect($order->grand_total)->toBe(6750000 + $rate['cost'])
        ->and($gapuraBody['amount']['value'])->toBe(number_format($order->grand_total, 2, '.', ''))
        ->and($jubelioBody['grand_total'])->toBe($order->grand_total)
        ->and($gapuraBody['partnerReferenceNo'])->toBe($order->order_number)
        ->and($jubelioBody['ref_no'])->toBe($order->order_number)
        ->and(Payment::sole())->amount->toBe($order->grand_total)->partner_reference_no->toBe($order->order_number);
});

test('the payment is stored with DANA reference, page, status, and the deadline that was sent', function () {
    checkout($this)->assertCreated();

    $payment = Payment::sole();
    $sent = json_decode((string) $this->gapuraSent[0]->getBody(), true);

    expect($payment)
        ->gapura_reference_no->toBe('20261020111212800110166000000000001')
        ->checkout_url->toStartWith('https://m.sandbox.dana.id/')
        ->status->toBe(PaymentStatus::Pending)
        ->and($payment->expires_at->toIso8601String())->toBe(Carbon::parse($sent['validUpTo'])->toIso8601String())
        // The sandbox shortens two hours to 30 minutes; the order keeps the shorter deadline.
        ->and(Order::sole()->payment_expires_at->equalTo($payment->expires_at))->toBeTrue();
});

test('DANA is told where to send the result and where to bring the customer back', function () {
    checkout($this)->assertCreated();

    $urls = collect(json_decode((string) $this->gapuraSent[0]->getBody(), true)['urlParams'])->pluck('url', 'type');

    expect($urls['NOTIFICATION'])->toEndWith('/v1.0/debit/notify')
        ->and($urls['PAY_RETURN'])->toBe(route('checkout.finish', ['order' => Order::sole()->order_number]));
});

test('the cart is emptied only after everything succeeded', function () {
    checkout($this)->assertCreated();

    expect($this->user->cartItems()->count())->toBe(0);
});

test('the Jubelio contact is created once and kept for later checkouts', function () {
    checkout($this)->assertCreated();

    expect($this->user->fresh()->jubelio_contact_id)->toBe(5512)
        ->and(jubelioPosts('/contacts/'))->toHaveCount(1);
});

// --- Idempotency and double clicks ------------------------------------------------------------

test('the same Idempotency-Key again returns the same response and makes nothing new', function () {
    $rate = quotedRate($this);
    $key = (string) Str::uuid();

    $first = checkout($this, ['rate' => $rate], $key)->assertCreated();
    $second = checkout($this, ['rate' => $rate], $key)->assertCreated();

    expect($second->json())->toBe($first->json())
        ->and(Order::count())->toBe(1)
        ->and(jubelioPosts('/sales/orders/'))->toHaveCount(1)
        ->and($this->gapuraSent)->toHaveCount(1);
});

test('a second click while the first is still working is DUPLICATE_REQUEST and creates nothing', function () {
    $rate = quotedRate($this);
    $lock = Cache::lock("checkout:user:{$this->user->id}", 60);
    expect($lock->get())->toBeTrue();

    assertApiError(checkout($this, ['rate' => $rate]), 409, 'DUPLICATE_REQUEST');

    $lock->release();
    expect(Order::count())->toBe(0)->and(jubelioPosts('/sales/orders/'))->toBeEmpty();
});

test('the lock is released after a failed checkout, so the customer can try again', function () {
    $rate = quotedRate($this);
    $this->routes['POST /sales/orders/'] = Http::response(['message' => 'An internal server error occurred'], 500);
    checkout($this, ['rate' => $rate])->assertStatus(502);

    $this->routes = [];
    useGapura($this, [new Response(200, ['Content-Type' => 'application/json'], fixtureContent('gapura/create-order-success.json'))]);
    $this->user->cartItems()->first() ?? $this->user->cartItems()->create(['product_variant_id' => $this->bike->id, 'qty' => 1]);

    checkout($this, ['rate' => quotedRate($this)])->assertCreated();
});

test('a checkout that was refused is not remembered under its key', function () {
    $rate = quotedRate($this);
    $key = (string) Str::uuid();

    assertApiError(checkout($this, ['rate' => $rate, 'expected_grand_total' => 1], $key), 409, 'PRICE_CHANGED');
    checkout($this, ['rate' => $rate], $key)->assertCreated();

    expect(Order::count())->toBe(1);
});

// --- Input and the checks before anything is made ----------------------------------------------

test('ORD-01 validates the body and the Idempotency-Key header', function (array $body, ?string $header, string $field) {
    $headers = $header === null ? [] : ['Idempotency-Key' => $header];

    assertApiError($this->actingAs($this->user)->postJson('/api/v1/checkout', $body, $headers), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors($field);

    expect(Order::count())->toBe(0);
})->with([
    'no header' => [['address_id' => 1, 'rate_id' => 'rt_x', 'expected_grand_total' => 1], null, 'idempotency_key'],
    'header is not a uuid' => [['address_id' => 1, 'rate_id' => 'rt_x', 'expected_grand_total' => 1], 'abc', 'idempotency_key'],
    'no address' => [['rate_id' => 'rt_x', 'expected_grand_total' => 1], 'b9d3a1de-6a32-4d6c-9b1c-5d1e5c1c0a11', 'address_id'],
    'no rate' => [['address_id' => 1, 'expected_grand_total' => 1], 'b9d3a1de-6a32-4d6c-9b1c-5d1e5c1c0a11', 'rate_id'],
    'no total' => [['address_id' => 1, 'rate_id' => 'rt_x'], 'b9d3a1de-6a32-4d6c-9b1c-5d1e5c1c0a11', 'expected_grand_total'],
    'note too long' => [['address_id' => 1, 'rate_id' => 'rt_x', 'expected_grand_total' => 1, 'note' => str_repeat('a', 256)], 'b9d3a1de-6a32-4d6c-9b1c-5d1e5c1c0a11', 'note'],
]);

test('ORD-01 needs a login', function () {
    assertApiError($this->postJson('/api/v1/checkout', [], ['Idempotency-Key' => 'b9d3a1de-6a32-4d6c-9b1c-5d1e5c1c0a11']), 401, 'UNAUTHENTICATED');
});

test('an address that is not the customer\'s is ADDRESS_NOT_FOUND', function () {
    $rate = quotedRate($this);
    $foreign = Address::factory()->create();

    assertApiError(checkout($this, ['rate' => $rate, 'address_id' => $foreign->id]), 404, 'ADDRESS_NOT_FOUND');
    expect(Order::count())->toBe(0);
});

test('an empty cart is CART_EMPTY', function () {
    $rate = quotedRate($this);
    $this->user->cartItems()->delete();

    assertApiError(checkout($this, ['rate' => $rate]), 422, 'CART_EMPTY');
});

test('a cart line with a problem is CART_HAS_ISSUES and carries the cart', function () {
    $rate = quotedRate($this);
    $this->user->cartItems()->update(['qty' => 3]);
    $this->bike->update(['available_qty' => 1]);

    $response = checkout($this, ['rate' => $rate]);

    assertApiError($response, 409, 'CART_HAS_ISSUES');
    expect($response->json('cart.summary.has_issue'))->toBeTrue()
        ->and($response->json('cart.items.0.issue'))->toBe('QTY_EXCEEDS_STOCK');
    expect(Order::count())->toBe(0);
});

test('a customer without a profile phone checks out with the address phone and keeps it', function () {
    $rate = quotedRate($this);
    $this->user->forceFill(['phone' => null])->save();

    checkout($this, ['rate' => $rate])->assertCreated();

    expect($this->user->fresh()->phone)->toBe($this->address->phone);
});

test('a profile phone is not filled from an address number that another account already has', function () {
    $rate = quotedRate($this);
    User::factory()->create(['phone' => $this->address->phone]);
    $this->user->forceFill(['phone' => null])->save();

    checkout($this, ['rate' => $rate])->assertCreated();

    expect($this->user->fresh()->phone)->toBeNull();
});

test('only the ticked cart lines are bought, and the others stay in the cart', function () {
    $other = ProductVariant::factory()->for(Product::factory()->create(['package_weight' => 1000]))
        ->create(['sku' => 'ACC-1', 'price' => 50000, 'available_qty' => 9, 'package_weight' => 1000]);
    $this->user->cartItems()->create(['product_variant_id' => $other->id, 'qty' => 2]);
    $bikeLine = $this->user->cartItems()->where('product_variant_id', $this->bike->id)->first();

    $rate = $this->actingAs($this->user)
        ->postJson('/api/v1/shipping/rates', ['address_id' => $this->address->id, 'item_ids' => [$bikeLine->id]])
        ->assertOk()->assertJsonPath('data.package.item_count', 1)->json('data.rates.0');

    checkout($this, ['rate' => $rate, 'item_ids' => [$bikeLine->id], 'expected_grand_total' => 6750000 + $rate['cost'] + $rate['insurance_cost']])
        ->assertCreated();

    expect(Order::first()->items)->toHaveCount(1)
        ->and($this->user->cartItems()->pluck('product_variant_id')->all())->toBe([$other->id]);
});

test('a rate quoted for the whole cart does not fit a smaller selection', function () {
    $other = ProductVariant::factory()->for(Product::factory()->create(['package_weight' => 1000]))
        ->create(['sku' => 'ACC-1', 'price' => 50000, 'available_qty' => 9, 'package_weight' => 1000]);
    $this->user->cartItems()->create(['product_variant_id' => $other->id, 'qty' => 1]);
    $rate = quotedRate($this);
    $bikeLine = $this->user->cartItems()->where('product_variant_id', $this->bike->id)->first();

    assertApiError(checkout($this, ['rate' => $rate, 'item_ids' => [$bikeLine->id]]), 409, 'RATE_EXPIRED');
});

test('item_ids that match nothing in the cart are CART_EMPTY', function () {
    $rate = quotedRate($this);

    assertApiError(checkout($this, ['rate' => $rate, 'item_ids' => [999999]]), 422, 'CART_EMPTY');
    expect(Order::count())->toBe(0);
});

test('an unknown or expired rate is RATE_EXPIRED', function () {
    assertApiError(checkout($this, ['rate' => ['rate_id' => 'rt_nonsuch0', 'cost' => 1]]), 409, 'RATE_EXPIRED');

    $rate = quotedRate($this);
    $this->travel(31)->minutes();
    assertApiError(checkout($this, ['rate' => $rate]), 409, 'RATE_EXPIRED');
});

test('a cart that changed since the rate was quoted is RATE_EXPIRED', function () {
    $rate = quotedRate($this);
    $this->user->cartItems()->update(['qty' => 2]);

    assertApiError(checkout($this, ['rate' => $rate]), 409, 'RATE_EXPIRED');
});

test('a total that is not what the customer saw is PRICE_CHANGED with the new amounts', function () {
    $rate = quotedRate($this);
    $this->bike->update(['price' => 6800000]);

    $response = checkout($this, ['rate' => $rate, 'expected_grand_total' => 6750000 + $rate['cost']]);

    assertApiError($response, 409, 'PRICE_CHANGED');
    expect($response->json('amounts'))->toBe([
        'subtotal' => 6800000, 'shipping_cost' => $rate['cost'], 'insurance_cost' => 0, 'discount' => 0, 'tax' => 0, 'grand_total' => 6800000 + $rate['cost'],
    ])->and($response->json('message'))->toStartWith('Harga atau ongkir berubah. Total baru Rp6.');
    expect(Order::count())->toBe(0);
});

test('stock that Jubelio no longer has is OUT_OF_STOCK, and the local copy is refreshed', function () {
    $rate = quotedRate($this);
    $this->jubelioStock['UWF-HTM'] = 0;

    $response = checkout($this, ['rate' => $rate]);

    assertApiError($response, 409, 'OUT_OF_STOCK');
    expect($response->json('items'))->toBe([[
        'variant_id' => $this->bike->id, 'sku' => 'UWF-HTM', 'name' => 'Sepeda Listrik Uwinfly C1 Pro - Hitam', 'requested_qty' => 1, 'available_qty' => 0,
    ]])->and($this->bike->fresh()->available_qty)->toBe(0)
        ->and(Order::count())->toBe(0)->and(jubelioPosts('/sales/orders/'))->toBeEmpty()->and($this->gapuraSent)->toBeEmpty();
});

// --- Failures midway ---------------------------------------------------------------------------

test('a Jubelio failure for the contact stops the checkout before any order exists', function () {
    $rate = quotedRate($this);
    $this->routes['POST /contacts/'] = Http::response(['message' => 'An internal server error occurred'], 500);

    assertApiError(checkout($this, ['rate' => $rate]), 502, 'INTEGRATION_ERROR');

    expect(Order::count())->toBe(0)->and($this->user->cartItems()->count())->toBe(1);
});

test('a failed sales order cancels the order, keeps the cart, and never asks Gapura', function () {
    $rate = quotedRate($this);
    $this->routes['POST /sales/orders/'] = Http::response(['code' => '23505', 'message' => 'duplicate key value'], 500);

    $response = checkout($this, ['rate' => $rate]);

    assertApiError($response, 502, 'INTEGRATION_ERROR');
    expect($response->json('message'))->toBe('Pesanan belum bisa dibuat karena sistem toko sedang sibuk. Tidak ada tagihan yang dibuat. Silakan coba lagi.');
    $order = Order::sole();
    expect($order)
        ->status->toBe(OrderStatus::Cancelled)
        ->cancel_reason->toBe('Gagal membuat pesanan di Jubelio')
        ->is_checkout_failed->toBeTrue()
        ->and($order->jubelio_last_error)->toContain('23505')
        ->and($order->statusHistories->pluck('status')->all())->toBe([OrderStatus::PendingPayment, OrderStatus::Cancelled])
        ->and($this->user->cartItems()->count())->toBe(1)
        ->and($this->gapuraSent)->toBeEmpty()
        ->and(Payment::count())->toBe(0);
});

test('a failed Gapura order gives the sales order back, cancels the order, and keeps the cart', function () {
    $rate = quotedRate($this);
    // DANA is asked twice: inside a customer request a transient failure is retried once (INT-HDL).
    useGapura($this, array_fill(0, 2, new Response(500, [], '{"responseCode":"5005401","responseMessage":"Internal Server Error"}')));

    $response = checkout($this, ['rate' => $rate]);

    assertApiError($response, 502, 'INTEGRATION_ERROR');
    $order = Order::sole();
    expect(jubelioPosts('/sales/orders/cancel/')[0]->data())->toBe(['ids' => [98112]])
        ->and($order)->status->toBe(OrderStatus::Cancelled)->cancel_reason->toBe('Gagal membuat pembayaran di Gapura')->is_checkout_failed->toBeTrue()
        ->and($order->jubelio_last_error)->toContain('5005401')
        ->and($this->user->cartItems()->count())->toBe(1)
        ->and(Payment::count())->toBe(0)
        ->and($this->gapuraSent)->toHaveCount(2);
    Queue::assertNotPushed(CancelSalesOrderJob::class);
});

test('when the sales order cannot be cancelled right away, the queue keeps trying and the order is marked failed', function () {
    $rate = quotedRate($this);
    // DANA is asked twice: inside a customer request a transient failure is retried once (INT-HDL).
    useGapura($this, array_fill(0, 2, new Response(500, [], '{"responseCode":"5005401","responseMessage":"Internal Server Error"}')));
    $this->routes['POST /sales/orders/cancel/'] = Http::response(['message' => 'An internal server error occurred'], 500);

    assertApiError(checkout($this, ['rate' => $rate]), 502, 'INTEGRATION_ERROR');

    $order = Order::sole();
    expect($order->jubelio_sync_status)->toBe(JubelioSyncStatus::Failed)->and($order->status)->toBe(OrderStatus::Cancelled);
    Queue::assertPushed(CancelSalesOrderJob::class, fn (CancelSalesOrderJob $job) => $job->orderId === $order->id);
});

test('an order number is never reused after a failed checkout', function () {
    $rate = quotedRate($this);
    $this->routes['POST /sales/orders/'] = Http::response(['message' => 'An internal server error occurred'], 500);
    checkout($this, ['rate' => $rate])->assertStatus(502);
    $failed = Order::sole();

    $this->routes = [];
    useGapura($this, [new Response(200, ['Content-Type' => 'application/json'], fixtureContent('gapura/create-order-success.json'))]);
    checkout($this, ['rate' => quotedRate($this)])->assertCreated();

    expect(Order::count())->toBe(2)->and(Order::orderByDesc('id')->first()->order_number)->not->toBe($failed->order_number);
});
