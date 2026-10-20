<?php

use App\Enums\JubelioSyncStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusSource;
use App\Enums\PaymentStatus;
use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Jobs\Jubelio\PushOrderToJubelioJob;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\Gapura\GapuraClient;
use App\Services\Shop\OrderStatusRecorder;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/*
| ADM-09 – ADM-12 (docs/api/admin.md): the admin's view of orders, resending to Jubelio, and re-checking payment.
*/

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->create(['name' => 'Siti Aminah', 'email' => 'siti@example.com', 'phone' => '081377788899']);
});

function adminOrder(User $user, string $number, array $attributes = [], bool $withPayment = true): Order
{
    $order = Order::factory()->for($user)->create($attributes + ['order_number' => $number, 'grand_total' => 3585000, 'payment_expires_at' => now()->addHour()]);
    $order->items()->create(['jubelio_item_id' => 1, 'sku' => 'BAT-48V', 'product_name' => 'Baterai Lithium 48V', 'unit' => 'Unit', 'price' => 3400000, 'qty' => 1, 'weight_gram' => 9500, 'subtotal' => 3400000]);
    $withPayment && Payment::create(['order_id' => $order->id, 'gapura_reference_no' => 'R'.$number, 'partner_reference_no' => $number, 'checkout_url' => 'https://pay.test',
        'amount' => 3585000, 'status' => PaymentStatus::Pending, 'expires_at' => now()->addHour()]);

    return $order;
}

// --- Access ----------------------------------------------------------------------------------

test('every admin order endpoint refuses guests and customers', function (string $method, string $uri) {
    assertApiError($this->json($method, $uri), 401, 'UNAUTHENTICATED');
    assertApiError($this->actingAs($this->customer)->json($method, $uri), 403, 'FORBIDDEN');
})->with([
    ['GET', '/api/v1/admin/orders'],
    ['GET', '/api/v1/admin/orders/WEB-000001'],
    ['POST', '/api/v1/admin/orders/WEB-000001/push-jubelio'],
    ['POST', '/api/v1/admin/orders/WEB-000001/refresh-payment'],
]);

// --- ADM-09 ----------------------------------------------------------------------------------

test('ADM-09 lists every order newest first, including a checkout that failed', function () {
    adminOrder($this->customer, 'WEB-000001');
    adminOrder($this->customer, 'WEB-000002', ['status' => OrderStatus::Cancelled, 'is_checkout_failed' => true, 'jubelio_sync_status' => JubelioSyncStatus::Failed]);
    adminOrder(User::factory()->create(), 'WEB-000003', ['status' => OrderStatus::Paid, 'jubelio_salesorder_no' => 'SO-1']);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/orders')->assertOk();

    expect(collect($response->json('data'))->pluck('order_number')->all())->toBe(['WEB-000003', 'WEB-000002', 'WEB-000001'])
        ->and($response->json('data.2'))->toMatchArray([
            'status' => 'pending_payment', 'status_label' => 'Menunggu pembayaran', 'grand_total' => 3585000, 'item_count' => 1,
            'payment_status' => 'PENDING', 'jubelio_sync_status' => 'pending', 'is_checkout_failed' => false,
            'customer' => ['id' => $this->customer->id, 'name' => 'Siti Aminah', 'email' => 'siti@example.com', 'phone' => '081377788899'],
        ])
        ->and($response->json('data.1'))->toMatchArray(['is_checkout_failed' => true, 'jubelio_sync_status' => 'failed'])
        ->and($response->json('meta'))->toMatchArray(['current_page' => 1, 'per_page' => 20, 'total' => 3]);
});

