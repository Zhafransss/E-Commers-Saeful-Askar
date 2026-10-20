<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;

/*
| Black-box: CAT-01..04 (docs/api/catalog.md). Equivalence classes and boundaries of every query parameter.
*/

beforeEach(function () {
    $this->bikes = Category::factory()->create(['name' => 'Sepeda', 'slug' => 'sepeda']);
    $this->parts = Category::factory()->create(['name' => 'Aki', 'slug' => 'aki']);
    $this->a = bbProduct(['name' => 'Alpha Bike', 'slug' => 'alpha-bike', 'jubelio_last_modified' => now()->subDays(3)], [['sku' => 'A-1', 'name' => 'Alpha Hitam', 'price' => 1000000, 'available_qty' => 5]], $this->bikes);
    $this->b = bbProduct(['name' => 'Beta Bike', 'slug' => 'beta-bike', 'jubelio_last_modified' => now()->subDays(2)], [['sku' => 'B-1', 'price' => 3000000, 'available_qty' => 0], ['sku' => 'B-2', 'price' => 5000000, 'available_qty' => 2]], $this->bikes);
    $this->c = bbProduct(['name' => 'Gamma Aki', 'slug' => 'gamma-aki', 'jubelio_last_modified' => now()->subDay()], [['sku' => 'C-1', 'price' => 200000, 'available_qty' => 9]], $this->parts);
});

function bbSlugs($response): array
{
    return collect($response->json('data'))->pluck('slug')->all();
}

test('CAT-01 is public and returns the list envelope with pagination meta', function () {
    $response = $this->getJson('/api/v1/products')->assertOk();

    expect($response->json('meta'))->toMatchArray(['current_page' => 1, 'per_page' => 12, 'total' => 3, 'last_page' => 1])
        ->and($response->json('data.0'))->toHaveKeys(['id', 'slug', 'name', 'category', 'thumbnail_url', 'price_min', 'price_max', 'total_available_qty', 'in_stock', 'variant_count']);
});

test('CAT-01 default order is newest first and each sort option orders correctly', function (string $sort, array $expected) {
    expect(bbSlugs($this->getJson("/api/v1/products?sort={$sort}")->assertOk()))->toBe($expected);
})->with([
    'latest' => ['latest', ['gamma-aki', 'beta-bike', 'alpha-bike']],
    'price_asc' => ['price_asc', ['gamma-aki', 'alpha-bike', 'beta-bike']],
    'price_desc' => ['price_desc', ['beta-bike', 'alpha-bike', 'gamma-aki']],
    'name_asc' => ['name_asc', ['alpha-bike', 'beta-bike', 'gamma-aki']],
]);

test('CAT-01 price range matches a product when ANY active variant falls inside it', function () {
    // Beta has variants at 3,000,000 and 5,000,000; only the second is in range.
    expect(bbSlugs($this->getJson('/api/v1/products?price_min=4000000&price_max=6000000')))->toBe(['beta-bike']);
    // Boundaries are inclusive.
    expect(bbSlugs($this->getJson('/api/v1/products?price_min=1000000&price_max=1000000')))->toBe(['alpha-bike']);
    expect(bbSlugs($this->getJson('/api/v1/products?price_max=199999')))->toBe([]);
});

