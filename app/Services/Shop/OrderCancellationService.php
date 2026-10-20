<?php

namespace App\Services\Shop;

use App\Enums\JubelioSyncStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusSource;
use App\Enums\PaymentStatus;
use App\Enums\SyncTrigger;
use App\Exceptions\ApiException;
use App\Exceptions\IntegrationException;
use App\Jobs\Jubelio\CancelSalesOrderJob;
use App\Mail\OrderCancelledMail;
use App\Models\Order;
use App\Services\Gapura\GapuraClient;
use App\Services\Jubelio\SalesOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * ORD-05 · The customer cancels an order they have not paid (docs/api/checkout-orders.md). DANA's order is
 * closed first, so the customer can no longer pay it, and only then is the Jubelio sales order cancelled.
 *
 * If DANA refuses because the order was in fact already paid (the notification was just late), the order
 * is not cancelled: the payment is applied and the customer is told it can no longer be cancelled.
 */
class OrderCancellationService
{
    public function __construct(
        private readonly GapuraClient $gapura,
        private readonly OrderPaymentService $payments,
        private readonly OrderStatusRecorder $status,
        private readonly SalesOrderService $salesOrders,
    ) {}

    public function cancel(Order $order, ?string $reason = null): Order
    {
        $this->assertCancellable($order);

        try {
            $this->gapura->withinCustomerRequest()->cancelOrder($order->order_number, $reason);
        } catch (IntegrationException $e) {
            $this->explainRefusal($order, $e);
        }

        $reason = filled($reason) ? trim($reason) : 'Dibatalkan oleh pelanggan';
        $cancelled = DB::transaction(function () use ($order, $reason): bool {
            $moved = $this->status->transition($order, OrderStatus::Cancelled, OrderStatusSource::Customer, $reason, [
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            if ($moved) {
                $order->payment?->forceFill(['status' => PaymentStatus::Expired])->save();
            }

            return $moved;
        });

        // The webhook paid the order while we were cancelling: that wins, and DANA could not have closed it.
        if (! $cancelled) {
            $this->assertCancellable($order->refresh());
        }

        $this->cancelSalesOrder($order);
        Mail::to($order->user)->queue(new OrderCancelledMail($order));

        return $order->refresh()->load(['items', 'payment', 'statusHistories']);
    }

    private function assertCancellable(Order $order): void
    {
        if ($order->status === OrderStatus::PendingPayment) {
            return;
        }

        $message = match ($order->status) {
            OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed => 'Pesanan yang sudah dibayar tidak bisa dibatalkan dari website. Silakan hubungi toko.',
            default => 'Pesanan ini sudah tidak bisa dibatalkan.',
        };

        throw new ApiException($message, 'ORDER_NOT_CANCELLABLE', 409, extra: ['order_status' => $order->status->value]);
    }

    /**
     * DANA would not cancel. Ask what state the order is in: paid or closed means the customer's request
     * cannot be honoured; anything else is a real failure and ends as 502 with the order untouched.
     */
    private function explainRefusal(Order $order, IntegrationException $refusal): void
    {
        try {
            $this->payments->reconcile($order);
        } catch (IntegrationException) {
            throw $refusal;
        }

        $order->refresh();

        if ($order->status !== OrderStatus::PendingPayment) {
            $this->assertCancellable($order);
        }

        throw $refusal;
    }

    /**
     * The order is already cancelled for the customer. If Jubelio cannot be reached now, the order says so and
     * the queue keeps trying (an admin can resend it, ADM-11); the customer still gets a success.
     */
    private function cancelSalesOrder(Order $order): void
    {
        if ($order->jubelio_salesorder_id === null) {
            return;
        }

        try {
            $this->salesOrders->cancel($order, SyncTrigger::Checkout);
        } catch (Throwable $e) {
            $order->forceFill([
                'jubelio_sync_status' => JubelioSyncStatus::Failed,
                'jubelio_last_error' => $e instanceof IntegrationException ? $e->detail() : $e->getMessage(),
            ])->save();
            CancelSalesOrderJob::dispatch($order->id);
        }
    }
}
