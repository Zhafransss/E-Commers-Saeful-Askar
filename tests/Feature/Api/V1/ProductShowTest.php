<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;

/*
| CAT-03 · GET /api/v1/products/{slug} (docs/api/catalog.md)
*/

beforeEach(function () {
    $this->category = Category::factory()->create(['name' => 'Baterai / AKi', 'slug' => 'baterai-aki']);
    $this->product = Product::factory()->for($this->category)->create([
        'name' => 'Baterai Lithium Phylion',
        'slug' => 'baterai-lithium-phylion',
        'brand_name' => null,
        'description_html' => '<p>Sel lithium berkualitas.</p>',
        'sell_unit' => 'Buah',
        'min_qty' => null,
        'max_qty' => null,
        'package_weight' => 8800,
        'package_length' => 35,
        'package_width' => 15,
        'package_height' => null,
        'variations' => [['label' => 'Kapasitas', 'values' => ['48V 12Ah', '72V 24Ah']]],
    ]);
    $this->small = ProductVariant::factory()->for($this->product)->create([
        'sku' => 'SC000381N', 'name' => 'Baterai Lithium Phylion 48V 12Ah', 'price' => 2500000, 'available_qty' => 2,
        'package_weight' => 3000, 'variation_values' => [['label' => 'Kapasitas', 'value' => '48V 12Ah']], 'image_url' => null,
    ]);
    $this->big = ProductVariant::factory()->for($this->product)->create([
        'sku' => 'SC000384N', 'name' => 'Baterai Lithium Phylion 72V 24Ah', 'price' => 4500000, 'available_qty' => 0,
        'package_weight' => null, 'variation_values' => [['label' => 'Kapasitas', 'value' => '72V 24Ah']], 'image_url' => 'https://cdn.example.com/24ah.png',
    ]);
    $this->old = ProductVariant::factory()->for($this->product)->inactive()->create(['sku' => 'LAMA']);
});

test('returns ProductDetail in the contract shape', function () {
    $cover = ProductImage::create(['product_id' => $this->product->id, 'url' => 'https://cdn.example.com/a.png', 'thumbnail_url' => 'https://cdn.example.com/a-t.png', 'sort_order' => 0]);

    $response = $this->getJson('/api/v1/products/baterai-lithium-phylion')->assertOk();

    expect($response->json('data'))->toMatchArray([
        'id' => $this->product->id,
        'slug' => 'baterai-lithium-phylion',
        'name' => 'Baterai Lithium Phylion',
        'category' => ['id' => $this->category->id, 'name' => 'Baterai / AKi', 'slug' => 'baterai-aki'],
        'brand_name' => null,
        'description_html' => '<p>Sel lithium berkualitas.</p>',
        'sell_unit' => 'Buah',
        'min_qty' => null,
        'max_qty' => null,
        'package_dimensions' => ['length' => 35, 'width' => 15, 'height' => null],
        'images' => [['id' => $cover->id, 'url' => 'https://cdn.example.com/a.png', 'thumbnail_url' => 'https://cdn.example.com/a-t.png', 'variant_id' => null, 'sort_order' => 0]],
        'variations' => [['label' => 'Kapasitas', 'values' => ['48V 12Ah', '72V 24Ah']]],
        'price_min' => 2500000,
        'price_max' => 4500000,
        'in_stock' => true,
    ])->and($response->json('data.updated_at'))->toMatch('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+07:00$/');

    expect($response->json('data.variants.0'))->toBe([
        'id' => $this->small->id,
        'sku' => 'SC000381N',
        'name' => 'Baterai Lithium Phylion 48V 12Ah',
        'variation_values' => [['label' => 'Kapasitas', 'value' => '48V 12Ah']],
        'price' => 2500000,
        'available_qty' => 2,
        'in_stock' => true,
        'package_weight' => 3000,
        'image_url' => null,
    ]);
});

test('only active variants are listed; out-of-stock ones stay with in_stock = false', function () {
    $variants = $this->getJson('/api/v1/products/baterai-lithium-phylion')->json('data.variants');

    expect(array_column($variants, 'sku'))->toBe(['SC000381N', 'SC000384N'])
        ->and($variants[1])->toMatchArray(['available_qty' => 0, 'in_stock' => false]);
});

test('a variant without its own weight uses the item group weight', function () {
    expect($this->getJson('/api/v1/products/baterai-lithium-phylion')->json('data.variants.1.package_weight'))->toBe(8800);
});

test('negative stock is shown as 0', function () {
    $this->small->update(['available_qty' => -3]);

    expect($this->getJson('/api/v1/products/baterai-lithium-phylion')->json('data'))
        ->variants->{0}->available_qty->toBe(0)
        ->in_stock->toBeFalse();
});

test('images are ordered by sort_order; images of a hidden variant are left out', function () {
    ProductImage::create(['product_id' => $this->product->id, 'product_variant_id' => $this->big->id, 'url' => 'https://cdn.example.com/2.png', 'sort_order' => 2]);
    ProductImage::create(['product_id' => $this->product->id, 'url' => 'https://cdn.example.com/0.png', 'sort_order' => 0]);
    ProductImage::create(['product_id' => $this->product->id, 'product_variant_id' => $this->old->id, 'url' => 'https://cdn.example.com/lama.png', 'sort_order' => 1]);

    $images = $this->getJson('/api/v1/products/baterai-lithium-phylion')->json('data.images');

    expect(array_column($images, 'url'))->toBe(['https://cdn.example.com/0.png', 'https://cdn.example.com/2.png'])
        ->and($images[1]['variant_id'])->toBe($this->big->id);
});

test('missing, inactive, or variant-less products answer 404 PRODUCT_NOT_FOUND', function (Closure $setup, string $slug) {
    $setup($this);

    assertApiError($this->getJson("/api/v1/products/{$slug}"), 404, 'PRODUCT_NOT_FOUND')
        ->assertJsonPath('message', 'Produk tidak ditemukan atau sudah tidak dijual.');
})->with([
    'slug tidak ada' => [fn () => null, 'tidak-ada'],
    'produk nonaktif' => [fn ($test) => $test->product->update(['is_active' => false]), 'baterai-lithium-phylion'],
    'semua varian nonaktif' => [fn ($test) => $test->product->variants()->update(['is_active' => false]), 'baterai-lithium-phylion'],
]);
