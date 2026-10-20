<?php

namespace App\Services\Shop;

use App\Enums\OrderStatus;
use App\Enums\OrderStatusSource;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * The one place an order's status changes (docs/api/README.md "Transisi yang sah"). Every change is
 * written to order_status_histories with who caused it, and a change the lifecycle does not allow is
 * refused instead of applied, so a late or repeated event can never move an order backwards.
 */
class OrderStatusRecorder
{
    /**
     * The first history row of a new order: its status right now.
     */
    public function start(Order $order, OrderStatusSource $source, ?string $note = null): void
    {
        $order->statusHistories()->create(['status' => $order->status, 'source' => $source, 'note' => $note]);
    }

    /**
     * Moves the order to $to when the lifecycle allows it, applying $attributes (paid_at, cancel_reason, …)
     * in the same transaction. The row is locked first, so two events arriving together are handled one after
     * the other and the second sees the result of the first.
     *
     * @param  array<string, mixed>  $attributes
     * @return bool Whether the order changed
     */
    public function transition(Order $order, OrderStatus $to, OrderStatusSource $source, ?string $note = null, array $attributes = []): bool
    {
        $changed = DB::transaction(function () use ($order, $to, $source, $note, $attributes): bool {
            $current = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $current->status->canTransitionTo($to)) {
                return false;
            }

            $current->forceFill(['status' => $to] + $attributes)->save();
            $current->statusHistories()->create(['status' => $to, 'source' => $source, 'note' => $note]);

            return true;
        });

        $order->refresh();

        return $changed;
    }
}
