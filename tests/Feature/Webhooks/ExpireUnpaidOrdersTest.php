<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\Jubelio\CancelSalesOrderJob;
use App\Jobs\Jubelio\MarkSalesOrderPaidJob;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Gapura\GapuraClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/*
| orders:expire-unpaid (docs/api/webhooks.md WH-01, backup job): asks DANA about orders past their deadline and
| applies the answer with the same rules as the webhook. DANA is a Guzzle mock handler.
*/

beforeEach(function () {
    Queue::fake();
    Mail::fake();
    config([
        'gapura.env' => 'sandbox', 'gapura.client_id' => 'client-123', 'gapura.merchant_id' => 'merchant-456',
        'gapura.private_key_path' => gapuraKeys('merchant')['private'], 'gapura.mcc' => '5940', 'gapura.origin' => 'https://toko.test',
    ]);
});

function overdueOrder(string $number, int $total = 6935000, array $attributes = []): Order
{
    $order = Order::factory()->create($attributes + ['order_number' => $number, 'grand_total' => $total, 'payment_expires_at' => now()->subMinutes(10)]);
    Payment::create([
        'order_id' => $order->id, 'gapura_reference_no' => 'REF-'.$number, 'partner_reference_no' => $number, 'checkout_url' => 'https://pay.test',
        'amount' => $total, 'status' => PaymentStatus::Pending, 'expires_at' => $order->payment_expires_at,
    ]);

    return $order;
}

/** DANA's Query Payment answer for an order. */
function danaSays(string $status, int $amount = 6935000, string $number = 'WEB-000123'): Response
{
    $body = ['responseCode' => '2005500', 'responseMessage' => 'Successful', 'originalPartnerReferenceNo' => $number, 'originalReferenceNo' => 'REF-'.$number,
        'serviceCode' => '54', 'latestTransactionStatus' => $status, 'transactionStatusDesc' => 'x', 'amount' => ['value' => number_format($amount, 2, '.', ''), 'currency' => 'IDR']];

    if ($status === '00') {
        $body['paidTime'] = '2026-10-20T10:42:10+07:00';
        $body['additionalInfo'] = ['paymentViews' => [['payOptionInfos' => [['payMethod' => 'NETWORK_PAY', 'payOption' => 'NETWORK_PAY_PG_OVO']]]]];
    }

    return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
}

function useDana(array $responses): void
{
    app()->instance(GapuraClient::class, new GapuraClient(config('gapura'), new Client(['handler' => HandlerStack::create(new MockHandler($responses))])));
}

test('an overdue order DANA says is paid becomes paid', function () {
    $order = overdueOrder('WEB-000123');
    useDana([danaSays('00')]);

    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->payment->fresh())->status->toBe(PaymentStatus::Paid)->payment_method->toBe('NETWORK_PAY')->payment_channel->toBe('NETWORK_PAY_PG_OVO');
    Queue::assertPushed(MarkSalesOrderPaidJob::class, 1);
});

test('an overdue order DANA says is closed expires and gives its stock back', function () {
    $order = overdueOrder('WEB-000123');
    useDana([danaSays('05')]);

    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($order->fresh())->status->toBe(OrderStatus::Expired)->cancel_reason->toBe('Batas waktu pembayaran habis');
    Queue::assertPushed(CancelSalesOrderJob::class, 1);
});

test('an overdue order DANA still shows as waiting is left for the next round', function () {
    $order = overdueOrder('WEB-000123');
    useDana([danaSays('01')]);

    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
    Queue::assertNothingPushed();
});

test('orders within their deadline, and orders no longer waiting, are not asked about', function () {
    Order::factory()->create(['order_number' => 'WEB-000201', 'payment_expires_at' => now()->addHour()]);
    overdueOrder('WEB-000202', attributes: ['status' => OrderStatus::Paid]);
    overdueOrder('WEB-000203', attributes: ['status' => OrderStatus::Cancelled]);
    useDana([]);

    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('a DANA failure for one order does not stop the others', function () {
    $first = overdueOrder('WEB-000301');
    $second = overdueOrder('WEB-000302');
    useDana([new Response(500, [], '{"responseCode":"5005501","responseMessage":"Internal Server Error"}'), danaSays('05', number: 'WEB-000302')]);

    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($first->fresh()->status)->toBe(OrderStatus::PendingPayment)->and($second->fresh()->status)->toBe(OrderStatus::Expired);
});

test('a paid answer with a different amount changes nothing', function () {
    $order = overdueOrder('WEB-000123');
    useDana([danaSays('00', amount: 1000)]);

    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

test('the job runs every five minutes without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'orders:expire-unpaid'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('*/5 * * * *')->and($event->withoutOverlapping)->toBeTrue();
});
