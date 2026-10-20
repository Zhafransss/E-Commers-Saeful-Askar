<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Gapura\GapuraClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/*
| Black-box: WH-01..04 (docs/api/webhooks.md). Only the public endpoints; signatures, replays, tampering.
*/

beforeEach(function () {
    Mail::fake();
    config([
        'gapura.env' => 'production', 'gapura.merchant_id' => '216620000000000000000', 'gapura.dana_public_key_path' => gapuraKeys('dana')['public'],
        'jubelio.webhook_secret' => 'rahasia-webhook',
        'jubelio.base_url' => 'https://api2.jubelio.com', 'jubelio.email' => 'a@b.c', 'jubelio.password' => 'x',
    ]);
    app()->instance(GapuraClient::class, new GapuraClient(config('gapura')));
    Http::fake(['*' => Http::response(['status' => 'ok', 'id' => 1], 200)]);

    $this->order = Order::factory()->create(['order_number' => 'WEB-000123', 'grand_total' => 6935000, 'payment_expires_at' => now()->addMinutes(10)]);
    $this->payment = Payment::create(['order_id' => $this->order->id, 'gapura_reference_no' => '20261020111212800110166000000000001', 'partner_reference_no' => 'WEB-000123',
        'checkout_url' => 'https://pay.test', 'amount' => 6935000, 'status' => PaymentStatus::Pending, 'expires_at' => now()->addMinutes(10)]);
});

function bbGapuraBody(array $override = []): string
{
    return json_encode(array_replace_recursive([
        'originalPartnerReferenceNo' => 'WEB-000123', 'originalReferenceNo' => '20261020111212800110166000000000001', 'merchantId' => '216620000000000000000',
        'amount' => ['value' => '6935000.00', 'currency' => 'IDR'], 'latestTransactionStatus' => '00', 'transactionStatusDesc' => 'success',
        'createdTime' => '2026-10-20T10:30:00+07:00', 'finishedTime' => '2026-10-20T10:42:11+07:00',
        'additionalInfo' => ['paymentInfo' => ['paidTime' => '2026-10-20T10:42:10+07:00', 'payOptionInfos' => [['payMethod' => 'VIRTUAL_ACCOUNT', 'payOption' => 'VIRTUAL_ACCOUNT_BCA', 'payAmount' => ['value' => '6935000.00', 'currency' => 'IDR']]]]],
    ], $override), JSON_UNESCAPED_SLASHES);
}

function bbGapuraPost($test, string $body, ?array $headers = null)
{
    $headers ??= gapuraNotifyHeaders($body);

    return $test->call('POST', '/v1.0/debit/notify', [], [], [], array_merge(
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        collect($headers)->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all(),
    ), $body);
}

test('WH-01 a genuine paid notification settles the order exactly once, even when DANA retries', function () {
    $body = bbGapuraBody();

    bbGapuraPost($this, $body)->assertOk()->assertJsonPath('responseCode', '2005600');
    bbGapuraPost($this, $body)->assertOk();
    bbGapuraPost($this, $body)->assertOk();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Paid)->and(WebhookEvent::count())->toBe(1)
        ->and($this->order->statusHistories()->where('status', 'paid')->count())->toBe(1);
});

test('WH-01 rejects forged requests with 401 and changes nothing', function (string $kind) {
    $body = bbGapuraBody();
    $headers = gapuraNotifyHeaders($body);

    $response = match ($kind) {
        'no signature' => bbGapuraPost($this, $body, []),
        'garbage signature' => bbGapuraPost($this, $body, ['X-TIMESTAMP' => $headers['X-TIMESTAMP'], 'X-SIGNATURE' => 'AAAA', 'X-PARTNER-ID' => 'dana']),
        'signature from another key' => bbGapuraPost($this, $body, gapuraNotifyHeaders($body, gapuraKeys('attacker')['private'])),
        'body tampered after signing' => bbGapuraPost($this, bbGapuraBody(['amount' => ['value' => '1.00']]), $headers),
        'status tampered after signing' => bbGapuraPost($this, bbGapuraBody(['latestTransactionStatus' => '05']), $headers),
        'timestamp replaced' => bbGapuraPost($this, $body, ['X-TIMESTAMP' => '2020-01-01T00:00:00+07:00'] + $headers),
    };

    $response->assertStatus(401);
    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment)->and($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
})->with(['no signature', 'garbage signature', 'signature from another key', 'body tampered after signing', 'status tampered after signing', 'timestamp replaced']);

