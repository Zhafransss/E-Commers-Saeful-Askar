<?php

namespace App\Services\Webhooks\Handlers;

use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\Jubelio\SalesOrderProgress;
use App\Services\Webhooks\WebhookEventIgnored;
use App\Services\Webhooks\WebhookHandler;

/**
 * WH-05 `update-salesorder`: a website order whose sales order the warehouse finished in Jubelio
 * (mark-as-complete, invoice made) becomes `shipped`. The sales order is read again from Jubelio
 * (SalesOrderProgress), so only its id is taken from the payload.
 */
class JubelioSalesOrderUpdated implements WebhookHandler
{
    public function __construct(private readonly SalesOrderProgress $progress) {}

    public function handle(WebhookEvent $event): void
    {
        $order = self::findOrder($event->payload);

        if ($order === null) {
            throw new WebhookEventIgnored('Sales order bukan pesanan website.');
        }

        if (! in_array($order->status, SalesOrderProgress::WAITING, true)) {
            throw new WebhookEventIgnored("Pesanan {$order->order_number} berstatus {$order->status->value}, tidak menunggu gudang.");
        }

        $this->progress->refresh($order);
    }

    /**
     * By sales order id, or by the order number, which is both the `ref_no` and (JUBELIO_USE_ORDER_NUMBER)
     * the `salesorder_no` of a website sales order.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function findOrder(array $payload): ?Order
    {
        $numbers = array_values(array_filter(
            [$payload['ref_no'] ?? null, $payload['salesorder_no'] ?? null],
            fn ($value) => is_string($value) && $value !== '',
        ));

        return Order::where('jubelio_salesorder_id', (int) ($payload['salesorder_id'] ?? 0))->first()
            ?? ($numbers === [] ? null : Order::whereIn('order_number', $numbers)->first());
    }
}
