<?php

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Exceptions\IntegrationException;
use App\Models\Order;
use App\Services\Jubelio\JubelioClient;
use App\Services\Jubelio\SalesOrderProgress;
use Illuminate\Support\Facades\Log;

/**
 * Reads the tracking number (resi) of paid orders from their Jubelio sales order and keeps it on the order,
 * for the admin "Manajemen resi" screen. Jubelio is the source: nothing is typed in here. An order whose
 * sales order has no tracking number yet is simply asked again later: the least recently asked order goes
 * first, so a few orders that never get one cannot hide the rest.
 */
class ShipmentSync
{
    /** One click asks Jubelio for at most this many orders, so the request stays short. */
    public const BATCH = 30;

    public function __construct(private readonly JubelioClient $jubelio) {}

    /**
     * @return array{checked: int, updated: int, failed: int, remaining: int}
     */
    public function run(): array
    {
        $waiting = Order::query()
            ->whereNotNull('jubelio_salesorder_id')
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped])
            ->whereNull('tracking_no');

        $orders = (clone $waiting)->orderBy('updated_at')->orderBy('id')->limit(self::BATCH)->get();
        $updated = $failed = 0;

        foreach ($orders as $order) {
            try {
                $salesOrder = $this->jubelio->withinCustomerRequest()->salesOrder((int) $order->jubelio_salesorder_id);
            } catch (IntegrationException $e) {
                $failed++;
                Log::warning("shipments:refresh {$order->order_number}: {$e->detail()}");

                continue;
            }

            $tracking = $salesOrder === null ? null : SalesOrderProgress::trackingNo($salesOrder);

            if ($tracking === null) {
                $order->touch();

                continue;
            }

            $order->forceFill(['tracking_no' => $tracking, 'jubelio_courier' => SalesOrderProgress::courier($salesOrder)])->save();
            $updated++;
        }

        return [
            'checked' => $orders->count(),
            'updated' => $updated,
            'failed' => $failed,
            'remaining' => max(0, (clone $waiting)->count() - $orders->count()),
        ];
    }
}
