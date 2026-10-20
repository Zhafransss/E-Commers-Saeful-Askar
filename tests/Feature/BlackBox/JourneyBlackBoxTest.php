<?php

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Gapura\GapuraClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/*
| Black-box user journeys through HTTP only: browse → cart → address → rates → checkout → pay (webhook) → order pages,
| plus the cancel journey. External services (Jubelio, DANA) are faked at the network edge.
*/

beforeEach(function () {
    Mail::fake();
    config([
        'jubelio.base_url' => 'https://api2.jubelio.com', 'jubelio.email' => 'owner@saefulaskar.id', 'jubelio.password' => 'x',
        'jubelio.store_id' => '-102', 'jubelio.location_id' => -1, 'jubelio.tax_id' => 1,
        'gapura.env' => 'production', 'gapura.client_id' => 'client-123', 'gapura.merchant_id' => '216620000000000000000', 'gapura.mcc' => '5940',
        'gapura.private_key_path' => gapuraKeys('merchant')['private'], 'gapura.dana_public_key_path' => gapuraKeys('dana')['public'],
        'gapura.origin' => 'https://toko.test', 'gapura.payment_duration' => 600,
        'shipping.driver' => 'stub',
    ]);

    $this->jubelioStock = 4;
    $this->cancelled = 0;
    Http::fake(function (Request $request) {
        $key = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);

        return match ($key) {
            'POST /login' => Http::response(fixture('jubelio/login.json')),
            'GET /inventory/' => (function () use ($request) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                return Http::response(['totalCount' => 1, 'data' => [['item_id' => 2976, 'item_code' => $query['q'] ?? '', 'location_stocks' => [['location_id' => -1, 'available' => $this->jubelioStock]]]]]);
            })(),
            'GET /contacts/customers/' => Http::response(['data' => []]),
            'POST /contacts/' => Http::response(['status' => 'ok', 'contact_id' => 5512]),
            'GET /sales/v2/orders/' => Http::response(['data' => []]),
            'POST /sales/orders/' => Http::response(['id' => 98112]),
            'GET /sales/orders/98112' => Http::response(['salesorder_id' => 98112, 'salesorder_no' => 'SO-000981']),
            'POST /sales/orders/cancel/' => (function () {
                $this->cancelled++;

                return Http::response(['status' => 'ok']);
            })(),
            default => Http::response(['message' => 'Not Found'], 404),
        };
    });
    bbDana([new Response(200, ['Content-Type' => 'application/json'], fixtureContent('gapura/create-order-success.json'))]);

    $this->user = bbCustomer(['phone' => '081234567890']);
    $this->address = Address::factory()->for($this->user)->default()->create();
    $this->variant = ProductVariant::factory()->for(Product::factory()->create(['package_weight' => 32000]))
        ->create(['sku' => 'UWF-HTM', 'jubelio_item_id' => 2976, 'price' => 6750000, 'available_qty' => 4, 'package_weight' => 32000]);
});

function bbDana(array $responses): void
{
    app()->instance(GapuraClient::class, new GapuraClient(config('gapura'), new Client(['handler' => HandlerStack::create(new MockHandler($responses))])));
}

function bbQuote($test): array
{
    $rates = $test->actingAs($test->user)->postJson('/api/v1/shipping/rates', ['address_id' => $test->address->id])->assertOk()->json('data.rates');

    return $rates[0];
}

