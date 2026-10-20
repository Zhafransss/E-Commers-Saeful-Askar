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
 * Marks the order's Jubelio sales order paid once Gapura confirmed the payment (INT-JB-11), so the
 * warehouse starts on it. Safe to repeat. After the last attempt the order is marked
 * `jubelio_sync_status = failed` and shows up for an admin to resend (ADM-11).
 */
class MarkSalesOrderPaidJob implements ShouldQueue
{
    use Queueable, RetriesIntegrationCalls;

    public function __construct(public int $orderId) {}

    public function handle(SalesOrderService $salesOrders): void
    {
        $order = Order::find($this->orderId);

        if ($order === null || $order->jubelio_salesorder_id === null) {
            return;
        }

        $salesOrders->markPaid($order);
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
