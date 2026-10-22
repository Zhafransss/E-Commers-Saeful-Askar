<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Api\V1\AddressResource;
use Illuminate\Http\Request;

/**
 * A customer as the admin sees them, ADM-19: read-only, so there is no password or token here. Expects `addresses`
 * and `orders` (latest first, already limited) to be loaded.
 */
class AdminUserDetailResource extends AdminUserSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'email_verified_at' => $this->email_verified_at,
            'addresses' => AddressResource::collection($this->addresses)->resolve($request),
            'recent_orders' => $this->orders->map(fn ($order) => [
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'grand_total' => $order->grand_total,
                'created_at' => $order->created_at,
            ])->values()->all(),
        ];
    }
}
