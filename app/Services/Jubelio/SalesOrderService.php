<?php

namespace App\Services\Jubelio;

use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Exceptions\IntegrationException;
use App\Models\Order;
use App\Models\SyncLog;
use LogicException;

/**
 * The website order as a Jubelio sales order (INT-JB-10 – INT-JB-13). It is created unpaid at checkout so
 * its stock is locked in every channel, marked paid when Gapura confirms the payment, and cancelled when
 * the order expires or the customer cancels.
 *
 * `ref_no` is the order number and is looked up before and after a failed create, so a retry after a
 * timeout never makes a second sales order for the same order.
 */
class SalesOrderService
{
    public function __construct(private readonly JubelioClient $jubelio) {}

    /**
     * @return array{id: int, no: string|null} The sales order's id and number (the number may follow later)
     */
    public function create(Order $order, SyncTrigger $trigger = SyncTrigger::Checkout, ?SyncLog $log = null): array
    {
        $result = [];
        $this->logged(SyncType::OrderPush, $trigger, $order, function () use ($order, &$result): int {
            $result = $this->push($order);

            return 1;
        }, $log);

        return $result;
    }

    /**
     * Runs the call inside a sync log: the one given (an admin resend creates it up front so the
     * response can name it), or a new one.
     */
    private function logged(SyncType $type, SyncTrigger $trigger, Order $order, \Closure $work, ?SyncLog $log): void
    {
        if ($log === null) {
            SyncLog::track($type, $trigger, $work, $order->id);

            return;
        }

        try {
            $log->markSucceeded($work());
        } catch (\Throwable $e) {
            $log->markFailed($e);

            throw $e;
        }
    }

    /**
     * @return array{id: int, no: string|null}
     */
    private function push(Order $order): array
    {
        if ($found = $this->jubelio->findSalesOrderByRefNo($order->order_number)) {
            return $this->summary($found);
        }

        try {
            $id = $this->jubelio->createSalesOrder($this->payload($order));
        } catch (IntegrationException $e) {
            // Jubelio often answers 500 for an order it did create (INT-JB-10): look before giving up.
            $found = rescue(fn () => $this->jubelio->findSalesOrderByRefNo($order->order_number), report: false);

            if ($found === null) {
                throw $e;
            }

            return $this->summary($found);
        }

        // The number is only for display; the order does not fail because it cannot be read yet.
        $detail = rescue(fn () => $this->jubelio->salesOrder($id), report: false);

        return ['id' => $id, 'no' => $detail['salesorder_no'] ?? null];
    }

    /**
     * INT-JB-11. Safe to repeat for an order that is already paid.
     */
    public function markPaid(Order $order, SyncTrigger $trigger = SyncTrigger::Webhook, ?SyncLog $log = null): void
    {
        $this->logged(SyncType::OrderPaid, $trigger, $order, function () use ($order): int {
            $this->jubelio->setSalesOrdersPaid([$this->salesOrderId($order)]);

            return 1;
        }, $log);
    }

    /**
     * INT-JB-12.
     */
    public function cancel(Order $order, SyncTrigger $trigger = SyncTrigger::Webhook, ?SyncLog $log = null): void
    {
        $this->logged(SyncType::OrderCancel, $trigger, $order, function () use ($order): int {
            $this->jubelio->cancelSalesOrders([$this->salesOrderId($order)]);

            return 1;
        }, $log);
    }

    /**
     * The INT-JB-10 body from the order's snapshots; nothing is read from the catalog again, so a price
     * changed after checkout does not change what was sold.
     *
     * @return array<string, mixed>
     */
    public function payload(Order $order): array
    {
        $order->loadMissing('items', 'user');

        if ($order->user->jubelio_contact_id === null) {
            throw new LogicException('Order tanpa contact Jubelio: ContactService::ensure() harus berjalan dulu.');
        }

        $location = (int) config('jubelio.location_id');
        $items = $order->items->map(fn ($item) => [
            'salesorder_detail_id' => 0,
            'item_id' => $item->jubelio_item_id,
            'description' => trim($item->product_name.($item->variant_name ? " - {$item->variant_name}" : '')),
            'tax_id' => (int) config('jubelio.tax_id'),
            'price' => $item->price,
            'unit' => $item->unit,
            'qty_in_base' => $item->qty,
            'disc' => 0,
            'disc_amount' => 0,
            'tax_amount' => 0,
            'amount' => $item->price * $item->qty,
            'location_id' => $location,
            'shipper' => $order->service_name,
        ])->all();

        return [
            'salesorder_id' => 0,
            'salesorder_no' => '[auto]',
            'contact_id' => $order->user->jubelio_contact_id,
            'customer_name' => $order->user->name,
            'transaction_date' => $order->created_at->clone()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'is_tax_included' => false,
            'note' => $order->note,
            'sub_total' => $order->subtotal,
            'total_disc' => 0,
            'total_tax' => 0,
            'grand_total' => $order->grand_total,
            'ref_no' => $order->order_number,
            'location_id' => $location,
            'source' => 1,
            'store_id' => (string) config('jubelio.store_id'),
            'is_paid' => false,
            'payment_method' => 'DANA Gapura',
            'shipping_cost' => $order->shipping_cost,
            'insurance_cost' => $order->insurance_cost,
            'shipping_full_name' => $order->shipping_recipient_name,
            'shipping_phone' => $order->shipping_phone,
            'shipping_address' => $order->shipping_address_line,
            'shipping_area' => $order->shipping_district,
            'shipping_city' => $order->shipping_city,
            'shipping_subdistrict' => $order->shipping_subdistrict,
            'shipping_province' => $order->shipping_province,
            'shipping_province_id' => $order->shipping_province_id,
            'shipping_city_id' => $order->shipping_city_id,
            'shipping_district_id' => $order->shipping_district_id,
            'shipping_subdistrict_id' => $order->shipping_subdistrict_id,
            'shipping_post_code' => $order->shipping_post_code,
            'shipping_country' => 'Indonesia',
            'add_disc' => 0,
            'add_fee' => 0,
            'service_fee' => 0,
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $row  A sales order from INT-JB-13
     * @return array{id: int, no: string|null}
     */
    private function summary(array $row): array
    {
        return ['id' => (int) $row['salesorder_id'], 'no' => $row['salesorder_no'] ?? null];
    }

    private function salesOrderId(Order $order): int
    {
        return $order->jubelio_salesorder_id
            ?? throw new LogicException("Order {$order->order_number} belum punya sales order Jubelio.");
    }
}
