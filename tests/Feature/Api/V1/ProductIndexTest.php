<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Http;

/*
| CAT-02 · GET /api/v1/products (docs/api/catalog.md)
*/

/**
 * @param  list<array<string, mixed>>  $variants
 * @param  array<string, mixed>  $attributes
 */
function productWith(array $variants, array $attributes = []): Product
{
    $product = Product::factory()->create($attributes);
    foreach ($variants as $variant) {
        ProductVariant::factory()->for($product)->create($variant);
    }

    return $product;
}

/**
 * @return list<string>
 */
function listedSlugs(string $query = ''): array
{
    return collect(test()->getJson('/api/v1/products'.$query)->assertOk()->json('data'))->pluck('slug')->all();
}

test('lists visible products as ProductSummary with the contract meta block', function () {
    $category = Category::factory()->create(['name' => 'Baterai / AKi', 'slug' => 'baterai-aki']);
    $product = productWith([
        ['price' => 4500000, 'available_qty' => 3],
        ['price' => 2500000, 'available_qty' => 2],
        ['price' => 1000, 'available_qty' => 99, 'is_active' => false],
    ], ['name' => 'Baterai Lithium Phylion', 'slug' => 'baterai-lithium-phylion', 'category_id' => $category->id, 'thumbnail_url' => 'https://cdn.example.com/t.png']);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertExactJson([
            'data' => [[
                'id' => $product->id,
                'slug' => 'baterai-lithium-phylion',
                'name' => 'Baterai Lithium Phylion',
                'category' => ['id' => $category->id, 'name' => 'Baterai / AKi', 'slug' => 'baterai-aki'],
                'thumbnail_url' => 'https://cdn.example.com/t.png',
                'price_min' => 2500000,
                'price_max' => 4500000,
                'total_available_qty' => 5,
                'in_stock' => true,
                'variant_count' => 2,
            ]],
            'meta' => ['current_page' => 1, 'per_page' => 12, 'total' => 1, 'last_page' => 1],
        ]);
});

test('inactive products and products without an active variant are not listed', function () {
    productWith([[]], ['slug' => 'tampil']);
    productWith([[]], ['slug' => 'nonaktif', 'is_active' => false]);
    productWith([['is_active' => false]], ['slug' => 'varian-nonaktif']);

    expect(listedSlugs())->toBe(['tampil']);
});

test('a single-variant product has the same min and max price; negative stock counts as 0', function () {
    productWith([['price' => 75000, 'available_qty' => -2]]);

    expect($this->getJson('/api/v1/products')->json('data.0'))
        ->price_min->toBe(75000)
        ->price_max->toBe(75000)
        ->total_available_qty->toBe(0)
        ->in_stock->toBeFalse();
});

test('out-of-stock products stay listed unless in_stock=1', function () {
    productWith([['available_qty' => 0]], ['slug' => 'habis']);
    productWith([['available_qty' => 0], ['available_qty' => 1]], ['slug' => 'ada']);

    expect(listedSlugs())->toEqualCanonicalizing(['habis', 'ada'])
        ->and(listedSlugs('?in_stock=1'))->toBe(['ada']);
});

test('q searches the product name, the variant name, and the exact SKU', function () {
    productWith([['sku' => 'SC000384N', 'name' => 'Varian 72V 24Ah']], ['slug' => 'phylion', 'name' => 'Baterai Lithium Phylion']);
    productWith([['sku' => 'SD000383N', 'name' => 'Socket M23']], ['slug' => 'socket', 'name' => 'Socket Lihtium M23']);

    expect(listedSlugs('?q=phylion'))->toBe(['phylion'])
        ->and(listedSlugs('?q=72V'))->toBe(['phylion'])
        ->and(listedSlugs('?q=SD000383N'))->toBe(['socket'])
        ->and(listedSlugs('?q=SD0003'))->toBe([])
        ->and(listedSlugs('?q=tidak-ada'))->toBe([]);
});

test('the SKU of an inactive variant does not find the product', function () {
    productWith([['sku' => 'AKTIF-1'], ['sku' => 'LAMA-1', 'is_active' => false]], ['slug' => 'produk', 'name' => 'Produk']);

    expect(listedSlugs('?q=LAMA-1'))->toBe([])
        ->and(listedSlugs('?q=AKTIF-1'))->toBe(['produk']);
});

test('q treats % and _ literally', function () {
    productWith([[]], ['slug' => 'diskon', 'name' => 'Promo 50% Charger']);
    productWith([[]], ['slug' => 'lain', 'name' => 'Charger Biasa']);

    expect(listedSlugs('?q='.urlencode('50%')))->toBe(['diskon'])
        ->and(listedSlugs('?q=_'))->toBe([]);
});

