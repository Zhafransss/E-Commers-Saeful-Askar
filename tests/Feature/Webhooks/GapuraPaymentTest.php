<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\WebhookEventStatus;
use App\Enums\WebhookProvider;
use App\Jobs\Jubelio\CancelSalesOrderJob;
use App\Jobs\Jubelio\MarkSalesOrderPaidJob;
use App\Jobs\ProcessGapuraWebhookJob;
use App\Mail\OrderCancelledMail;
use App\Mail\PaymentReceivedMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Gapura\GapuraClient;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/*
| WH-01 (docs/api/webhooks.md): what a Gapura notification does to an order, through the real endpoint
| (signature → stored event) and through the handler. DANA's answers are built here, signed with a
| throwaway key pair (tests/Pest.php gapuraKeys()).
*/

beforeEach(function () {
    Queue::fake();
    Mail::fake();
    config(['gapura.env' => 'production', 'gapura.merchant_id' => '216620000000000000000', 'gapura.dana_public_key_path' => gapuraKeys('dana')['public']]);
    app()->instance(GapuraClient::class, new GapuraClient(config('gapura')));

    $this->order = Order::factory()->create(['order_number' => 'WEB-000123', 'grand_total' => 6935000]);
    $this->payment = Payment::create([
        'order_id' => $this->order->id, 'gapura_reference_no' => '20261020111212800110166000000000001', 'partner_reference_no' => 'WEB-000123',
        'checkout_url' => 'https://m.sandbox.dana.id/pay', 'amount' => 6935000, 'status' => PaymentStatus::Pending, 'expires_at' => now()->addHour(),
    ]);
});

/** A Finish Notify body for the order, as DANA sends it. */
function notification(string $status, array $override = []): array
{
    $body = [
        'originalPartnerReferenceNo' => 'WEB-000123', 'originalReferenceNo' => '20261020111212800110166000000000001',
        'merchantId' => '216620000000000000000', 'amount' => ['value' => '6935000.00', 'currency' => 'IDR'],
        'latestTransactionStatus' => $status, 'transactionStatusDesc' => $status === '05' ? 'cancelled' : 'success',
        'createdTime' => '2026-10-20T10:30:00+07:00', 'finishedTime' => '2026-10-20T10:42:11+07:00',
    ];

    if ($status !== '05') {
        $body['additionalInfo'] = ['paymentInfo' => [
            'paidTime' => '2026-10-20T10:42:10+07:00',
            'payOptionInfos' => [['payMethod' => 'VIRTUAL_ACCOUNT', 'payOption' => 'VIRTUAL_ACCOUNT_BCA', 'payAmount' => ['value' => '6935000.00', 'currency' => 'IDR']]],
        ]];
    }

    return array_replace_recursive($body, $override);
}

/** Stores the notification the way the endpoint would and runs its job, returning the settled event. */
function process(array $payload): WebhookEvent
{
    $event = WebhookEvent::create([
        'provider' => WebhookProvider::Gapura, 'event_type' => 'notify', 'idempotency_key' => 'gapura:'.uniqid(),
        'signature_valid' => true, 'status' => WebhookEventStatus::Received, 'payload' => $payload, 'received_at' => now(),
    ]);

    (new ProcessGapuraWebhookJob($event->id))->handle();

    return $event->fresh();
}

// --- Paid (00 and 02) -----------------------------------------------------------------------

test('a paid notification marks the order and the payment paid and tells Jubelio and the customer', function () {
    $event = process(notification('00'));

    expect($event->status)->toBe(WebhookEventStatus::Processed);
    $this->order->refresh();
    $this->payment->refresh();
    expect($this->order)->status->toBe(OrderStatus::Paid)
        ->and($this->order->paid_at->toIso8601String())->toBe('2026-10-20T10:42:10+07:00')
        ->and($this->order->statusHistories->last())->status->toBe(OrderStatus::Paid)->note->toBe('Pembayaran diterima')
        ->and($this->payment)->status->toBe(PaymentStatus::Paid)->paid_amount->toBe(6935000)
        ->payment_method->toBe('VIRTUAL_ACCOUNT')->payment_channel->toBe('VIRTUAL_ACCOUNT_BCA');
    Queue::assertPushed(MarkSalesOrderPaidJob::class, fn (MarkSalesOrderPaidJob $job) => $job->orderId === $this->order->id);
    Mail::assertQueued(PaymentReceivedMail::class, fn (PaymentReceivedMail $mail) => $mail->hasTo($this->order->user->email));
});