test('CAT-01 price range edge: price_max below price_min is a validation error', function () {
    assertApiError($this->getJson('/api/v1/products?price_min=500&price_max=100'), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('price_max');
    assertApiError($this->getJson('/api/v1/products?price_min=-1'), 422, 'VALIDATION_ERROR');
    assertApiError($this->getJson('/api/v1/products?price_min=abc'), 422, 'VALIDATION_ERROR');
});

test('CAT-01 in_stock keeps products with at least one active variant in stock', function () {
    ProductVariant::where('sku', 'A-1')->update(['available_qty' => 0]);

    expect(bbSlugs($this->getJson('/api/v1/products?in_stock=1')))->toBe(['gamma-aki', 'beta-bike']);
    expect($this->getJson('/api/v1/products?in_stock=0')->json('meta.total'))->toBe(3);
});

test('CAT-01 negative stock never counts as stock', function () {
    ProductVariant::where('sku', 'A-1')->update(['available_qty' => -3]);

    $row = collect($this->getJson('/api/v1/products')->json('data'))->firstWhere('slug', 'alpha-bike');
    expect($row['total_available_qty'])->toBe(0)->and($row['in_stock'])->toBeFalse();
    expect(bbSlugs($this->getJson('/api/v1/products?in_stock=1')))->not->toContain('alpha-bike');
});

test('CAT-01 category filter includes sub-categories; an unknown category is an empty list, not an error', function () {
    $child = Category::factory()->create(['parent_id' => $this->bikes->id, 'slug' => 'sepeda-lipat']);
    bbProduct(['slug' => 'lipat-1'], [[]], $child);

    expect(bbSlugs($this->getJson('/api/v1/products?category=sepeda')))->toContain('lipat-1', 'alpha-bike', 'beta-bike')->not->toContain('gamma-aki');
    expect($this->getJson('/api/v1/products?category=tidak-ada')->assertOk()->json('data'))->toBe([]);
});

test('CAT-01 search matches product name, variant name and exact SKU, case-insensitively', function () {
    expect(bbSlugs($this->getJson('/api/v1/products?q=ALPHA')))->toBe(['alpha-bike']);
    expect(bbSlugs($this->getJson('/api/v1/products?q=Hitam')))->toBe(['alpha-bike']);
    expect(bbSlugs($this->getJson('/api/v1/products?q=C-1')))->toBe(['gamma-aki']);
    // A partial SKU is not a SKU match.
    expect(bbSlugs($this->getJson('/api/v1/products?q=C-')))->toBe([]);
});

test('CAT-01 LIKE wildcards in the search term are literal characters', function () {
    expect($this->getJson('/api/v1/products?q=%25')->json('meta.total'))->toBe(0)
        ->and($this->getJson('/api/v1/products?q=_')->json('meta.total'))->toBe(0)
        ->and($this->getJson('/api/v1/products?q='.urlencode('\\'))->json('meta.total'))->toBe(0);
});

test('CAT-01 hostile search input is handled as plain text', function (string $q) {
    $this->getJson('/api/v1/products?q='.urlencode($q))->assertOk()->assertJsonPath('meta.total', 0);
    expect(Product::count())->toBe(3);
})->with(["' OR '1'='1", "'; DROP TABLE products;--", '<script>alert(1)</script>', "Sepeda\0Listrik", str_repeat('é', 100)]);

test('CAT-01 pagination boundaries: per_page 1..48 accepted, 0 and 49 refused, page past the end is empty', function () {
    $this->getJson('/api/v1/products?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.last_page', 3);
    $this->getJson('/api/v1/products?per_page=48')->assertOk();
    assertApiError($this->getJson('/api/v1/products?per_page=0'), 422, 'VALIDATION_ERROR');
    assertApiError($this->getJson('/api/v1/products?per_page=49'), 422, 'VALIDATION_ERROR');
    assertApiError($this->getJson('/api/v1/products?page=0'), 422, 'VALIDATION_ERROR');
    $this->getJson('/api/v1/products?page=99')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 3);
    assertApiError($this->getJson('/api/v1/products?sort=random'), 422, 'VALIDATION_ERROR');
    assertApiError($this->getJson('/api/v1/products?q='.str_repeat('a', 101)), 422, 'VALIDATION_ERROR');
});

test('CAT-01 hides inactive products, products without an active variant, and inactive variants from price and stock', function () {
    bbProduct(['slug' => 'mati', 'is_active' => false], [[]]);
    bbProduct(['slug' => 'tanpa-varian-aktif'], [['is_active' => false]]);
    ProductVariant::where('sku', 'B-2')->update(['is_active' => false]);

    $slugs = bbSlugs($this->getJson('/api/v1/products?per_page=48'));
    expect($slugs)->not->toContain('mati', 'tanpa-varian-aktif');

    $beta = collect($this->getJson('/api/v1/products')->json('data'))->firstWhere('slug', 'beta-bike');
    expect($beta['price_max'])->toBe(3000000)->and($beta['variant_count'])->toBe(1);
});

test('CAT-02 detail returns the product with active variants only, and 404 PRODUCT_NOT_FOUND otherwise', function () {
    ProductVariant::where('sku', 'B-2')->update(['is_active' => false]);

    $response = $this->getJson('/api/v1/products/beta-bike')->assertOk();
    expect(collect($response->json('data.variants'))->pluck('sku')->all())->toBe(['B-1'])
        ->and($response->json('data'))->toHaveKeys(['id', 'slug', 'name', 'category', 'description_html', 'variants', 'images', 'price_min', 'price_max', 'in_stock']);

    bbProduct(['slug' => 'mati', 'is_active' => false], [[]]);
    assertApiError($this->getJson('/api/v1/products/mati'), 404, 'PRODUCT_NOT_FOUND');
    assertApiError($this->getJson('/api/v1/products/tidak-ada'), 404, 'PRODUCT_NOT_FOUND');
    // A path-traversal slug never reaches a product: still a plain 404 in the contract error shape.
    bbExpectErrorShape($this->getJson('/api/v1/products/'.urlencode('../../etc/passwd'))->assertNotFound());
});

test('CAT-03 categories hide empty and inactive ones by default; with_empty shows empty ones; counts include descendants', function () {
    $child = Category::factory()->create(['parent_id' => $this->bikes->id, 'slug' => 'lipat']);
    bbProduct([], [[]], $child);
    Category::factory()->create(['slug' => 'kosong']);
    Category::factory()->create(['slug' => 'nonaktif', 'is_active' => false]);

    $default = collect($this->getJson('/api/v1/categories')->assertOk()->json('data'));
    expect($default->pluck('slug')->all())->not->toContain('kosong', 'nonaktif')
        ->and($default->firstWhere('slug', 'sepeda')['product_count'])->toBe(3);

    $all = collect($this->getJson('/api/v1/categories?with_empty=1')->json('data'))->pluck('slug');
    expect($all)->toContain('kosong')->not->toContain('nonaktif');
    assertApiError($this->getJson('/api/v1/categories?with_empty=maybe'), 422, 'VALIDATION_ERROR');
});

test('catalog endpoints reject non-GET methods', function (string $method, string $uri) {
    $this->json($method, $uri)->assertStatus(405);
})->with([['POST', '/api/v1/products'], ['DELETE', '/api/v1/products/alpha-bike'], ['PUT', '/api/v1/categories']]);
