<?php

namespace Database\Factories;

use App\Enums\ShippingVoucherType;
use App\Models\ShippingVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A running "Potongan ongkir Rp5.000, min. belanja Rp100.000" voucher without limits.
 *
 * @extends Factory<ShippingVoucher>
 */
class ShippingVoucherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Potongan ongkir 5rb',
            'type' => ShippingVoucherType::ShippingDiscount,
            'amount' => 5000,
            'min_subtotal' => 100000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'quota' => null,
            'per_user_limit' => null,
        ];
    }

    public function freeShipping(int $minSubtotal = 0): static
    {
        return $this->state(['name' => 'Gratis ongkir', 'type' => ShippingVoucherType::FreeShipping, 'amount' => null, 'min_subtotal' => $minSubtotal]);
    }
}
