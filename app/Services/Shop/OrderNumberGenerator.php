<?php

namespace App\Services\Shop;

use App\Models\Order;

/**
 * `WEB-000123`, built from the order's id right after the insert: sequential and unique without a
 * counter table (docs/erd.md). It is sent to Jubelio as `ref_no` and to Gapura as `partnerReferenceNo`.
 * The prefix is `shop.order_prefix`, different per install (see config/shop.php).
 */
class OrderNumberGenerator
{
    public function assign(Order $order): Order
    {
        $order->forceFill(['order_number' => sprintf('%s-%06d', $this->prefix(), $order->id)])->save();

        return $order;
    }

    /**
     * Letters and digits only, up to 8 of them (the column holds 20 characters); `WEB` when nothing usable is set.
     */
    private function prefix(): string
    {
        $prefix = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', (string) config('shop.order_prefix')), 0, 8));

        return $prefix === '' ? 'WEB' : $prefix;
    }
}
