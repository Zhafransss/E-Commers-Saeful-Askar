<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Resources\ApiResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;

/**
 * OrderDetail object (docs/api/README.md), used by ORD-01, ORD-03, ORD-04, and ORD-05. Expects `items`,
 * `payment`, and `statusHistories` to be loaded.
 *
 * @mixin Order
 */
class OrderDetailResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->payment;
        $pending = $this->status === OrderStatus::PendingPayment;
        // The payment page is only offered while the order can still be paid.
        $payable = $pending && $this->payment_expires_at !== null && $this->payment_expires_at->isFuture();

        return [
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'items' => $this->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'variant_id' => $item->product_variant_id,
                'sku' => $item->sku,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'image_url' => $item->image_url,
                'price' => $item->price,
                'qty' => $item->qty,
                'weight_gram' => $item->weight_gram,
                'subtotal' => $item->subtotal,
            ])->values()->all(),
            'shipping_address' => [
                'recipient_name' => $this->shipping_recipient_name,
                'phone' => $this->shipping_phone,
                'address_line' => $this->shipping_address_line,
                'subdistrict' => $this->shipping_subdistrict,
                'district' => $this->shipping_district,
                'city' => $this->shipping_city,
                'province' => $this->shipping_province,
                'post_code' => $this->shipping_post_code,
            ],
            'shipping' => [
                'courier_code' => $this->courier_code,
                'courier_name' => $this->courier_name,
                'service_code' => $this->service_code,
                'service_name' => $this->service_name,
                'tracking_no' => $this->tracking_no,
                'etd_text' => $this->etd_text,
            ],
            'amounts' => [
                'subtotal' => $this->subtotal,
                'shipping_cost' => $this->shipping_cost,
                'insurance_cost' => $this->insurance_cost,
                'discount' => $this->discount,
                'tax' => $this->tax,
                'grand_total' => $this->grand_total,
            ],
            'payment' => [
                'status' => $payment?->status->value,
                'checkout_url' => $payable ? $payment?->checkout_url : null,
                'payment_method' => $payment?->payment_method,
                'payment_channel' => $payment?->payment_channel,
                'expires_at' => $payment?->expires_at ?? $this->payment_expires_at,
                'paid_at' => $payment?->paid_at,
            ],
            'note' => $this->note,
            'status_history' => $this->statusHistories->map(fn (OrderStatusHistory $history) => [
                'status' => $history->status->value,
                'label' => $history->status->label(),
                'at' => $history->created_at,
            ])->values()->all(),
            'can_pay' => $pending,
            'can_cancel' => $pending,
            'payment_expires_at' => $this->payment_expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
