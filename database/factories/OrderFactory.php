<?php

namespace Database\Factories;

use App\Enums\JubelioSyncStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An order as ORD-01 leaves it: pending payment, one JNE Trucking parcel to Tambun Utara.
 * The order number comes from the id (see afterCreating), like in OrderNumberGenerator.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::PendingPayment,
            'shipping_recipient_name' => fake()->name(),
            'shipping_phone' => '08'.fake()->numerify('##########'),
            'shipping_address_line' => 'Jl. Melati No. 12, RT 03/RW 05',
            'shipping_province_id' => '32',
            'shipping_province' => 'JAWA BARAT',
            'shipping_city_id' => '3216',
            'shipping_city' => 'KAB. BEKASI',
            'shipping_district_id' => '321605',
            'shipping_district' => 'TAMBUN UTARA',
            'shipping_subdistrict_id' => '3216052001',
            'shipping_subdistrict' => 'Satriajaya',
            'shipping_post_code' => '17565',
            'courier_code' => 'jne',
            'courier_name' => 'JNE',
            'service_code' => 'JTR',
            'service_name' => 'JNE Trucking',
            'service_type' => 'cargo',
            'etd_text' => '3–5 hari',
            'subtotal' => 6750000,
            'shipping_cost' => 185000,
            'insurance_cost' => 0,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 6935000,
            'payment_expires_at' => now()->addHours(2),
            'jubelio_sync_status' => JubelioSyncStatus::Pending,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order): void {
            if ($order->order_number === null) {
                $order->forceFill(['order_number' => sprintf('WEB-%06d', $order->id)])->save();
            }
        });
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
