<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Jubelio\ProductSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| Catalog sync from Jubelio (INT-JB-02 – INT-JB-05, roadmap Fase 2), against
| fixtures shaped like the store's real account.
*/

beforeEach(fn () => fakeJubelioCatalog());

function catalogSync(): ProductSyncService
{
    return app(ProductSyncService::class);
}

// --- Categories (INT-JB-02) ---------------------------------------------------

test('categories are copied with their tree, even when a child arrives before its parent', function () {
    expect(catalogSync()->syncCategories())->toBe(4);

    $battery = Category::where('jubelio_category_id', 1000000002)->sole();
    $sparepart = Category::where('jubelio_category_id', 1000000001)->sole();

    expect($battery)->name->toBe('Baterai / AKi')->slug->toBe('baterai-aki')->parent_id->toBe($sparepart->id)
        ->and($sparepart->parent_id)->toBeNull()
        ->and(Category::where('is_active', true)->count())->toBe(4);
});

test('a category slug is made once and kept when the name changes in Jubelio', function () {
    catalogSync()->syncCategories();

    $renamed = fixture('jubelio/catalog/categories.json');
    $renamed['data'][0]['category_name'] = 'Baterai Sepeda Listrik';
    fakeJubelioCatalog(['/inventory/categories/item-categories/' => Http::response($renamed)]);
    catalogSync()->syncCategories();

    expect(Category::where('jubelio_category_id', 1000000002)->sole())
        ->name->toBe('Baterai Sepeda Listrik')
        ->slug->toBe('baterai-aki');
});

test('a category gone from Jubelio is deleted, unless products still use it: then it is only deactivated', function () {
    catalogSync()->syncCategories();
    $used = Category::where('jubelio_category_id', 1000000004)->sole();
    Product::factory()->for($used)->create();

    $fewer = fixture('jubelio/catalog/categories.json');
    $fewer['data'] = array_slice($fewer['data'], 0, 2);
    fakeJubelioCatalog(['/inventory/categories/item-categories/' => Http::response($fewer)]);
    catalogSync()->syncCategories();

    expect($used->fresh()->is_active)->toBeFalse()
        ->and(Category::where('jubelio_category_id', 1000000003)->exists())->toBeFalse();
});

// --- Full sync (INT-JB-03 – INT-JB-06) -----------------------------------------

test('a full sync copies products, bundles, variants, images, weights, and warehouse stock', function () {
    catalogSync()->syncCategories();

    expect(catalogSync()->syncAll())->toBe(3);

    $battery = Product::where('jubelio_item_group_id', 18)->sole();
    expect($battery)
        ->name->toBe('Baterai Lithium Phylion')
        ->slug->toBe('baterai-lithium-phylion')
        ->category->name->toBe('Baterai / AKi')
        ->is_active->toBeTrue()
        ->min_qty->toBeNull()
        ->max_qty->toBeNull()
        ->package_weight->toBe(8800)
        ->synced_at->not->toBeNull()
        ->and($battery->description_html)->not->toContain('<script');

    expect(ProductVariant::where('sku', 'SC000384N')->sole())
        ->product_id->toBe($battery->id)
        ->price->toBe(4500000)
        ->package_weight->toBe(27500)
        ->available_qty->toBe(3)
        ->is_active->toBeTrue();

    // Bundles come from /inventory/item-bundles/, not /inventory/items/.
    expect(ProductVariant::where('sku', '72V24Ah-10A-M23')->sole())
        ->is_bundle->toBeTrue()
        ->package_weight->toBeNull()
        ->available_qty->toBe(-2)
        ->sellableQty()->toBe(0)
        ->effectiveWeight()->toBe(100);

    expect(ProductImage::where('product_id', $battery->id)->orderBy('sort_order')->pluck('jubelio_image_id')->all())->toBe([301, 302, 303]);
});

test('stock only counts the website warehouse; location 0 is ignored', function () {
    catalogSync()->syncAll();

    // SC000384N has 9 at location 0 and 3 at -1; SD000383N only has stock at location 0.
    expect(ProductVariant::where('sku', 'SC000384N')->value('available_qty'))->toBe(3)
        ->and(ProductVariant::where('sku', 'SD000383N')->value('available_qty'))->toBe(0);
});

test('the website store price from prices[] wins over sell_price', function () {
    catalogSync()->syncAll();

    expect(ProductVariant::where('sku', 'SD000383N')->value('price'))->toBe(70000);
});

test('a product no longer listed by Jubelio is deactivated, not deleted', function () {
    catalogSync()->syncAll();

    $items = fixture('jubelio/catalog/items.json');
    $items['data'] = [$items['data'][1]];
    $items['totalCount'] = 1;
    fakeJubelioCatalog(['/inventory/items/' => Http::response($items)]);
    catalogSync()->syncAll();

    expect(Product::where('jubelio_item_group_id', 19)->sole()->is_active)->toBeFalse()
        ->and(Product::visible()->pluck('jubelio_item_group_id')->sort()->values()->all())->toBe([18, 25]);
});

test('running the full sync twice changes nothing and creates no duplicates', function () {
    catalogSync()->syncAll();
    catalogSync()->syncAll();

    expect(Product::count())->toBe(3)
        ->and(ProductVariant::count())->toBe(4)
        ->and(ProductImage::count())->toBe(4);
});

