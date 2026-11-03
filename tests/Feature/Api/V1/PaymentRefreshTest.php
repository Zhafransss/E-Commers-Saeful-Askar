<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\Jubelio\MarkSalesOrderPaidJob;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Gapura\GapuraClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/*
| ORD-06 (docs/api/checkout-orders.md): the customer asks DANA about an order that still waits for payment,
| with the same rules as the webhook. DANA is a Guzzle mock handler behind the SDK.
*/

beforeEach(function () {
    Queue::fake();
    Mail::fake();
    config([
        'gapura.env' => 'sandbox', 'gapura.client_id' => 'client-123', 'gapura.merchant_id' => 'merchant-456',
        'gapura.private_key_path' => gapuraKeys('merchant')['private'], 'gapura.mcc' => '5940', 'gapura.origin' => 'https://toko.test',
    ]);

    $this->customer = User::factory()->create();
});

/** DANA's answer to Query Payment (INT-GP-02). */
function danaQueryReply(string $status, string $number, int $amount = 6935000): Response
{
    $body = ['responseCode' => '2005500', 'responseMessage' => 'Successful', 'originalPartnerReferenceNo' => $number, 'originalReferenceNo' => 'REF-'.$number,
        'serviceCode' => '54', 'latestTransactionStatus' => $status, 'transactionStatusDesc' => 'x', 'amount' => ['value' => number_format($amount, 2, '.', ''), 'currency' => 'IDR']];

    if ($status === '00') {
        $body['paidTime'] = '2026-11-03T16:40:00+07:00';
    }

    return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
}

/**
 * @param  list<Response>  $responses
 */
function fakeDanaQuery(array $responses): ArrayObject
{
    $sent = new ArrayObject;
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($sent));
    app()->instance(GapuraClient::class, new GapuraClient(config('gapura'), new Client(['handler' => $stack])));

    return $sent;
}

function pendingCustomerOrder(User $user, string $number, array $attributes = []): Order
{
    $order = Order::factory()->for($user)->create($attributes + ['order_number' => $number, 'grand_total' => 6935000, 'payment_expires_at' => now()->addDay()]);
    Payment::create([
        'order_id' => $order->id, 'gapura_reference_no' => 'REF-'.$number, 'partner_reference_no' => $number, 'checkout_url' => 'https://pay.test',
        'amount' => 6935000, 'status' => PaymentStatus::Pending, 'expires_at' => $order->payment_expires_at,
    ]);

    return $order;
}

test('ORD-06 marks the order paid when DANA has the payment', function () {
    $order = pendingCustomerOrder($this->customer, 'WS-000030');
    fakeDanaQuery([danaQueryReply('00', 'WS-000030')]);

    $this->actingAs($this->customer)->postJson('/api/v1/orders/WS-000030/refresh-payment')
        ->assertOk()
        ->assertJsonPath('data.order_number', 'WS-000030')
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('message', 'Pembayaran sudah diterima. Terima kasih!');

    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
    Queue::assertPushed(MarkSalesOrderPaidJob::class, 1);
});

test('ORD-06 leaves a still unpaid order waiting and says so', function () {
    $order = pendingCustomerOrder($this->customer, 'WS-000031');
    fakeDanaQuery([danaQueryReply('01', 'WS-000031')]);

    $this->actingAs($this->customer)->postJson('/api/v1/orders/WS-000031/refresh-payment')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('message', 'DANA belum mencatat pembayaran untuk pesanan ini. Bila Anda sudah membayar, tunggu beberapa menit lalu periksa lagi, atau hubungi admin.');

    expect($order->refresh()->status)->toBe(OrderStatus::PendingPayment);
});

test('ORD-06 does not apply a payment of the wrong amount', function () {
    $order = pendingCustomerOrder($this->customer, 'WS-000032');
    fakeDanaQuery([danaQueryReply('00', 'WS-000032', 1000)]);

    $this->actingAs($this->customer)->postJson('/api/v1/orders/WS-000032/refresh-payment')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('message', 'Jumlah yang dibayar berbeda dengan tagihan, jadi pembayaran perlu dicek admin. Silakan hubungi admin.');

    expect($order->refresh()->status)->toBe(OrderStatus::PendingPayment);
});

test('ORD-06 answers an order that no longer waits without asking DANA', function () {
    pendingCustomerOrder($this->customer, 'WS-000033', ['status' => OrderStatus::Paid]);
    $sent = fakeDanaQuery([]);

    $this->actingAs($this->customer)->postJson('/api/v1/orders/WS-000033/refresh-payment')
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('message', 'Pembayaran sudah diterima. Terima kasih!');

    expect($sent)->toHaveCount(0);
});

test('ORD-06 answers 502 when DANA cannot be reached, and changes nothing', function () {
    $order = pendingCustomerOrder($this->customer, 'WS-000034');
    fakeDanaQuery(array_fill(0, 3, new Response(500, [], '{"responseCode":"5005501","responseMessage":"Internal Server Error"}')));

    assertApiError($this->actingAs($this->customer)->postJson('/api/v1/orders/WS-000034/refresh-payment'), 502, 'INTEGRATION_ERROR');

    expect($order->refresh()->status)->toBe(OrderStatus::PendingPayment);
});

test('ORD-06 is only for the order owner', function () {
    pendingCustomerOrder(User::factory()->create(), 'WS-000035');
    fakeDanaQuery([]);

    assertApiError($this->postJson('/api/v1/orders/WS-000035/refresh-payment'), 401, 'UNAUTHENTICATED');
    assertApiError($this->actingAs($this->customer)->postJson('/api/v1/orders/WS-000035/refresh-payment'), 404, 'ORDER_NOT_FOUND');
});

test('ORD-06 is limited to 6 checks a minute', function () {
    pendingCustomerOrder($this->customer, 'WS-000036');
    fakeDanaQuery(array_map(fn () => danaQueryReply('01', 'WS-000036'), range(1, 6)));

    foreach (range(1, 6) as $i) {
        $this->actingAs($this->customer)->postJson('/api/v1/orders/WS-000036/refresh-payment')->assertOk();
    }

    assertApiError($this->actingAs($this->customer)->postJson('/api/v1/orders/WS-000036/refresh-payment'), 429, 'TOO_MANY_REQUESTS');
});

test('the finish page and the order page offer the WhatsApp number', function (string $uri) {
    $this->withoutVite();
    config(['app.api_mock' => false, 'shop.whatsapp' => '6281111111111']);

    $this->actingAs($this->customer)->get($uri)->assertOk()->assertSee('6281111111111', false);
})->with(['/checkout/finish?order=WS-000030', '/account/orders/WS-000030']);
