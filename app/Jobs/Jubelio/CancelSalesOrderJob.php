<?php

namespace App\Jobs\Jubelio;

use App\Enums\JubelioSyncStatus;
use App\Exceptions\IntegrationException;
use App\Jobs\Concerns\RetriesIntegrationCalls;
use App\Models\Order;
use App\Services\Jubelio\SalesOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Cancels an order's Jubelio sales order so its stock comes back (INT-JB-12): after Gapura closes an
 * unpaid order (WH-01 `05`), when the customer cancels (ORD-05), or when checkout fails after the
 * sales order was made. Retried with INT-HDL backoff; after the last attempt the order is marked
 * `jubelio_sync_status = failed` so an admin can resend it (ADM-11).
 */
class CancelSalesOrderJob implements ShouldQueue
{
    use Queueable, RetriesIntegrationCalls;

    public function __construct(public int $orderId) {}

    public function handle(SalesOrderService $salesOrders): void
    {
        $order = Order::find($this->orderId);

        // Nothing to give back when the order never got a sales order.
        if ($order === null || $order->jubelio_salesorder_id === null) {
            return;
        }

        $salesOrders->cancel($order);
        $order->forceFill(['jubelio_sync_status' => JubelioSyncStatus::Synced, 'jubelio_last_error' => null])->save();
    }

    public function failed(Throwable $e): void
    {
        Order::whereKey($this->orderId)->update([
            'jubelio_sync_status' => JubelioSyncStatus::Failed->value,
            'jubelio_last_error' => $e instanceof IntegrationException ? $e->detail() : $e->getMessage(),
        ]);
    }
}