test('ADM-09 filters by status, sync status, search term, and dates', function () {
    adminOrder($this->customer, 'WEB-000001', ['status' => OrderStatus::Paid, 'created_at' => '2026-10-10 10:00:00']);
    adminOrder($this->customer, 'WEB-000002', ['jubelio_sync_status' => JubelioSyncStatus::Failed, 'created_at' => '2026-10-14 10:00:00', 'tracking_no' => 'JP123']);
    adminOrder(User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']), 'WEB-000003', ['created_at' => '2026-10-20 23:30:00']);

    $numbers = fn (string $query) => collect($this->actingAs($this->admin)->getJson("/api/v1/admin/orders?{$query}")->assertOk()->json('data'))->pluck('order_number')->all();

    expect($numbers('status=paid,expired'))->toBe(['WEB-000001'])
        ->and($numbers('jubelio_sync_status=failed'))->toBe(['WEB-000002'])
        ->and($numbers('q=siti'))->toBe(['WEB-000002', 'WEB-000001'])
        ->and($numbers('q=081377788899'))->toBe(['WEB-000002', 'WEB-000001'])
        ->and($numbers('q=WEB-000003'))->toBe(['WEB-000003'])
        ->and($numbers('q=JP123'))->toBe(['WEB-000002'])
        ->and($numbers('date_from=2026-10-14&date_to=2026-10-14'))->toBe(['WEB-000002'])
        ->and($numbers('date_to=2026-10-20'))->toBe(['WEB-000003', 'WEB-000002', 'WEB-000001'])
        ->and($numbers('per_page=2&page=2'))->toBe(['WEB-000001']);
});

test('ADM-09 rejects unknown filters', function (string $query, string $field) {
    assertApiError($this->actingAs($this->admin)->getJson("/api/v1/admin/orders?{$query}"), 422, 'VALIDATION_ERROR')->assertJsonValidationErrors($field);
})->with([
    ['status=dikirim', 'status'],
    ['jubelio_sync_status=oke', 'jubelio_sync_status'],
    ['date_from=14-10-2026', 'date_from'],
    ['date_from=2026-10-14&date_to=2026-10-01', 'date_to'],
    ['per_page=500', 'per_page'],
]);

// --- ADM-10 ----------------------------------------------------------------------------------

test('ADM-10 shows any customer\'s order with the customer, sources, and integration details', function () {
    $order = adminOrder($this->customer, 'WEB-000124', ['jubelio_sync_status' => JubelioSyncStatus::Failed, 'jubelio_last_error' => 'SQLSTATE 23505']);
    app(OrderStatusRecorder::class)->start($order, OrderStatusSource::System);
    SyncLog::start(SyncType::OrderPush, SyncTrigger::Checkout, $order->id)->markFailed('SQLSTATE 23505');
    SyncLog::start(SyncType::OrderPush, SyncTrigger::Checkout, 999999)->markFailed('lain');
    SyncLog::start(SyncType::Products, SyncTrigger::Schedule, $order->id)->markSucceeded(1);

    $data = $this->actingAs($this->admin)->getJson('/api/v1/admin/orders/WEB-000124')->assertOk()->json('data');

    expect($data)->toMatchArray(['order_number' => 'WEB-000124', 'status' => 'pending_payment', 'can_cancel' => true])
        ->and($data['customer'])->toMatchArray(['id' => $this->customer->id, 'email' => 'siti@example.com'])
        ->and($data['items'][0])->toHaveKeys(['id', 'variant_id', 'sku', 'product_name', 'price', 'qty'])
        ->and($data['status_history'][0])->toMatchArray(['status' => 'pending_payment', 'source' => 'system'])
        ->and($data['integration'])->toMatchArray([
            'jubelio_sync_status' => 'failed', 'jubelio_last_error' => 'SQLSTATE 23505',
            'gapura_reference_no' => 'RWEB-000124', 'partner_reference_no' => 'WEB-000124',
        ])
        ->and($data['integration']['sync_logs'])->toHaveCount(1)
        ->and($data['integration']['sync_logs'][0])->toMatchArray(['type' => 'order_push', 'status' => 'failed', 'message' => 'SQLSTATE 23505']);
});

test('ADM-10 never shows credentials and answers 404 for an unknown order', function () {
    adminOrder($this->customer, 'WEB-000124');

    $body = $this->actingAs($this->admin)->get('/api/v1/admin/orders/WEB-000124', ['Accept' => 'application/json'])->assertOk()->getContent();
    expect($body)->not->toContain('password')->not->toContain('token')->not->toContain('private_key');

    assertApiError($this->actingAs($this->admin)->getJson('/api/v1/admin/orders/WEB-999999'), 404, 'ORDER_NOT_FOUND');
});

// --- ADM-11 ----------------------------------------------------------------------------------

function push($test, string $number, array $body = [])
{
    return $test->actingAs($test->admin)->postJson("/api/v1/admin/orders/{$number}/push-jubelio", $body);
}

test('ADM-11 chooses create for an order without a sales order, queues it, and names the sync log', function () {
    Queue::fake();
    $order = adminOrder($this->customer, 'WEB-000124', ['jubelio_sync_status' => JubelioSyncStatus::Failed]);

    $response = push($this, 'WEB-000124')->assertStatus(202);

    $log = SyncLog::sole();
    expect($response->json('data'))->toBe(['order_number' => 'WEB-000124', 'action' => 'create', 'sync_log_id' => $log->id, 'jubelio_sync_status' => 'pending'])
        ->and($response->json('message'))->toBe('Pesanan dikirim ulang ke Jubelio. Periksa status beberapa saat lagi.')
        ->and($log)->type->toBe(SyncType::OrderPush)->status->toBe(SyncStatus::Running)->trigger->toBe(SyncTrigger::Manual)->reference_id->toBe($order->id)
        ->and($order->fresh()->jubelio_sync_status)->toBe(JubelioSyncStatus::Pending);
    Queue::assertPushed(PushOrderToJubelioJob::class, fn ($job) => $job->orderId === $order->id && $job->action === 'create' && $job->syncLogId === $log->id);
});

test('ADM-11 chooses mark_paid for a paid order never marked paid, and cancel for a closed one never cancelled', function () {
    Queue::fake();
    adminOrder($this->customer, 'WEB-000001', ['status' => OrderStatus::Paid, 'jubelio_salesorder_id' => 11]);
    adminOrder($this->customer, 'WEB-000002', ['status' => OrderStatus::Expired, 'jubelio_salesorder_id' => 12]);

    expect(push($this, 'WEB-000001')->json('data.action'))->toBe('mark_paid')
        ->and(push($this, 'WEB-000002')->json('data.action'))->toBe('cancel');
});

test('ADM-11 says ALREADY_SYNCED when Jubelio already reflects the order', function (array $attributes, ?SyncType $done, array $body) {
    Queue::fake();
    $order = adminOrder($this->customer, 'WEB-000001', $attributes);
    $done && SyncLog::start($done, SyncTrigger::Webhook, $order->id)->markSucceeded(1);

    assertApiError(push($this, 'WEB-000001', $body), 409, 'ALREADY_SYNCED');
    Queue::assertNothingPushed();
})->with([
    'has a sales order, still unpaid' => [['jubelio_salesorder_id' => 11, 'jubelio_salesorder_no' => 'SO-1'], null, []],
    'create again' => [['jubelio_salesorder_id' => 11, 'jubelio_salesorder_no' => 'SO-1'], null, ['action' => 'create']],
    'paid and marked paid' => [['status' => OrderStatus::Paid, 'jubelio_salesorder_id' => 11], SyncType::OrderPaid, []],
    'cancelled and cancelled in Jubelio' => [['status' => OrderStatus::Cancelled, 'jubelio_salesorder_id' => 11], SyncType::OrderCancel, ['action' => 'cancel']],
]);

test('ADM-11 refuses an action that does not fit the order', function (array $attributes, array $body) {
    Queue::fake();
    adminOrder($this->customer, 'WEB-000001', $attributes);

    assertApiError(push($this, 'WEB-000001', $body), 422, 'VALIDATION_ERROR')->assertJsonValidationErrors('action');
    Queue::assertNothingPushed();
})->with([
    'unknown action' => [[], ['action' => 'delete']],
    'mark_paid before payment' => [['jubelio_salesorder_id' => 11], ['action' => 'mark_paid']],
    'mark_paid without a sales order' => [['status' => OrderStatus::Paid], ['action' => 'mark_paid']],
    'cancel an order still waiting' => [['jubelio_salesorder_id' => 11], ['action' => 'cancel']],
    'cancel without a sales order' => [['status' => OrderStatus::Expired], ['action' => 'cancel']],
    'create for a failed checkout' => [['status' => OrderStatus::Cancelled, 'is_checkout_failed' => true], ['action' => 'create']],
]);

test('ADM-11 answers 404 for an unknown order', function () {
    assertApiError(push($this, 'WEB-999999'), 404, 'ORDER_NOT_FOUND');
});

test('the resend job creates the sales order once: a sales order Jubelio already has is found by ref_no, not made again', function () {
    config(['jubelio.base_url' => 'https://api2.jubelio.com', 'jubelio.email' => 'a@b.c', 'jubelio.password' => 'x', 'jubelio.store_id' => '-102', 'jubelio.location_id' => -1, 'jubelio.tax_id' => 1]);
    $this->customer->forceFill(['jubelio_contact_id' => 5512])->save();
    $order = adminOrder($this->customer, 'WEB-000124', ['jubelio_sync_status' => JubelioSyncStatus::Pending]);
    $log = SyncLog::start(SyncType::OrderPush, SyncTrigger::Manual, $order->id);
    Http::fake([
        '*/login' => Http::response(fixture('jubelio/login.json')),
        '*/sales/v2/orders/*' => Http::response(['data' => [['salesorder_id' => 98112, 'salesorder_no' => 'SO-000981', 'ref_no' => 'WEB-000124']]]),
    ]);

    app()->call([new PushOrderToJubelioJob($order->id, 'create', $log->id), 'handle']);

    expect($order->fresh())->jubelio_salesorder_id->toBe(98112)->jubelio_salesorder_no->toBe('SO-000981')->jubelio_sync_status->toBe(JubelioSyncStatus::Synced)
        ->and($log->fresh()->status)->toBe(SyncStatus::Success);
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/sales/orders/'));
});

test('the resend job marks its log and the order failed when Jubelio keeps failing', function () {
    config(['jubelio.base_url' => 'https://api2.jubelio.com', 'jubelio.email' => 'a@b.c', 'jubelio.password' => 'x']);
    $order = adminOrder($this->customer, 'WEB-000001', ['status' => OrderStatus::Expired, 'jubelio_salesorder_id' => 12]);
    $log = SyncLog::start(SyncType::OrderCancel, SyncTrigger::Manual, $order->id);
    Http::fake(['*/login' => Http::response(fixture('jubelio/login.json')), '*' => Http::response(['message' => 'boom'], 500)]);
    $job = new PushOrderToJubelioJob($order->id, 'cancel', $log->id);

    try {
        app()->call([$job, 'handle']);
    } catch (Throwable $e) {
        $job->failed($e);
    }

    expect($log->fresh()->status)->toBe(SyncStatus::Failed)->and($order->fresh()->jubelio_sync_status)->toBe(JubelioSyncStatus::Failed);
});

// --- ADM-12 ----------------------------------------------------------------------------------

function danaAnswers(string $code, int $amount = 3585000, string $number = 'WEB-000124'): void
{
    config(['gapura.env' => 'sandbox', 'gapura.client_id' => 'c', 'gapura.merchant_id' => 'm', 'gapura.mcc' => '5940',
        'gapura.private_key_path' => gapuraKeys('merchant')['private'], 'gapura.origin' => 'https://toko.test']);
    $body = ['responseCode' => '2005500', 'responseMessage' => 'Successful', 'originalPartnerReferenceNo' => $number, 'originalReferenceNo' => 'R',
        'serviceCode' => '54', 'latestTransactionStatus' => $code, 'transactionStatusDesc' => 'x', 'amount' => ['value' => number_format($amount, 2, '.', ''), 'currency' => 'IDR']];
    $code === '00' && $body['paidTime'] = '2026-10-20T10:00:00+07:00';
    app()->instance(GapuraClient::class, new GapuraClient(config('gapura'), new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], json_encode($body))]))])));
}