function bbCheckout($test, ?array $rate = null, array $override = [], ?string $key = null)
{
    $rate ??= bbQuote($test);
    $total = $test->variant->price * (int) $test->user->cartItems()->sum('qty') + $rate['cost'];

    return $test->actingAs($test->user)->postJson('/api/v1/checkout', $override + [
        'address_id' => $test->address->id, 'rate_id' => $rate['rate_id'], 'expected_grand_total' => $total,
    ], ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
}

function bbNotify($test, string $orderNumber, string $referenceNo, int $amount, string $status = '00')
{
    $body = json_encode(['originalPartnerReferenceNo' => $orderNumber, 'originalReferenceNo' => $referenceNo, 'merchantId' => '216620000000000000000',
        'amount' => ['value' => number_format($amount, 2, '.', ''), 'currency' => 'IDR'], 'latestTransactionStatus' => $status, 'transactionStatusDesc' => 'x',
        'createdTime' => now()->toIso8601String(), 'finishedTime' => now()->toIso8601String(),
        'additionalInfo' => ['paymentInfo' => ['paidTime' => now()->toIso8601String(), 'payOptionInfos' => [['payMethod' => 'VIRTUAL_ACCOUNT', 'payOption' => 'VIRTUAL_ACCOUNT_BCA', 'payAmount' => ['value' => number_format($amount, 2, '.', ''), 'currency' => 'IDR']]]]],
    ], JSON_UNESCAPED_SLASHES);

    return $test->call('POST', '/v1.0/debit/notify', [], [], [], array_merge(
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        collect(gapuraNotifyHeaders($body))->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all(),
    ), $body);
}

test('the whole purchase: cart → rates → checkout → webhook paid → order pages agree', function () {
    $this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->variant->id, 'qty' => 1])->assertCreated();
    $rate = bbQuote($this);

    $checkout = bbCheckout($this, $rate)->assertCreated();
    $number = $checkout->json('data.order.order_number');
    $total = $checkout->json('data.order.amounts.grand_total');

    // Money adds up and the cart is emptied.
    expect($total)->toBe(6750000 + $rate['cost'])
        ->and($checkout->json('data.redirect_url'))->toStartWith('https://')
        ->and($this->user->cartItems()->count())->toBe(0);

    // The order is visible in the list and detail, in pending_payment, and can still be paid.
    $this->actingAs($this->user)->getJson('/api/v1/orders')->assertOk()->assertJsonPath('data.0.order_number', $number)->assertJsonPath('data.0.status', 'pending_payment');
    $this->actingAs($this->user)->getJson("/api/v1/orders/{$number}/payment")->assertOk()->assertJsonPath('data.amount', $total)->assertJsonPath('data.checkout_url', $checkout->json('data.redirect_url'));

    // DANA tells us it is paid.
    $reference = Order::where('order_number', $number)->first()->payment->gapura_reference_no;
    bbNotify($this, $number, $reference, $total)->assertOk()->assertJsonPath('responseCode', '2005600');

    $detail = $this->actingAs($this->user)->getJson("/api/v1/orders/{$number}")->assertOk();
    expect($detail->json('data.status'))->toBe('paid')
        ->and($detail->json('data.can_pay'))->toBeFalse()->and($detail->json('data.can_cancel'))->toBeFalse()
        ->and($detail->json('data.payment.status'))->toBe('PAID')
        ->and(collect($detail->json('data.status_history'))->pluck('status')->all())->toBe(['pending_payment', 'paid']);

    // Once paid it can neither be paid again nor cancelled by the customer.
    assertApiError($this->actingAs($this->user)->getJson("/api/v1/orders/{$number}/payment"), 409, 'ORDER_NOT_PAYABLE');
    expect($this->actingAs($this->user)->postJson("/api/v1/orders/{$number}/cancel")->getStatusCode())->toBeIn([409, 422]);
});

test('checkout is idempotent: the same key twice gives one order and the same answer', function () {
    $this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->variant->id, 'qty' => 1]);
    $rate = bbQuote($this);
    $key = (string) Str::uuid();

    $first = bbCheckout($this, $rate, [], $key)->assertCreated();
    $second = bbCheckout($this, $rate, [], $key)->assertCreated();

    expect(Order::count())->toBe(1)->and($second->json('data.order.order_number'))->toBe($first->json('data.order.order_number'));
});

test('checkout input is validated: key must be a UUID, total must match, rate and address must be ours', function (string $case, int $status, string $code) {
    $this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->variant->id, 'qty' => 1]);
    $rate = bbQuote($this);
    $stranger = Address::factory()->create()->id;

    $response = match ($case) {
        'key not uuid' => bbCheckout($this, $rate, [], 'bukan-uuid'),
        'wrong total' => bbCheckout($this, $rate, ['expected_grand_total' => 1]),
        'fake rate' => bbCheckout($this, $rate, ['rate_id' => 'rt_palsu123']),
        'unknown address' => bbCheckout($this, $rate, ['address_id' => 999999]),
        'foreign address' => bbCheckout($this, $rate, ['address_id' => $stranger]),
        'no key' => $this->actingAs($this->user)->postJson('/api/v1/checkout', ['address_id' => $this->address->id, 'rate_id' => $rate['rate_id'], 'expected_grand_total' => 1]),
        'note too long' => bbCheckout($this, $rate, ['note' => str_repeat('a', 256)]),
    };

    assertApiError($response, $status, $code);
    expect(Order::count())->toBe(0)->and($this->user->cartItems()->count())->toBe(1);
})->with([
    ['key not uuid', 422, 'VALIDATION_ERROR'], ['wrong total', 409, 'PRICE_CHANGED'], ['fake rate', 409, 'RATE_EXPIRED'],
    ['unknown address', 404, 'ADDRESS_NOT_FOUND'], ['foreign address', 404, 'ADDRESS_NOT_FOUND'], ['no key', 422, 'VALIDATION_ERROR'], ['note too long', 422, 'VALIDATION_ERROR'],
]);

test('checkout is rate limited per user (429 after 5 attempts a minute)', function () {
    $statuses = collect(range(1, 7))->map(fn () => $this->actingAs($this->user)->postJson('/api/v1/checkout', [], ['Idempotency-Key' => (string) Str::uuid()])->getStatusCode());

    expect($statuses->first())->toBe(422)->and($statuses->last())->toBe(429);
});

