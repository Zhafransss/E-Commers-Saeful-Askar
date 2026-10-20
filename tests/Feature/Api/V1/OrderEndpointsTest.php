<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\Jubelio\CancelSalesOrderJob;
use App\Mail\OrderCancelledMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Gapura\GapuraClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/*
| ORD-02..05 (docs/api/checkout-orders.md): history, detail, continue paying, cancel.
*/

beforeEach(function () {
    Queue::fake();
    Mail::fake();
    config([
        'gapura.env' => 'sandbox', 'gapura.client_id' => 'c', 'gapura.merchant_id' => 'm', 'gapura.mcc' => '5940',
        'gapura.private_key_path' => gapuraKeys('merchant')['private'], 'gapura.origin' => 'https://toko.test',
        'jubelio.base_url' => 'https://api2.jubelio.com', 'jubelio.email' => 'a@b.c', 'jubelio.password' => 'x',
    ]);
    $this->user = User::factory()->create();
});

function placed(User $user, string $number, array $attributes = [], PaymentStatus $payment = PaymentStatus::Pending): Order
{
    $order = Order::factory()->for($user)->create($attributes + ['order_number' => $number, 'grand_total' => 1000000, 'payment_expires_at' => now()->addHour(), 'jubelio_salesorder_id' => (int) substr($number, 4)]);
    $order->items()->create(['jubelio_item_id' => 1, 'sku' => 'SKU-1', 'product_name' => 'Aki', 'unit' => 'Unit', 'price' => 1000000, 'qty' => 1, 'weight_gram' => 1000, 'subtotal' => 1000000]);
    Payment::create(['order_id' => $order->id, 'gapura_reference_no' => 'R'.$number, 'partner_reference_no' => $number, 'checkout_url' => 'https://pay.test/'.$number,
        'amount' => 1000000, 'status' => $payment, 'expires_at' => $order->payment_expires_at]);

    return $order;
}

function dana(array $responses): void
{
    app()->instance(GapuraClient::class, new GapuraClient(config('gapura'), new Client(['handler' => HandlerStack::create(new MockHandler($responses))])));
}

test('ORD-02 lists only my orders, newest first, filtered by status, without failed checkouts', function () {
    placed($this->user, 'WEB-000001', ['status' => OrderStatus::Paid]);
    placed($this->user, 'WEB-000002');
    placed($this->user, 'WEB-000003', ['status' => OrderStatus::Cancelled, 'is_checkout_failed' => true]);
    placed(User::factory()->create(), 'WEB-000004');

    $all = $this->actingAs($this->user)->getJson('/api/v1/orders')->assertOk();
    expect(collect($all->json('data'))->pluck('order_number')->all())->toBe(['WEB-000002', 'WEB-000001'])
        ->and($all->json('meta'))->toMatchArray(['current_page' => 1, 'per_page' => 10, 'total' => 2]);

    $paid = $this->actingAs($this->user)->getJson('/api/v1/orders?status=paid,processing')->assertOk();
    expect(collect($paid->json('data'))->pluck('order_number')->all())->toBe(['WEB-000001']);

    assertApiError($this->actingAs($this->user)->getJson('/api/v1/orders?status=dikirim'), 422, 'VALIDATION_ERROR')->assertJsonValidationErrors('status');
});

test('ORD-02 and ORD-03 need a login', function () {
    assertApiError($this->getJson('/api/v1/orders'), 401, 'UNAUTHENTICATED');
    assertApiError($this->getJson('/api/v1/orders/WEB-000001'), 401, 'UNAUTHENTICATED');
});

test('ORD-03 shows my order and hides anyone else\'s', function () {
    placed($this->user, 'WEB-000010');
    placed(User::factory()->create(), 'WEB-000011');

    $this->actingAs($this->user)->getJson('/api/v1/orders/WEB-000010')->assertOk()->assertJsonPath('data.order_number', 'WEB-000010');
    assertApiError($this->actingAs($this->user)->getJson('/api/v1/orders/WEB-000011'), 404, 'ORDER_NOT_FOUND');
    assertApiError($this->actingAs($this->user)->getJson('/api/v1/orders/WEB-999999'), 404, 'ORDER_NOT_FOUND');
});

