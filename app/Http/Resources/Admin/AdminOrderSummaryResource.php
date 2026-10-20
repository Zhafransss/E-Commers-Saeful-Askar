<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ApiResource;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * One row of the admin order list, ADM-09. Expects `user`, `payment` and an `items_count` to be loaded.
 *
 * @mixin Order
 */
class AdminOrderSummaryResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'customer' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ],
            'grand_total' => $this->grand_total,
            'item_count' => $this->items_count,
            'payment_status' => $this->payment?->status->value,
            'jubelio_sync_status' => $this->jubelio_sync_status->value,
            'jubelio_salesorder_no' => $this->jubelio_salesorder_no,
            'tracking_no' => $this->tracking_no,
            'is_checkout_failed' => $this->is_checkout_failed,
            'created_at' => $this->created_at,
        ];
    }
}