test('a rate quoted for one user cannot be used by another (no cross-account price reuse)', function () {
    $this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->variant->id, 'qty' => 1]);
    $rate = bbQuote($this);

    $thief = bbCustomer(['phone' => '081200000000']);
    $thief->cartItems()->create(['product_variant_id' => $this->variant->id, 'qty' => 1]);
    $theirAddress = Address::factory()->for($thief)->create();

    $this->actingAs($thief)->postJson('/api/v1/checkout', ['address_id' => $theirAddress->id, 'rate_id' => $rate['rate_id'], 'expected_grand_total' => 1], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409);
    expect(Order::count())->toBe(0);
});

test('an empty cart, a deactivated item, and a stock shortage each stop checkout with the documented code', function () {
    assertApiError($this->actingAs($this->user)->postJson('/api/v1/checkout', ['address_id' => $this->address->id, 'rate_id' => 'rt_x', 'expected_grand_total' => 1], ['Idempotency-Key' => (string) Str::uuid()]), 422, 'CART_EMPTY');

    $this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->variant->id, 'qty' => 2]);
    $rate = bbQuote($this);
    $this->jubelioStock = 1; // Jubelio, not our cache, is the authority at checkout
    assertApiError(bbCheckout($this, $rate), 409, 'OUT_OF_STOCK');

    $this->variant->update(['is_active' => false]);
    assertApiError(bbCheckout($this, $rate), 409, 'CART_HAS_ISSUES');
    expect(Order::count())->toBe(0);
});

test('the last unit goes to one buyer: the second checkout is refused', function () {
    $this->variant->update(['available_qty' => 1]);
    $this->jubelioStock = 1;
    $this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->variant->id, 'qty' => 1]);
    $rate = bbQuote($this);
    bbCheckout($this, $rate)->assertCreated();

    // Jubelio now reports the unit as reserved by the first sales order.
    $this->jubelioStock = 0;
    $second = bbCustomer(['phone' => '081200000001']);
    $address = Address::factory()->for($second)->default()->create();
    $second->cartItems()->create(['product_variant_id' => $this->variant->id, 'qty' => 1]);

    $this->actingAs($second)->postJson('/api/v1/checkout', ['address_id' => $address->id, 'rate_id' => 'rt_x', 'expected_grand_total' => 1], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409);
    expect(Order::count())->toBe(1);
});

test('cancelling an unpaid order frees it: status cancelled, Jubelio told, second cancel refused', function () {
    $this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->variant->id, 'qty' => 1]);
    $number = bbCheckout($this)->assertCreated()->json('data.order.order_number');
    bbDana([new Response(200, ['Content-Type' => 'application/json'], fixtureContent('gapura/cancel-order-success.json'))]);

    $this->actingAs($this->user)->postJson("/api/v1/orders/{$number}/cancel", ['reason' => 'Salah pilih'])->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect(Order::where('order_number', $number)->first()->status)->toBe(OrderStatus::Cancelled)->and($this->cancelled)->toBe(1);
    expect($this->actingAs($this->user)->postJson("/api/v1/orders/{$number}/cancel")->getStatusCode())->toBeIn([409, 422]);
    assertApiError($this->actingAs($this->user)->getJson("/api/v1/orders/{$number}/payment"), 409, 'ORDER_NOT_PAYABLE');
});

test('another customer can never see, pay or cancel my order (404)', function () {
    $this->actingAs($this->user)->postJson('/api/v1/cart/items', ['variant_id' => $this->variant->id, 'qty' => 1]);
    $number = bbCheckout($this)->assertCreated()->json('data.order.order_number');
    $other = bbCustomer();

    assertApiError($this->actingAs($other)->getJson("/api/v1/orders/{$number}"), 404, 'ORDER_NOT_FOUND');
    assertApiError($this->actingAs($other)->getJson("/api/v1/orders/{$number}/payment"), 404, 'ORDER_NOT_FOUND');
    assertApiError($this->actingAs($other)->postJson("/api/v1/orders/{$number}/cancel"), 404, 'ORDER_NOT_FOUND');
    expect($this->actingAs($other)->getJson('/api/v1/orders')->json('data'))->toBe([]);
});

test('order list filters: unknown status is a validation error; per_page is capped', function () {
    assertApiError($this->actingAs($this->user)->getJson('/api/v1/orders?status=teleported'), 422, 'VALIDATION_ERROR');
    assertApiError($this->actingAs($this->user)->getJson('/api/v1/orders?per_page=49'), 422, 'VALIDATION_ERROR');
    assertApiError($this->actingAs($this->user)->getJson('/api/v1/orders?page=0'), 422, 'VALIDATION_ERROR');
    $this->actingAs($this->user)->getJson('/api/v1/orders?status=paid,cancelled&per_page=48')->assertOk();
});

test('every customer order endpoint needs a login', function (string $method, string $uri) {
    assertApiError($this->json($method, $uri), 401, 'UNAUTHENTICATED');
})->with([['GET', '/api/v1/orders'], ['GET', '/api/v1/orders/WEB-000001'], ['GET', '/api/v1/orders/WEB-000001/payment'], ['POST', '/api/v1/orders/WEB-000001/cancel'],
    ['POST', '/api/v1/checkout'], ['POST', '/api/v1/shipping/rates']]);