test('WH-01 a paid notification for the wrong amount does not mark the order paid', function () {
    bbGapuraPost($this, bbGapuraBody(['amount' => ['value' => '1000.00']]));

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

test('WH-01 a notification for an unknown order is answered without a server error and creates nothing', function () {
    $body = bbGapuraBody(['originalPartnerReferenceNo' => 'WEB-999999']);

    expect(bbGapuraPost($this, $body)->getStatusCode())->toBeLessThan(500);
    expect(Order::count())->toBe(1);
});

test('WH-01 a malformed or empty body never causes a 5xx', function (string $body) {
    expect(bbGapuraPost($this, $body, gapuraNotifyHeaders('{}'))->getStatusCode())->toBeLessThan(500);
})->with(['', '{', 'null', '[]', '"text"', '{"a":']);

test('WH-01 DANA\'s expiry notice expires the order, and a paid order is not expired afterwards', function () {
    bbGapuraPost($this, bbGapuraBody(['latestTransactionStatus' => '05', 'additionalInfo' => null]))->assertOk();
    expect($this->order->fresh()->status)->toBe(OrderStatus::Expired);

    $paid = Order::factory()->create(['order_number' => 'WEB-000124', 'grand_total' => 100000]);
    Payment::create(['order_id' => $paid->id, 'gapura_reference_no' => 'R124', 'partner_reference_no' => 'WEB-000124', 'checkout_url' => 'x', 'amount' => 100000, 'status' => PaymentStatus::Paid, 'expires_at' => now()->addMinutes(5)]);
    $paid->forceFill(['status' => OrderStatus::Paid])->save();
    bbGapuraPost($this, bbGapuraBody(['originalPartnerReferenceNo' => 'WEB-000124', 'originalReferenceNo' => 'R124', 'latestTransactionStatus' => '05', 'amount' => ['value' => '100000.00'], 'additionalInfo' => null]));
    expect($paid->fresh()->status)->toBe(OrderStatus::Paid);
});

test('WH-02..04 Jubelio webhooks: no or wrong signature is 401; a signed delivery is accepted once', function (string $path, array $payload) {
    Queue::fake();
    $body = json_encode($payload);

    $this->call('POST', $path, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $body)->assertStatus(401);
    $this->call('POST', $path, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_SIGN' => 'salah'], $body)->assertStatus(401);

    $signed = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_SIGN' => jubelioSignature($body)];
    $this->call('POST', $path, [], [], [], $signed, $body)->assertOk();
    $this->call('POST', $path, [], [], [], $signed, $body)->assertOk();

    expect(WebhookEvent::where('signature_valid', true)->count())->toBe(1);
})->with([
    'product' => ['/webhooks/jubelio/product', ['item_group_id' => 18]],
    'price' => ['/webhooks/jubelio/price', ['item_group_id' => 18]],
    'stock' => ['/webhooks/jubelio/stock', ['item_ids' => [1, 2], 'location_id' => -1]],
]);

test('WH-02..04 a signed payload missing its required fields is 422, not 500', function (string $path) {
    $body = '{}';

    $response = $this->call('POST', $path, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_SIGN' => jubelioSignature($body)], $body);

    expect($response->getStatusCode())->toBe(422);
})->with(['/webhooks/jubelio/product', '/webhooks/jubelio/price', '/webhooks/jubelio/stock']);

test('webhook endpoints take POST only', function (string $uri) {
    $this->getJson($uri)->assertStatus(405);
})->with(['/v1.0/debit/notify', '/webhooks/jubelio/product', '/webhooks/jubelio/stock']);
