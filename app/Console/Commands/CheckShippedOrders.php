<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Exceptions\IntegrationException;
use App\Models\Order;
use App\Services\Jubelio\SalesOrderProgress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Backup for a WH-05 webhook that never arrived: asks Jubelio about paid orders still waiting for the warehouse
 * and marks those whose sales order was finished (mark-as-complete, invoiced) as shipped. Shipped orders without a
 * tracking number are asked too, because the warehouse may finish the sales order before the courier's number reaches
 * Jubelio. The least recently asked order goes first and each asked order is touched, so a few orders that are never
 * finished cannot hide the rest.
 */
class CheckShippedOrders extends Command
{
    protected $signature = 'orders:check-shipped {--limit=30 : Paling banyak pesanan yang ditanyakan ke Jubelio sekali jalan}';

    protected $description = 'Tandai dikirim pesanan lunas yang sales order Jubelio-nya sudah diselesaikan (mark-as-complete), dan ambil resi yang belum ada';

    /** Orders older than this are not asked about any more: one never finished in Jubelio would be asked forever. */
    private const MAX_AGE_DAYS = 60;

    public function handle(SalesOrderProgress $progress): int
    {
        $orders = Order::query()
            ->where(fn ($query) => $query
                ->whereIn('status', SalesOrderProgress::WAITING)
                ->orWhere(fn ($shipped) => $shipped->where('status', OrderStatus::Shipped)->whereNull('tracking_no')))
            ->whereNotNull('jubelio_salesorder_id')
            ->where('created_at', '>=', now()->subDays(self::MAX_AGE_DAYS))
            ->orderBy('updated_at')->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $shipped = $tracked = 0;

        foreach ($orders as $order) {
            $waiting = in_array($order->status, SalesOrderProgress::WAITING, true);

            try {
                $changed = $waiting ? $progress->refresh($order) : $progress->refreshTracking($order);
            } catch (IntegrationException $e) {
                // Next round asks again; one unreachable order must not stop the others.
                Log::warning("orders:check-shipped {$order->order_number}: {$e->detail()}");

                continue;
            }

            if (! $changed) {
                $order->touch();
            } elseif ($waiting) {
                $shipped++;
            } else {
                $tracked++;
            }
        }

        $this->components->info("{$orders->count()} pesanan diperiksa di Jubelio, {$shipped} ditandai dikirim, {$tracked} resi baru.");

        return self::SUCCESS;
    }
}