test('ORD-04 gives the payment link of an order still waiting', function () {
    placed($this->user, 'WEB-000020');

    $this->actingAs($this->user)->getJson('/api/v1/orders/WEB-000020/payment')->assertOk()
        ->assertJsonPath('data.checkout_url', 'https://pay.test/WEB-000020')->assertJsonPath('data.amount', 1000000)
        ->assertJsonPath('data.status', 'PENDING');
});

test('ORD-04 refuses an order that is paid, cancelled, or expired', function (OrderStatus $status) {
    placed($this->user, 'WEB-000021', ['status' => $status]);

    assertApiError($this->actingAs($this->user)->getJson('/api/v1/orders/WEB-000021/payment'), 409, 'ORDER_NOT_PAYABLE')
        ->assertJsonPath('order_status', $status->value);
})->with([OrderStatus::Paid, OrderStatus::Cancelled, OrderStatus::Expired]);

test('ORD-04 on an order past its deadline asks DANA, and a payment that arrived late wins', function () {
    $order = placed($this->user, 'WEB-000022', ['payment_expires_at' => now()->subMinute()]);
    dana([new Response(200, [], json_encode(['responseCode' => '2005500', 'responseMessage' => 'Successful', 'originalPartnerReferenceNo' => 'WEB-000022',
        'originalReferenceNo' => 'R', 'serviceCode' => '54', 'latestTransactionStatus' => '00', 'transactionStatusDesc' => 'ok',
        'amount' => ['value' => '1000000.00', 'currency' => 'IDR'], 'paidTime' => '2026-10-20T10:00:00+07:00']))]);

    assertApiError($this->actingAs($this->user)->getJson('/api/v1/orders/WEB-000022/payment'), 409, 'ORDER_NOT_PAYABLE')->assertJsonPath('order_status', 'paid');
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('ORD-05 closes the DANA order, cancels the order and the sales order, and tells the customer', function () {
    $order = placed($this->user, 'WEB-000030');
    dana([new Response(200, [], json_encode(['responseCode' => '2005700', 'responseMessage' => 'Successful', 'originalPartnerReferenceNo' => 'WEB-000030', 'originalReferenceNo' => 'R']))]);
    \Illuminate\Support\Facades\Http::fake([
        '*/login' => \Illuminate\Support\Facades\Http::response(fixture('jubelio/login.json')),
        '*/sales/orders/cancel/' => \Illuminate\Support\Facades\Http::response(['status' => 'ok']),
    ]);

    $this->actingAs($this->user)->postJson('/api/v1/orders/WEB-000030/cancel', ['reason' => 'Ingin ganti warna'])->assertOk()
        ->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.can_pay', false);

    expect($order->fresh())->status->toBe(OrderStatus::Cancelled)->cancel_reason->toBe('Ingin ganti warna')
        ->and($order->payment->fresh()->status)->toBe(PaymentStatus::Expired);
    Mail::assertQueued(OrderCancelledMail::class, 1);
    Queue::assertNotPushed(CancelSalesOrderJob::class);
});

test('ORD-05 keeps the cancellation when Jubelio is down and retries through the queue', function () {
    $order = placed($this->user, 'WEB-000031');
    dana([new Response(200, [], json_encode(['responseCode' => '2005700', 'responseMessage' => 'Successful', 'originalPartnerReferenceNo' => 'WEB-000031', 'originalReferenceNo' => 'R']))]);
    \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['message' => 'boom'], 500)]);

    $this->actingAs($this->user)->postJson('/api/v1/orders/WEB-000031/cancel')->assertOk();

    expect($order->fresh())->status->toBe(OrderStatus::Cancelled)->cancel_reason->toBe('Dibatalkan oleh pelanggan');
    Queue::assertPushed(CancelSalesOrderJob::class, 1);
});

test('ORD-05 refuses a paid order, another customer\'s order, and a reason that is too long', function () {
    placed($this->user, 'WEB-000032', ['status' => OrderStatus::Paid]);
    placed(User::factory()->create(), 'WEB-000033');
    placed($this->user, 'WEB-000034');

    assertApiError($this->actingAs($this->user)->postJson('/api/v1/orders/WEB-000032/cancel'), 409, 'ORDER_NOT_CANCELLABLE');
    assertApiError($this->actingAs($this->user)->postJson('/api/v1/orders/WEB-000033/cancel'), 404, 'ORDER_NOT_FOUND');
    assertApiError($this->actingAs($this->user)->postJson('/api/v1/orders/WEB-000034/cancel', ['reason' => str_repeat('a', 256)]), 422, 'VALIDATION_ERROR');
});