function refresh($test, string $number = 'WEB-000124')
{
    return $test->actingAs($test->admin)->postJson("/api/v1/admin/orders/{$number}/refresh-payment");
}

test('ADM-12 finds a payment DANA says is made and marks the order paid', function () {
    Queue::fake();
    Mail::fake();
    adminOrder($this->customer, 'WEB-000124', ['jubelio_salesorder_id' => 11]);
    danaAnswers('00');

    $response = refresh($this)->assertOk();

    expect($response->json('data'))->toBe(['order_number' => 'WEB-000124', 'gapura_status' => '00', 'order_status_before' => 'pending_payment', 'order_status_after' => 'paid', 'changed' => true])
        ->and($response->json('message'))->toBe('Pembayaran ditemukan. Pesanan ditandai dibayar.')
        ->and(Order::first()->status)->toBe(OrderStatus::Paid);
});

test('ADM-12 expires an order DANA says is closed', function () {
    Queue::fake();
    Mail::fake();
    adminOrder($this->customer, 'WEB-000124');
    danaAnswers('05');

    refresh($this)->assertOk()->assertJsonPath('data.order_status_after', 'expired')->assertJsonPath('data.changed', true);
});

test('ADM-12 changes nothing when DANA still waits, and is safe to repeat', function () {
    adminOrder($this->customer, 'WEB-000124');
    danaAnswers('01');

    refresh($this)->assertOk()->assertJsonPath('data.changed', false)->assertJsonPath('data.gapura_status', '01')
        ->assertJsonPath('message', 'Status pembayaran belum berubah.');
    expect(Order::first()->status)->toBe(OrderStatus::PendingPayment);
});