test('category filters by slug, and a parent also shows its children\'s products', function () {
    $parent = Category::factory()->create(['slug' => 'sparepart']);
    $child = Category::factory()->create(['slug' => 'baterai-aki', 'parent_id' => $parent->id]);
    productWith([[]], ['slug' => 'di-induk', 'category_id' => $parent->id]);
    productWith([[]], ['slug' => 'di-anak', 'category_id' => $child->id]);
    productWith([[]], ['slug' => 'lain']);

    expect(listedSlugs('?category=sparepart'))->toEqualCanonicalizing(['di-induk', 'di-anak'])
        ->and(listedSlugs('?category=baterai-aki'))->toBe(['di-anak']);
});

test('an unknown category is not an error: the result is empty', function () {
    productWith([[]]);

    $this->getJson('/api/v1/products?category=tidak-ada')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});

test('the price range matches products with at least one variant inside it', function () {
    productWith([['price' => 2500000], ['price' => 4500000]], ['slug' => 'baterai']);
    productWith([['price' => 75000]], ['slug' => 'socket']);

    expect(listedSlugs('?price_min=3000000&price_max=5000000'))->toBe(['baterai'])
        ->and(listedSlugs('?price_max=100000'))->toBe(['socket'])
        ->and(listedSlugs('?price_min=100000'))->toBe(['baterai']);
});

test('sorting: latest by jubelio_last_modified then id, prices, and name', function () {
    productWith([['price' => 300]], ['slug' => 'b', 'name' => 'Beta', 'jubelio_last_modified' => '2026-10-01 10:00:00']);
    productWith([['price' => 100]], ['slug' => 'c', 'name' => 'Charlie', 'jubelio_last_modified' => '2026-10-03 10:00:00']);
    productWith([['price' => 200]], ['slug' => 'a', 'name' => 'Alfa', 'jubelio_last_modified' => '2026-10-03 10:00:00']);

    expect(listedSlugs())->toBe(['a', 'c', 'b'])
        ->and(listedSlugs('?sort=latest'))->toBe(['a', 'c', 'b'])
        ->and(listedSlugs('?sort=price_asc'))->toBe(['c', 'a', 'b'])
        ->and(listedSlugs('?sort=price_desc'))->toBe(['b', 'a', 'c'])
        ->and(listedSlugs('?sort=name_asc'))->toBe(['a', 'b', 'c']);
});

test('pagination follows page and per_page', function () {
    foreach (range(1, 5) as $i) {
        productWith([[]], ['slug' => "p{$i}", 'jubelio_last_modified' => now()->subMinutes($i)]);
    }

    $this->getJson('/api/v1/products?per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('meta', ['current_page' => 2, 'per_page' => 2, 'total' => 5, 'last_page' => 3])
        ->assertJsonPath('data.0.slug', 'p3');
});

test('invalid queries answer 422 VALIDATION_ERROR', function (string $query, string $field) {
    assertApiError($this->getJson('/api/v1/products?'.$query), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors($field);
})->with([
    'sort tidak dikenal' => ['sort=termurah', 'sort'],
    'per_page > 48' => ['per_page=49', 'per_page'],
    'price_max < price_min' => ['price_min=500&price_max=100', 'price_max'],
    'q > 100 karakter' => ['q='.str_repeat('a', 101), 'q'],
    'in_stock bukan boolean' => ['in_stock=ya', 'in_stock'],
    'price_min negatif' => ['price_min=-1', 'price_min'],
]);

test('an unknown sort uses the contract message', function () {
    $this->getJson('/api/v1/products?sort=termurah')->assertJsonPath('errors.sort.0', 'Pilihan urutan tidak dikenal.');
});

test('48 products answer in under 500 ms without calling Jubelio', function () {
    Http::fake();
    foreach (range(1, 48) as $i) {
        productWith([['price' => 1000 * $i], ['price' => 2000 * $i, 'available_qty' => 0]], ['slug' => "produk-{$i}"]);
    }

    $this->getJson('/api/v1/products?per_page=48'); // warm-up: route, config, and query plan caches
    $started = hrtime(true);
    $response = $this->getJson('/api/v1/products?per_page=48');
    $elapsedMs = (hrtime(true) - $started) / 1_000_000;

    $response->assertOk()->assertJsonCount(48, 'data');
    expect($elapsedMs)->toBeLessThan(500);
    Http::assertNothingSent();
});
