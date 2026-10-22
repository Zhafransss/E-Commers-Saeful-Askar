<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ApiResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * One row of the admin customer list, ADM-18. Expects `orders_count`, `paid_orders_count` and `paid_orders_sum_grand_total`.
 *
 * @mixin User
 */
class AdminUserSummaryResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'orders_count' => (int) $this->orders_count,
            'paid_orders_count' => (int) $this->paid_orders_count,
            'paid_total' => (int) $this->paid_orders_sum_grand_total,
            'registered_at' => $this->created_at,
        ];
    }
}
