<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CatalogCache;

/*
| CAT-01 · GET /api/v1/categories (docs/api/catalog.md)
*/

/**
 * @param  array<string, mixed>  $attributes
 */
function visibleProductIn(Category $category, array $attributes = []): Product
{
    $product = Product::factory()->for($category)->create($attributes);
    ProductVariant::factory()->for($product)->create();

    return $product;
}

beforeEach(function () {
    $this->sparepart = Category::factory()->create(['name' => 'Sparepart', 'slug' => 'sparepart']);
    $this->battery = Category::factory()->create(['name' => 'Baterai / AKi', 'slug' => 'baterai-aki', 'parent_id' => $this->sparepart->id]);
    $this->bike = Category::factory()->create(['name' => 'Roda 2', 'slug' => 'roda-2']);
});

test('lists categories by name in the contract shape, without pagination', function () {
    visibleProductIn($this->battery);
    visibleProductIn($this->bike);

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => $this->battery->id, 'name' => 'Baterai / AKi', 'slug' => 'baterai-aki', 'parent_id' => $this->sparepart->id, 'product_count' => 1],
            ['id' => $this->bike->id, 'name' => 'Roda 2', 'slug' => 'roda-2', 'parent_id' => null, 'product_count' => 1],
            ['id' => $this->sparepart->id, 'name' => 'Sparepart', 'slug' => 'sparepart', 'parent_id' => null, 'product_count' => 1],
        ]]);
});

test('a parent counts the products of its children, so the tree stays whole', function () {
    visibleProductIn($this->battery);
    visibleProductIn($this->battery);
    visibleProductIn($this->sparepart);

    $counts = collect($this->getJson('/api/v1/categories')->json('data'))->pluck('product_count', 'slug');

    expect($counts->all())->toBe(['baterai-aki' => 2, 'sparepart' => 3]);
});

test('only active products with an active variant are counted', function () {
    visibleProductIn($this->bike);
    Product::factory()->for($this->bike)->inactive()->has(ProductVariant::factory(), 'variants')->create();
    Product::factory()->for($this->bike)->has(ProductVariant::factory()->inactive(), 'variants')->create();
    Product::factory()->for($this->bike)->create(); // no variants at all

    expect(collect($this->getJson('/api/v1/categories')->json('data'))->firstWhere('slug', 'roda-2')['product_count'])->toBe(1);
});

test('empty categories are hidden unless with_empty=1', function () {
    visibleProductIn($this->bike);

    expect(collect($this->getJson('/api/v1/categories')->json('data'))->pluck('slug')->all())->toBe(['roda-2'])
        ->and(collect($this->getJson('/api/v1/categories?with_empty=1')->json('data'))->pluck('product_count', 'slug')->all())
        ->toBe(['baterai-aki' => 0, 'roda-2' => 1, 'sparepart' => 0]);
});

test('a category marked unused after a sync is hidden', function () {
    visibleProductIn($this->bike);
    $this->bike->update(['is_active' => false]);

    expect(collect($this->getJson('/api/v1/categories?with_empty=1')->json('data'))->pluck('slug')->all())
        ->toBe(['baterai-aki', 'sparepart']);
});

test('with_empty must be a boolean', function () {
    assertApiError($this->getJson('/api/v1/categories?with_empty=ya'), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('with_empty');
});

test('the response is cached and refreshed after a sync drops the cache', function () {
    visibleProductIn($this->bike);
    $this->getJson('/api/v1/categories')->assertJsonCount(1, 'data');

    visibleProductIn($this->battery);
    $this->getJson('/api/v1/categories')->assertJsonCount(1, 'data');

    CatalogCache::flush();
    $this->getJson('/api/v1/categories')->assertJsonCount(3, 'data');
});
