<?php

use App\Enums\OrderStatus;
use App\Enums\OrderStatusSource;
use App\Enums\WebhookEventStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Jubelio\SalesOrderProgress;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/*
| WH-05 update-salesorder (docs/api/webhooks.md), its scheduled backup orders:check-shipped, and ADM-29
| (docs/api/admin.md). A website order whose sales order the warehouse finished in Jubelio (mark-as-complete,
| invoice made) becomes shipped; the admin then marks it completed.
*/

beforeEach(function () {
    config(['jubelio.webhook_secret' => 'rahasia-webhook']);
});

/**
 * The sales order detail (INT-JB-13) as Jubelio returned it for WS-000027 after mark-as-complete, with overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function finishedSalesOrder(int $id, string $number, array $overrides = []): array
{
    return $overrides + [
        'salesorder_id' => $id,
        'salesorder_no' => $number,
        'ref_no' => $number,
        'store_id' => -101,
        'source' => 1,
        'is_paid' => true,
        'is_canceled' => null,
        'channel_status' => 'Completed',
        'internal_status' => 'COMPLETED',
        'wms_status' => 'COMPLETED',
        'marked_as_complete' => true,
        'completed_date' => '2026-11-03T08:19:37.684Z',
        'invoice_id' => 187421,
        'invoice_no' => 'INV-000187421',
        'tracking_no' => null,
        'shipper' => 'Wahana Express',
        'courier' => 'Wahana Express',
    ];
}

/**
 * @param  array<int, array<string, mixed>|null>  $salesOrders  salesorder_id => detail (null = 404)
 */
function fakeSalesOrders(array $salesOrders): void
{
    $routes = [];
    foreach ($salesOrders as $id => $detail) {
        $routes["/sales/orders/{$id}"] = $detail === null ? Http::response(['message' => 'Not Found'], 404) : Http::response($detail);
    }

    fakeJubelioCatalog($routes);
}

function paidWebsiteOrder(string $number, int $salesOrderId, array $attributes = []): Order
{
    return Order::factory()->create($attributes + [
        'order_number' => $number,
        'status' => OrderStatus::Paid,
        'paid_at' => now(),
        'jubelio_salesorder_id' => $salesOrderId,
        'jubelio_salesorder_no' => $number,
    ]);
}

/**
 * @param  array<string, mixed>  $body
 */
function deliverSalesOrder(array $body, ?string $signature = null): TestResponse
{
    $raw = json_encode($body);

    return test()->call('POST', '/webhooks/jubelio/salesorder', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_SIGN' => $signature ?? jubelioSignature($raw),
    ], $raw);
}

// --- WH-05 -----------------------------------------------------------------------------------------

test('WH-05 for a sales order finished in Jubelio marks the paid order shipped', function () {
    $order = paidWebsiteOrder('WS-000027', 193460);
    fakeSalesOrders([193460 => finishedSalesOrder(193460, 'WS-000027')]);

    deliverSalesOrder(['action' => 'update-salesorder', 'salesorder_id' => 193460, 'salesorder_no' => 'WS-000027', 'ref_no' => 'WS-000027'])
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);

    $order->refresh();
    $history = $order->statusHistories()->latest('id')->first();

    expect($order->status)->toBe(OrderStatus::Shipped)
        ->and($order->jubelio_courier)->toBe('Wahana Express')
        ->and($order->tracking_no)->toBeNull()
        ->and($history->status)->toBe(OrderStatus::Shipped)
        ->and($history->source)->toBe(OrderStatusSource::Jubelio)
        ->and($history->note)->toBe('Diselesaikan di Jubelio, faktur INV-000187421')
        ->and(WebhookEvent::sole())
        ->event_type->toBe('update-salesorder')
        ->status->toBe(WebhookEventStatus::Processed)
        ->and(jubelioCalls())->toContain('/sales/orders/193460');
});

test('WH-05 reads the state from Jubelio, not from the payload', function () {
    $order = paidWebsiteOrder('WS-000030', 193500);
    // The payload claims completion, Jubelio itself does not.
    fakeSalesOrders([193500 => finishedSalesOrder(193500, 'WS-000030', ['marked_as_complete' => null, 'internal_status' => 'PAID', 'invoice_no' => null])]);

    deliverSalesOrder(['action' => 'update-salesorder', 'salesorder_id' => 193500, 'status' => 'Completed', 'invoice_no' => 'INV-1', 'marked_as_complete' => true])
        ->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(WebhookEvent::sole()->status)->toBe(WebhookEventStatus::Processed);
});

