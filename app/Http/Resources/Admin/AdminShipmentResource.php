<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ApiResource;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * One row of the admin shipment (resi) list, ADM-21. Expects `user` to be loaded.
 *
 * @mixin Order
 */
class AdminShipmentResource extends ApiResource
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
            'customer' => ['id' => $this->user->id, 'name' => $this->user->name, 'phone' => $this->user->phone],
            'recipient_name' => $this->shipping_recipient_name,
            'destination' => collect([$this->shipping_city, $this->shipping_province])->filter()->implode(', '),
            'courier' => $this->courier_name,
            'service' => $this->service_name,
            'jubelio_courier' => $this->jubelio_courier,
            'jubelio_salesorder_no' => $this->jubelio_salesorder_no,
            'tracking_no' => $this->tracking_no,
            'grand_total' => $this->grand_total,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
