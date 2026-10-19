<?php

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

test('database seeder creates the admin and the demo catalog', function () {
    $this->seed();

    expect(User::where('role', UserRole::Admin)->count())->toBe(1)
        ->and(Product::visible()->count())->toBe(2);
});

test('seeders are safe to run twice', function () {
    $this->seed();
    $this->seed();

    // One admin plus the five sample customers of CustomerUserSeeder.
    expect(Product::count())->toBe(2)
        ->and(User::count())->toBe(6);
});

test('inactive products and products without active variants are hidden', function () {
    Product::factory()->inactive()->hasVariants(1)->create();
    Product::factory()->has(ProductVariant::factory()->inactive(), 'variants')->create();
    $visible = Product::factory()->hasVariants(1)->create();

    expect(Product::visible()->pluck('id')->all())->toBe([$visible->id]);
});