test('WH-05 keeps a tracking number Jubelio has and one the order already has', function () {
    $withNone = paidWebsiteOrder('WS-000031', 193501);
    $withOwn = paidWebsiteOrder('WS-000032', 193502, ['tracking_no' => 'WHN-LAMA', 'jubelio_courier' => 'Wahana']);
    fakeSalesOrders([
        193501 => finishedSalesOrder(193501, 'WS-000031', ['tracking_no' => 'WHN123']),
        193502 => finishedSalesOrder(193502, 'WS-000032', ['tracking_no' => 'WHN456']),
    ]);

    deliverSalesOrder(['action' => 'update-salesorder', 'salesorder_id' => 193501])->assertOk();
    deliverSalesOrder(['action' => 'update-salesorder', 'salesorder_id' => 193502])->assertOk();

    expect($withNone->refresh())->status->toBe(OrderStatus::Shipped)->tracking_no->toBe('WHN123')
        ->and($withOwn->refresh())->status->toBe(OrderStatus::Shipped)->tracking_no->toBe('WHN-LAMA')->jubelio_courier->toBe('Wahana');
});

test('WH-05 finds the order by its number when the sales order id differs', function () {
    $order = paidWebsiteOrder('WS-000033', 193503);
    fakeSalesOrders([193503 => finishedSalesOrder(193503, 'WS-000033')]);

    deliverSalesOrder(['action' => 'update-salesorder', 'salesorder_id' => 1, 'ref_no' => 'WS-000033'])->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Shipped);
});

test('WH-05 for another channel answers 200 without recording or asking Jubelio', function () {
    paidWebsiteOrder('WS-000034', 193504);
    fakeSalesOrders([]);

    deliverSalesOrder(['action' => 'update-salesorder', 'salesorder_id' => 555001, 'salesorder_no' => 'SP-2511030001', 'ref_no' => '2511030ABCD'])
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);

    expect(WebhookEvent::count())->toBe(0)
        ->and(jubelioCalls())->toBe([]);
});

test('WH-05 for an order that is not waiting for the warehouse is ignored', function (OrderStatus $status) {
    $order = paidWebsiteOrder('WS-000035', 193505, ['status' => $status]);
    fakeSalesOrders([193505 => finishedSalesOrder(193505, 'WS-000035')]);

    deliverSalesOrder(['action' => 'update-salesorder', 'salesorder_id' => 193505])->assertOk();

    expect($order->refresh()->status)->toBe($status)
        ->and(WebhookEvent::sole()->status)->toBe(WebhookEventStatus::Ignored)
        ->and(jubelioCalls())->toBe([]);
})->with([OrderStatus::PendingPayment, OrderStatus::Shipped, OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Expired]);

test('WH-05 with a wrong signature is rejected and changes nothing', function () {
    $order = paidWebsiteOrder('WS-000036', 193506);
    fakeSalesOrders([193506 => finishedSalesOrder(193506, 'WS-000036')]);

    assertApiError(deliverSalesOrder(['action' => 'update-salesorder', 'salesorder_id' => 193506], 'salah'), 401, 'INVALID_SIGNATURE');

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(WebhookEvent::sole()->signature_valid)->toBeFalse();
});

