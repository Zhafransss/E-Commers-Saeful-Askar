<?php

namespace App\Services\Shop;

use App\Models\Order;

/**
 * `WEB-000123`, built from the order's id right after the insert: sequential and unique without a
 * counter table (docs/erd.md). It is sent to Jubelio as `ref_no` and to Gapura as `partnerReferenceNo`.
 */
class OrderNumberGenerator
{
    public function assign(Order $order): Order
    {
        $order->forceFill(['order_number' => sprintf('WEB-%06d', $order->id)])->save();

        return $order;
    }
}
