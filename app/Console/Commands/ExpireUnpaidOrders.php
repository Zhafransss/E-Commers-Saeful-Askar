<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Exceptions\IntegrationException;
use App\Models\Order;
use App\Services\Shop\OrderPaymentService;
use App\Services\Shop\PaymentOutcome;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Backup for a Finish Notify that never arrived (docs/api/webhooks.md WH-01): for every order still waiting for
 * payment past its deadline, ask DANA what happened and apply the answer with the same rules as the webhook.
 */
class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Periksa status ke Gapura untuk pesanan yang lewat batas bayar, lalu tandai lunas atau kedaluwarsa';

    public function handle(OrderPaymentService $payments): int
    {
        $overdue = Order::query()
            ->with(['payment', 'user'])
            ->where('status', OrderStatus::PendingPayment)
            ->where('payment_expires_at', '<', now())
            ->whereHas('payment')
            ->get();

        $applied = 0;

        foreach ($overdue as $order) {
            try {
                $outcome = $payments->reconcile($order);
            } catch (IntegrationException $e) {
                // Next round asks again; one unreachable order must not stop the others.
                Log::warning("orders:expire-unpaid {$order->order_number}: {$e->detail()}");

                continue;
            }

            if ($outcome === PaymentOutcome::Applied) {
                $applied++;
            }
        }

        $this->components->info("{$overdue->count()} pesanan lewat batas bayar diperiksa, {$applied} diperbarui.");

        return self::SUCCESS;
    }
}