test('the full sync reads every page until totalCount', function () {
    $page1 = fixture('jubelio/catalog/items.json');
    $page2 = $page1;
    $page1['data'] = [$page1['data'][0]];
    $page2['data'] = [$page2['data'][1]];

    fakeJubelioCatalog(['/inventory/items/' => function ($request) use ($page1, $page2) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return Http::response((int) $query['page'] === 1 ? $page1 : $page2);
    }]);

    expect(catalogSync()->syncAll())->toBe(3);
});

// --- One item group (WH-02 / WH-03) ---------------------------------------------

test('syncing one group updates name, price, and weight, but keeps the slug', function () {
    catalogSync()->syncAll();

    $changed = fixture('jubelio/catalog/item-group-18.json');
    $changed['item_group_name'] = 'Baterai Phylion Baru';
    $changed['product_skus'][1]['sell_price'] = '4750000.0000';
    fakeJubelioCatalog(['/inventory/items/group/18' => Http::response($changed)]);

    catalogSync()->syncGroup(18);

    expect(Product::where('jubelio_item_group_id', 18)->sole())
        ->name->toBe('Baterai Phylion Baru')
        ->slug->toBe('baterai-lithium-phylion')
        ->and(ProductVariant::where('sku', 'SC000384N')->value('price'))->toBe(4750000);
});

test('a SKU removed in Jubelio is deactivated and its images are dropped', function () {
    catalogSync()->syncAll();

    $fewer = fixture('jubelio/catalog/item-group-18.json');
    array_pop($fewer['product_skus']);
    fakeJubelioCatalog(['/inventory/items/group/18' => Http::response($fewer)]);
    catalogSync()->syncGroup(18);

    expect(ProductVariant::where('sku', 'SC000384N')->sole()->is_active)->toBeFalse()
        ->and(ProductVariant::where('sku', 'SC000381N')->sole()->is_active)->toBeTrue()
        ->and(ProductImage::whereIn('jubelio_image_id', [302, 303])->exists())->toBeFalse();
});

test('an item group Jubelio no longer has (404) is deactivated', function () {
    catalogSync()->syncAll();
    fakeJubelioCatalog(['/inventory/items/group/18' => Http::response(['message' => 'Not Found'], 404)]);

    expect(catalogSync()->syncGroup(18))->toBeNull()
        ->and(Product::where('jubelio_item_group_id', 18)->sole()->is_active)->toBeFalse();
});

test('a group archived in Jubelio stays in the database but disappears from the storefront', function () {
    catalogSync()->syncAll();
    fakeJubelioCatalog(['/inventory/items/group/18' => Http::response(['is_active' => false] + fixture('jubelio/catalog/item-group-18.json'))]);

    catalogSync()->syncGroup(18);

    expect(Product::where('jubelio_item_group_id', 18)->sole()->is_active)->toBeFalse()
        ->and(Product::visible()->where('jubelio_item_group_id', 18)->exists())->toBeFalse();
});

test('a new group gets a unique slug when another product already uses its name', function () {
    Product::factory()->create(['slug' => 'baterai-lithium-phylion']);

    catalogSync()->syncGroup(18);

    expect(Product::where('jubelio_item_group_id', 18)->value('slug'))->toBe('baterai-lithium-phylion-2');
});

test('a SKU code reused by a new Jubelio item renames the old inactive row instead of failing', function () {
    $old = ProductVariant::factory()->create(['sku' => 'SC000384N', 'jubelio_item_id' => 9999]);

    catalogSync()->syncGroup(18);

    expect($old->fresh())->sku->toBe('SC000384N~9999')->is_active->toBeFalse()
        ->and(ProductVariant::where('sku', 'SC000384N')->sole()->jubelio_item_id)->toBe(19);
});

test('a group sync also refreshes stock of its SKUs with exact SKU matches', function () {
    catalogSync()->syncGroup(19);

    // The search for SD000383N also returns SD000383N-X (50 in stock): it must not be used.
    expect(ProductVariant::where('sku', 'SD000383N')->value('available_qty'))->toBe(0);
});

// --- Incremental sync ----------------------------------------------------------

test('without a previous run, the incremental sync runs in full', function () {
    expect(catalogSync()->syncChanged())->toBe(3)
        ->and(Cache::get('jubelio:products:listed-at'))->not->toBeNull();
});

test('the incremental sync only reads groups modified after the previous run, newest first', function () {
    Cache::forever('jubelio:products:listed-at', '2026-10-13T14:00:00+07:00');

    expect(catalogSync()->syncChanged())->toBe(1);

    // 19 changed 2026-10-14 04:42 WIB; 18 (2026-10-13 13:41 WIB) and bundle 25 (2026-10-10) did not.
    expect(Product::pluck('jubelio_item_group_id')->all())->toBe([19]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/inventory/items/?')
        && str_contains($request->url(), 'sortBy=last_modified')
        && str_contains($request->url(), 'sortDirection=DESC'));
});

test('the incremental sync with no changes calls no item group detail', function () {
    Cache::forever('jubelio:products:listed-at', '2026-10-20T00:00:00+07:00');

    expect(catalogSync()->syncChanged())->toBe(0)
        ->and(collect(jubelioCalls())->filter(fn ($path) => str_starts_with($path, '/inventory/items/group/')))->toBeEmpty();
});

// --- Cache ---------------------------------------------------------------------

test('every sync drops the cached category list', function () {
    Cache::put('catalog:categories:non-empty', ['lama'], 600);

    catalogSync()->syncGroup(19);

    expect(Cache::has('catalog:categories:non-empty'))->toBeFalse();

    Cache::put('catalog:categories:all', ['lama'], 600);
    catalogSync()->syncCategories();

    expect(Cache::has('catalog:categories:all'))->toBeFalse();
});
