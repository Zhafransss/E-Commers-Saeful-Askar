<?php

namespace App\Http\Resources\Admin;

use App\Models\ShippingVoucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ADM-30 – ADM-32: a shipping voucher with how much of it is used. `status` is worked out from the dates and
 * the quota: scheduled (not started), running, exhausted (quota used up), ended.
 *
 * @mixin ShippingVoucher
 */
class AdminShippingVoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $used = $this->used_count ?? $this->usedCount();

        $status = match (true) {
            $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at !== null && ! $this->ends_at->isFuture() => 'ended',
            $this->quota !== null && $used >= $this->quota => 'exhausted',
            default => 'running',
        };

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'amount' => $this->amount,
            'min_subtotal' => $this->min_subtotal,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'quota' => $this->quota,
            'per_user_limit' => $this->per_user_limit,
            'used_count' => $used,
            'status' => $status,
            'status_label' => ['scheduled' => 'Terjadwal', 'running' => 'Aktif', 'exhausted' => 'Kuota habis', 'ended' => 'Berakhir'][$status],
            'created_at' => $this->created_at,
        ];
    }
}
