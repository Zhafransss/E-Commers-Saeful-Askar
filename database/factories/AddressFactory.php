<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 *
 * Uses the Region IDs from the contract examples (Satriajaya, Tambun Utara).
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Rumah',
            'recipient_name' => fake()->name(),
            'phone' => '08'.fake()->numerify('##########'),
            'address_line' => fake()->streetAddress(),
            'province_id' => '32',
            'province_name' => 'JAWA BARAT',
            'city_id' => '3216',
            'city_name' => 'KAB. BEKASI',
            'district_id' => '321612',
            'district_name' => 'TAMBUN UTARA',
            'subdistrict_id' => '3216122004',
            'subdistrict_name' => 'Satriajaya',
            'post_code' => '17565',
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }
}
