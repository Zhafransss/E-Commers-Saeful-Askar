<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Sepeda Listrik '.Str::title(fake()->unique()->words(2, true));

        return [
            'jubelio_item_group_id' => fake()->unique()->numberBetween(1, 999999),
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'brand_name' => fake()->company(),
            'description_html' => '<p>'.fake()->sentence(12).'</p>',
            'sell_unit' => 'Unit',
            'min_qty' => null,
            'max_qty' => null,
            'package_weight' => 30000,
            'package_length' => 150,
            'package_width' => 30,
            'package_height' => 80,
            'variations' => null,
            'thumbnail_url' => null,
            'is_active' => true,
            'jubelio_last_modified' => now(),
            'synced_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
