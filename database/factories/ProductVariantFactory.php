<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'jubelio_item_id' => fake()->unique()->numberBetween(1, 999999),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'name' => fake()->words(3, true),
            'variation_values' => [['label' => 'Warna', 'value' => fake()->safeColorName()]],
            'price' => fake()->numberBetween(20, 120) * 100000,
            'available_qty' => fake()->numberBetween(1, 10),
            'package_weight' => 30000,
            'barcode' => null,
            'image_url' => null,
            'is_bundle' => false,
            'is_active' => true,
            'stock_synced_at' => now(),
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => ['available_qty' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
