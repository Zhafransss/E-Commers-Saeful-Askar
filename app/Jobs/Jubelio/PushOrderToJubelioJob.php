<?php

namespace App\Jobs\Jubelio;

use App\Enums\JubelioSyncStatus;
use App\Enums\OrderStatus;
use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Exceptions\IntegrationException;
use App\Jobs\Concerns\RetriesIntegrationCalls;
use App\Models\Order;
use App\Models\SyncLog;
use App\Services\Jubelio\ContactService;
use App\Services\Jubelio\SalesOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * ADM-11: an admin sends an order to Jubelio again, one of `create`, `mark_paid` or `cancel`. The work is
 * the same as at checkout and after a payment, but it runs inside the sync log the admin was given, so the
 * result shows in ADM-10. `create` looks for the sales order by `ref_no` first, so it never makes a second one.
 */
class PushOrderToJubelioJob implements ShouldQueue
{
    use Queueable, RetriesIntegrationCalls;

    public function __construct(public int $orderId, public string $action, public int $syncLogId) {}

    public function handle(SalesOrderService $salesOrders, ContactService $contacts): void
    {
        $order = Order::with(['user', 'address'])->findOrFail($this->orderId);
        $log = SyncLog::findOrFail($this->syncLogId);

        match ($this->action) {
            'create' => $this->create($order, $log, $salesOrders, $contacts),
            'mark_paid' => $salesOrders->markPaid($order, SyncTrigger::Manual, $log),
            'cancel' => $salesOrders->cancel($order, SyncTrigger::Manual, $log),
        };

        $order->forceFill(['jubelio_sync_status' => JubelioSyncStatus::Synced, 'jubelio_last_error' => null])->save();
    }

    private function create(Order $order, SyncLog $log, SalesOrderService $salesOrders, ContactService $contacts): void
    {
        if ($order->user->jubelio_contact_id === null) {
            $contacts->ensure($order->user, $order->address);
            $order->user->refresh();
        }

        $salesOrder = $salesOrders->create($order, SyncTrigger::Manual, $log);
        $order->forceFill(['jubelio_salesorder_id' => $salesOrder['id'], 'jubelio_salesorder_no' => $salesOrder['no']])->save();

        // An order that was already paid when it is sent must also be paid in Jubelio.
        if (in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed], true)) {
            $salesOrders->markPaid($order, SyncTrigger::Manual);
        }
    }

    public function failed(Throwable $e): void
    {
        // The log is closed by the service when the call itself failed; close it here if we never got that far.
        SyncLog::whereKey($this->syncLogId)->where('status', SyncStatus::Running->value)->first()?->markFailed($e);

        Order::whereKey($this->orderId)->update([
            'jubelio_sync_status' => JubelioSyncStatus::Failed->value,
            'jubelio_last_error' => $e instanceof IntegrationException ? $e->detail() : $e->getMessage(),
        ]);
    }
}