test('ADM-12 reports a payment on an order that was already cancelled, and a wrong amount, without changing the order', function () {
    Queue::fake();
    adminOrder($this->customer, 'WEB-000124', ['status' => OrderStatus::Cancelled]);
    danaAnswers('00');
    refresh($this)->assertOk()->assertJsonPath('data.changed', false)->assertJsonPath('data.order_status_after', 'cancelled');

    adminOrder($this->customer, 'WEB-000125');
    danaAnswers('00', 1000, 'WEB-000125');
    refresh($this, 'WEB-000125')->assertOk()->assertJsonPath('data.changed', false)->assertJsonPath('data.order_status_after', 'pending_payment');
});

test('ADM-12 answers 502 when DANA does not answer, 404 for an unknown order, 409 for an order without payment', function () {
    adminOrder($this->customer, 'WEB-000124');
    config(['gapura.env' => 'sandbox', 'gapura.client_id' => 'c', 'gapura.merchant_id' => 'm', 'gapura.mcc' => '5940', 'gapura.private_key_path' => gapuraKeys('merchant')['private'], 'gapura.origin' => 'https://toko.test']);
    app()->instance(GapuraClient::class, new GapuraClient(config('gapura'), new Client(['handler' => HandlerStack::create(new MockHandler([new Response(500, [], '{}'), new Response(500, [], '{}')]))])));

    assertApiError(refresh($this), 502, 'INTEGRATION_ERROR');
    assertApiError(refresh($this, 'WEB-999999'), 404, 'ORDER_NOT_FOUND');

    adminOrder($this->customer, 'WEB-000130', withPayment: false);
    assertApiError(refresh($this, 'WEB-000130'), 409, 'ORDER_HAS_NO_PAYMENT');
});
