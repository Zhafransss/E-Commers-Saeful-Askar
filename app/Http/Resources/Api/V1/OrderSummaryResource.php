<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\ApiResource;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * OrderSummary object (docs/api/README.md) for the order list, ORD-02. Expects `items` to be loaded.
 *
 * @mixin Order
 */
class OrderSummaryResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $first = $this->items->first();

        return [
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'item_count' => $this->items->count(),
            'first_item' => $first === null ? null : [
                'name' => trim($first->product_name.($first->variant_name ? " - {$first->variant_name}" : '')),
                'image_url' => $first->image_url,
            ],
            'grand_total' => $this->grand_total,
            'payment_expires_at' => $this->payment_expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
