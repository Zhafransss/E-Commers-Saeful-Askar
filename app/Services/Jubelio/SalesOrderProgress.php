<?php

namespace App\Services\Jubelio;

use App\Enums\OrderStatus;
use App\Enums\OrderStatusSource;
use App\Models\Order;
use App\Services\Shop\OrderStatusRecorder;

/**
 * Moves a paid website order to `shipped` once the warehouse has finished its Jubelio sales order
 * (POST /sales/orders/mark-as-complete, which also makes the invoice). Used by WH-05 and by the scheduled
 * `orders:check-shipped`, which catches a webhook that never arrived.
 *
 * The sales order is always read again from Jubelio (INT-JB-13 detail) rather than trusted from a webhook
 * payload, which may be partial or older than the current state. `completed` is never set here: the admin
 * closes an order by hand (ADM-29).
 */
class SalesOrderProgress
{
    /** Orders still waiting for the warehouse. */
    public const WAITING = [OrderStatus::Paid, OrderStatus::Processing];

    public function __construct(
        private readonly JubelioClient $jubelio,
        private readonly OrderStatusRecorder $status,
    ) {}

    /**
     * @return bool Whether the order became `shipped`
     */
    public function refresh(Order $order): bool
    {
        if (! $this->isWaiting($order) || $order->jubelio_salesorder_id === null) {
            return false;
        }

        $salesOrder = $this->jubelio->salesOrder((int) $order->jubelio_salesorder_id);

        return $salesOrder !== null && $this->apply($order, $salesOrder);
    }

    /**
     * @param  array<string, mixed>  $salesOrder  The sales order detail from Jubelio
     * @return bool Whether the order became `shipped`
     */
    public function apply(Order $order, array $salesOrder): bool
    {
        if (! $this->isWaiting($order) || ! self::isCompleted($salesOrder)) {
            return false;
        }

        $invoice = trim((string) $salesOrder['invoice_no']);

        // A tracking number already read by "Ambil resi" (ADM-22) is kept when Jubelio has none.
        $attributes = array_filter([
            'tracking_no' => $order->tracking_no === null ? self::trackingNo($salesOrder) : null,
            'jubelio_courier' => $order->jubelio_courier === null ? self::courier($salesOrder) : null,
        ]);

        return $this->status->transition(
            $order,
            OrderStatus::Shipped,
            OrderStatusSource::Jubelio,
            "Diselesaikan di Jubelio, faktur {$invoice}",
            $attributes,
        );
    }

    /**
     * Fills in the tracking number of a shipped order that had none when it became shipped: the warehouse may finish
     * the sales order before the courier's number reaches Jubelio. The status is left as it is.
     *
     * @return bool Whether a tracking number was found
     */
    public function refreshTracking(Order $order): bool
    {
        if (! self::awaitsTracking($order)) {
            return false;
        }

        $salesOrder = $this->jubelio->salesOrder((int) $order->jubelio_salesorder_id);
        $tracking = $salesOrder === null ? null : self::trackingNo($salesOrder);

        if ($tracking === null) {
            return false;
        }

        $order->forceFill([
            'tracking_no' => $tracking,
            'jubelio_courier' => $order->jubelio_courier ?? self::courier($salesOrder),
        ])->save();

        return true;
    }

    /**
     * Shipped, sent to Jubelio, and still without a tracking number.
     */
    public static function awaitsTracking(Order $order): bool
    {
        return $order->status === OrderStatus::Shipped
            && $order->tracking_no === null
            && $order->jubelio_salesorder_id !== null;
    }

    /**
     * The tracking number (resi) on a sales order detail, or null while it has none.
     *
     * @param  array<string, mixed>  $salesOrder
     */
    public static function trackingNo(array $salesOrder): ?string
    {
        $tracking = trim((string) ($salesOrder['tracking_no'] ?? $salesOrder['tracking_number'] ?? ''));

        return $tracking === '' ? null : mb_substr($tracking, 0, 100);
    }

    /**
     * The courier the warehouse used, as Jubelio names it, or null.
     *
     * @param  array<string, mixed>  $salesOrder
     */
    public static function courier(array $salesOrder): ?string
    {
        $courier = trim((string) ($salesOrder['shipper'] ?? $salesOrder['courier'] ?? ''));

        return $courier === '' ? null : mb_substr($courier, 0, 100);
    }

    /**
     * Finished in Jubelio and invoiced, and not cancelled. Seen on WS-000027 (3 Nov 2026): after
     * mark-as-complete Jubelio sets marked_as_complete true, internal_status COMPLETED, and invoice_no.
     *
     * @param  array<string, mixed>  $salesOrder
     */
    public static function isCompleted(array $salesOrder): bool
    {
        if (filter_var($salesOrder['is_canceled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $marked = filter_var($salesOrder['marked_as_complete'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || strtoupper((string) ($salesOrder['internal_status'] ?? '')) === 'COMPLETED';

        return $marked && trim((string) ($salesOrder['invoice_no'] ?? '')) !== '';
    }

    private function isWaiting(Order $order): bool
    {
        return in_array($order->status, self::WAITING, true);
    }
}
