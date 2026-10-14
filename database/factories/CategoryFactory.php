<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'jubelio_category_id' => fake()->unique()->numberBetween(1, 999999),
            'parent_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }
}
