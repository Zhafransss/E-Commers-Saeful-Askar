<?php

namespace App\Services\Shop;

use App\Enums\OrderStatus;
use App\Enums\OrderStatusSource;
use App\Enums\PaymentStatus;
use App\Exceptions\IntegrationException;
use App\Jobs\Jubelio\CancelSalesOrderJob;
use App\Jobs\Jubelio\MarkSalesOrderPaidJob;
use App\Mail\OrderCancelledMail;
use App\Mail\PaymentReceivedMail;
use App\Models\Order;
use App\Services\Gapura\GapuraClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * What a payment result does to an order (docs/api/webhooks.md WH-01). The same rules serve every way the
 * result can arrive: Gapura's notification, the backup job that asks DANA about overdue orders, and the
 * "continue to pay" and "cancel" endpoints when the notification was late (ORD-04, ORD-05). Each rule is
 * idempotent, so a result seen twice changes the order once.
 */
class OrderPaymentService
{
    public function __construct(
        private readonly OrderStatusRecorder $status,
        private readonly GapuraClient $gapura,
    ) {}

    /**
     * DANA says the order is paid (`00`, or `02` when the payment is made but not final).
     * The amount must equal the bill; anything else leaves the order untouched.
     */
    public function applyPaid(Order $order, int $paidAmount, ?CarbonInterface $paidAt = null, ?string $method = null, ?string $channel = null): PaymentOutcome
    {
        $payment = $order->payment;

        if ($payment === null || $payment->amount !== $paidAmount) {
            Log::warning("Pembayaran {$order->order_number} tidak sama dengan tagihan.", ['billed' => $payment?->amount, 'paid' => $paidAmount]);

            return PaymentOutcome::AmountMismatch;
        }

        if (in_array($order->status, [OrderStatus::Expired, OrderStatus::Cancelled], true)) {
            return PaymentOutcome::TooLate;
        }

        $paidAt ??= now();

        $applied = DB::transaction(function () use ($order, $payment, $paidAmount, $paidAt, $method, $channel): bool {
            $moved = $this->status->transition($order, OrderStatus::Paid, OrderStatusSource::Gapura, 'Pembayaran diterima', ['paid_at' => $paidAt]);

            if ($moved) {
                $payment->forceFill([
                    'status' => PaymentStatus::Paid,
                    'paid_amount' => $paidAmount,
                    'paid_at' => $paidAt,
                    'payment_method' => $method,
                    'payment_channel' => $channel,
                ])->save();
            }

            return $moved;
        });

        if (! $applied) {
            return PaymentOutcome::Unchanged;
        }

        MarkSalesOrderPaidJob::dispatch($order->id);
        Mail::to($order->user)->queue(new PaymentReceivedMail($order));

        return PaymentOutcome::Applied;
    }

    /**
     * DANA closed the order (`05`) or it ran past its deadline: an unpaid order expires and its stock goes back.
     * Anything that is no longer waiting for payment is left alone.
     */
    public function applyExpired(Order $order): PaymentOutcome
    {
        $applied = DB::transaction(function () use ($order): bool {
            $reason = 'Batas waktu pembayaran habis';
            $moved = $this->status->transition($order, OrderStatus::Expired, OrderStatusSource::Gapura, $reason, [
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            if ($moved) {
                $order->payment?->forceFill(['status' => PaymentStatus::Expired])->save();
            }

            return $moved;
        });

        if (! $applied) {
            return PaymentOutcome::Unchanged;
        }

        CancelSalesOrderJob::dispatch($order->id);
        Mail::to($order->user)->queue(new OrderCancelledMail($order));

        return PaymentOutcome::Applied;
    }

    /**
     * Asks DANA where the order stands and applies the answer, for the cases where its notification did not
     * (yet) arrive: the backup job, "continue to pay" (ORD-04), and "cancel" (ORD-05).
     *
     * @throws IntegrationException When DANA cannot be asked
     */
    public function reconcile(Order $order): ?PaymentOutcome
    {
        return $this->check($order)['outcome'];
    }

    /**
     * Like reconcile(), and also says what DANA answered (`latestTransactionStatus`), for the admin screen.
     *
     * @return array{code: string, outcome: PaymentOutcome|null}
     *
     * @throws IntegrationException When DANA cannot be asked
     */
    public function check(Order $order): array
    {
        $result = $this->gapura->queryPayment($order->order_number);

        return ['code' => $result->statusCode, 'outcome' => match ($result->status) {
            PaymentStatus::Paid => $this->applyPaid($order, $result->amount ?? 0, $result->paidAt, $result->method, $result->channel),
            PaymentStatus::Expired => $this->applyExpired($order),
            PaymentStatus::Pending => null,
        }];
    }
}