test('WH-05 without salesorder_id answers 422', function () {
    assertApiError(deliverSalesOrder(['action' => 'update-salesorder']), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('salesorder_id');
});

// --- When a sales order counts as finished --------------------------------------------------------

test('a sales order is finished only when marked complete, invoiced, and not cancelled', function (array $overrides, bool $finished) {
    expect(SalesOrderProgress::isCompleted(finishedSalesOrder(1, 'WS-1', $overrides)))->toBe($finished);
})->with([
    'as WS-000027 after mark-as-complete' => [[], true],
    'internal_status COMPLETED without the flag' => [['marked_as_complete' => null], true],
    'paid, invoiced, not finished' => [['marked_as_complete' => false, 'internal_status' => 'PAID'], false],
    'finished, no invoice yet' => [['invoice_no' => null], false],
    'finished, blank invoice' => [['invoice_no' => '  '], false],
    'cancelled' => [['is_canceled' => true], false],
]);

// --- orders:check-shipped ----------------------------------------------------------------------------

test('orders:check-shipped marks finished sales orders shipped and leaves the rest waiting', function () {
    $finished = paidWebsiteOrder('WS-000040', 193510);
    $waiting = paidWebsiteOrder('WS-000041', 193511);
    $gone = paidWebsiteOrder('WS-000042', 193512);
    $old = paidWebsiteOrder('WS-000043', 193513, ['created_at' => now()->subDays(61)]);
    $unsynced = paidWebsiteOrder('WS-000044', 0, ['jubelio_salesorder_id' => null]);
    fakeSalesOrders([
        193510 => finishedSalesOrder(193510, 'WS-000040'),
        193511 => finishedSalesOrder(193511, 'WS-000041', ['marked_as_complete' => false, 'internal_status' => 'PAID', 'invoice_no' => null]),
        193512 => null,
        193513 => finishedSalesOrder(193513, 'WS-000043'),
    ]);

    $this->artisan('orders:check-shipped')
        ->expectsOutputToContain('3 pesanan diperiksa di Jubelio, 1 ditandai dikirim.')
        ->assertSuccessful();

    expect($finished->refresh()->status)->toBe(OrderStatus::Shipped)
        ->and($waiting->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($gone->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($old->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($unsynced->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(jubelioCalls())->not->toContain('/sales/orders/193513');
});

test('orders:check-shipped asks the least recently asked orders first', function () {
    $this->travelTo(now()->subHour());
    $older = paidWebsiteOrder('WS-000045', 193515);
    $this->travelBack();
    paidWebsiteOrder('WS-000046', 193516);
    fakeSalesOrders([193515 => finishedSalesOrder(193515, 'WS-000045'), 193516 => finishedSalesOrder(193516, 'WS-000046')]);

    $this->artisan('orders:check-shipped', ['--limit' => 1])->assertSuccessful();

    expect($older->refresh()->status)->toBe(OrderStatus::Shipped)
        ->and(collect(jubelioCalls())->filter(fn ($path) => str_starts_with($path, '/sales/orders/'))->values()->all())->toBe(['/sales/orders/193515']);
});

test('orders:check-shipped goes on when Jubelio fails for one order', function () {
    $failing = paidWebsiteOrder('WS-000047', 193517);
    $finished = paidWebsiteOrder('WS-000048', 193518);
    fakeSalesOrders([193518 => finishedSalesOrder(193518, 'WS-000048')]);
    fakeJubelioCatalog([
        '/sales/orders/193517' => Http::response(['message' => 'Bad Request'], 400),
        '/sales/orders/193518' => Http::response(finishedSalesOrder(193518, 'WS-000048')),
    ]);

    $this->artisan('orders:check-shipped')->assertSuccessful();

    expect($failing->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($finished->refresh()->status)->toBe(OrderStatus::Shipped);
});

test('orders:check-shipped runs on the schedule', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('orders:check-shipped')->assertSuccessful();
});

// --- ADM-29 ----------------------------------------------------------------------------------------

test('ADM-29 marks a shipped order completed and records the admin', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin Gudang']);
    $order = paidWebsiteOrder('WS-000050', 193520, ['status' => OrderStatus::Shipped]);

    $this->actingAs($admin)->postJson('/api/v1/admin/orders/WS-000050/complete')
        ->assertOk()
        ->assertExactJson([
            'data' => ['order_number' => 'WS-000050', 'status' => 'completed', 'status_label' => 'Selesai'],
            'message' => 'Pesanan WS-000050 ditandai selesai.',
        ]);

    $history = $order->statusHistories()->latest('id')->first();

    expect($order->refresh()->status)->toBe(OrderStatus::Completed)
        ->and($history->source)->toBe(OrderStatusSource::Admin)
        ->and($history->note)->toBe('Diselesaikan oleh admin Admin Gudang');
});

test('ADM-29 refuses an order that is not shipped', function (OrderStatus $status) {
    $order = paidWebsiteOrder('WS-000051', 193521, ['status' => $status]);

    assertApiError($this->actingAs(User::factory()->admin()->create())->postJson('/api/v1/admin/orders/WS-000051/complete'), 409, 'ORDER_NOT_SHIPPED');

    expect($order->refresh()->status)->toBe($status);
})->with([OrderStatus::PendingPayment, OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Expired]);

test('ADM-29 answers 404 for an unknown order and refuses guests and customers', function () {
    assertApiError($this->postJson('/api/v1/admin/orders/WS-999999/complete'), 401, 'UNAUTHENTICATED');
    assertApiError($this->actingAs(User::factory()->create())->postJson('/api/v1/admin/orders/WS-999999/complete'), 403, 'FORBIDDEN');
    assertApiError($this->actingAs(User::factory()->admin()->create())->postJson('/api/v1/admin/orders/WS-999999/complete'), 404, 'ORDER_NOT_FOUND');
});