test('status 02, paid but not final, counts as paid', function () {
    expect(process(notification('02'))->status)->toBe(WebhookEventStatus::Processed)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('a second paid notification for the same order changes nothing and sends nothing twice', function () {
    process(notification('00'));
    $second = process(notification('02'));

    expect($second)->status->toBe(WebhookEventStatus::Ignored)->error->toBe('Order sudah dibayar.')
        ->and($this->order->statusHistories()->count())->toBe(1);
    Queue::assertPushed(MarkSalesOrderPaidJob::class, 1);
    Mail::assertQueued(PaymentReceivedMail::class, 1);
});

test('a paid amount that is not the bill leaves the order untouched and fails the event', function () {
    $event = process(notification('00', ['amount' => ['value' => '1000.00']]));

    expect($event)->status->toBe(WebhookEventStatus::Failed)->error->toContain('berbeda dari tagihan')
        ->and($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
    Queue::assertNothingPushed();
    Mail::assertNothingQueued();
});

test('paying an order that already expired or was cancelled changes nothing and asks for manual handling', function (OrderStatus $status) {
    $this->order->forceFill(['status' => $status])->save();

    $event = process(notification('00'));

    expect($event)->status->toBe(WebhookEventStatus::Failed)->error->toBe('Dibayar setelah dibatalkan — perlu refund/proses manual.')
        ->and($this->order->fresh()->status)->toBe($status)
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);
    Queue::assertNothingPushed();
})->with([OrderStatus::Expired, OrderStatus::Cancelled]);

// --- Closed (05) ---------------------------------------------------------------------------

test('a closed notification expires the unpaid order, gives the stock back, and tells the customer', function () {
    $event = process(notification('05'));

    expect($event->status)->toBe(WebhookEventStatus::Processed);
    $this->order->refresh();
    expect($this->order)->status->toBe(OrderStatus::Expired)->cancel_reason->toBe('Batas waktu pembayaran habis')
        ->and($this->order->cancelled_at)->not->toBeNull()
        ->and($this->payment->fresh()->status)->toBe(PaymentStatus::Expired);
    Queue::assertPushed(CancelSalesOrderJob::class, fn (CancelSalesOrderJob $job) => $job->orderId === $this->order->id);
    Mail::assertQueued(OrderCancelledMail::class, 1);
});

test('a closed notification for an order the customer already cancelled is ignored', function () {
    $this->order->forceFill(['status' => OrderStatus::Cancelled, 'cancel_reason' => 'Dibatalkan oleh pelanggan'])->save();

    $event = process(notification('05'));

    expect($event)->status->toBe(WebhookEventStatus::Ignored)
        ->and($this->order->fresh())->status->toBe(OrderStatus::Cancelled)->cancel_reason->toBe('Dibatalkan oleh pelanggan');
    Queue::assertNothingPushed();
    Mail::assertNothingQueued();
});

test('a closed notification for an order that was paid is ignored, never undoing the payment', function () {
    process(notification('00'));
    $closed = process(notification('05'));

    expect($closed->status)->toBe(WebhookEventStatus::Ignored)->and($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

// --- Everything else -----------------------------------------------------------------------

test('a notification for an order the website does not know is ignored', function () {
    expect(process(notification('00', ['originalPartnerReferenceNo' => 'WEB-999999'])))
        ->status->toBe(WebhookEventStatus::Ignored)->error->toBe('Order WEB-999999 tidak dikenal.');
});

test('status 01, still waiting, changes nothing', function () {
    expect(process(notification('01'))->status)->toBe(WebhookEventStatus::Ignored)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

test('a notification for another merchant is refused', function () {
    $event = process(notification('00', ['merchantId' => '999']));

    expect($event)->status->toBe(WebhookEventStatus::Failed)->error->toContain('merchantId')
        ->and($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

// --- Through the real endpoint --------------------------------------------------------------

test('a signed Finish Notify posted to /v1.0/debit/notify ends with the order paid', function () {
    Queue::fake([MarkSalesOrderPaidJob::class, CancelSalesOrderJob::class]);
    config(['queue.default' => 'sync']);
    $body = json_encode(notification('00'), JSON_UNESCAPED_SLASHES);

    $response = $this->call('POST', '/v1.0/debit/notify', [], [], [], array_merge(
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        collect(gapuraNotifyHeaders($body))->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all(),
    ), $body);

    $response->assertOk()->assertExactJson(['responseCode' => '2005600', 'responseMessage' => 'Successful']);
    expect(WebhookEvent::sole())->status->toBe(WebhookEventStatus::Processed)->signature_valid->toBeTrue()
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('an unsigned notification posted to the endpoint changes nothing', function () {
    config(['queue.default' => 'sync']);

    $this->postJson('/v1.0/debit/notify', notification('00'))->assertStatus(401);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});
