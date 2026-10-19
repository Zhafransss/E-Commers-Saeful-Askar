<?php

namespace App\Services\Jubelio;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Jubelio\Mappers\ProductMapper;
use App\Support\CatalogCache;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies categories and products from Jubelio into the local catalog
 * (INT-JB-02 – INT-JB-05). The website never edits catalog data itself.
 *
 * Nothing is deleted that orders or carts may point to: products and variants
 * that disappear from Jubelio become is_active = false (WH-02). Slugs are made
 * once and never follow later name changes (CAT-03).
 *
 * HTTP calls happen before each database transaction, so a slow Jubelio never
 * holds a transaction open.
 */
class ProductSyncService
{
    /** Start of the last full or incremental run: the next incremental run reads changes after it. */
    private const LISTED_AT_KEY = 'jubelio:products:listed-at';

    public function __construct(
        private readonly JubelioClient $jubelio,
        private readonly ProductMapper $mapper,
        private readonly StockSyncService $stock,
    ) {}

    /**
     * INT-JB-02. A category gone from Jubelio is deleted, unless products still
     * use it: then it is only marked inactive.
     *
     * @return int Number of categories received
     */
    public function syncCategories(): int
    {
        $rows = collect($this->jubelio->categories())->filter(fn ($row) => isset($row['category_id']));

        DB::transaction(function () use ($rows): void {
            $localIds = [];

            foreach ($rows as $row) {
                $category = Category::firstOrNew(['jubelio_category_id' => (int) $row['category_id']]);
                $category->name = trim((string) $row['category_name']);
                $category->is_active = true;
                $category->slug ??= $this->uniqueSlug(Category::class, $category->name, 'kategori-'.$row['category_id']);
                $category->save();

                $localIds[(int) $row['category_id']] = $category->id;
            }

            // Second pass: a child may arrive before its parent.
            foreach ($rows as $row) {
                $parent = (int) ($row['parent_id'] ?? 0);
                Category::whereKey($localIds[(int) $row['category_id']])->update(['parent_id' => $localIds[$parent] ?? null]);
            }

            Category::whereNotIn('jubelio_category_id', array_keys($localIds))->get()
                ->each(fn (Category $category) => $category->products()->exists()
                    ? $category->update(['is_active' => false])
                    : $category->delete());
        });

        CatalogCache::flush();

        return $rows->count();
    }

    /**
     * Full sync: every item group (bundles included), then stock for all SKUs.
     * Groups no longer listed by Jubelio are deactivated.
     *
     * @return int Number of item groups synced
     */
    public function syncAll(): int
    {
        $startedAt = now();
        $groupIds = $this->listedGroupIds();
        $skuRows = $this->skuRows();

        foreach ($groupIds as $groupId) {
            $this->syncGroup($groupId, $skuRows, withStock: false);
        }

        Product::where('is_active', true)->whereNotIn('jubelio_item_group_id', $groupIds)->update(['is_active' => false]);

        $this->stock->reconcile();
        CatalogCache::flush();
        Cache::forever(self::LISTED_AT_KEY, $startedAt->toIso8601String());

        return count($groupIds);
    }

    /**
     * Incremental sync: item groups modified since the previous full or
     * incremental run started. Lists are read newest first and stop at the
     * first older group (INT-JB-03). Without a previous run it syncs in full.
     *
     * The baseline is kept in the cache rather than read from sync_logs, where
     * single-group webhook syncs would move it forward and hide other changes.
     *
     * @return int Number of item groups synced
     */
    public function syncChanged(): int
    {
        $since = Cache::get(self::LISTED_AT_KEY);

        if ($since === null) {
            return $this->syncAll();
        }

        $startedAt = now();
        $groupIds = $this->listedGroupIds(Carbon::parse($since));

        if ($groupIds !== []) {
            $skuRows = $this->skuRows();
            foreach ($groupIds as $groupId) {
                $this->syncGroup($groupId, $skuRows);
            }
            CatalogCache::flush();
        }

        Cache::forever(self::LISTED_AT_KEY, $startedAt->toIso8601String());

        return count($groupIds);
    }

    /**
     * One item group (INT-JB-04 + INT-JB-05), used by WH-02/WH-03 and the syncs.
     * A group Jubelio no longer has is deactivated; a new one is created with a
     * new slug.
     *
     * @param  array<int, array<string, mixed>>|null  $skuRows  INT-JB-05 rows by item_id; fetched when null
     * @param  bool  $withStock  Also refresh stock of its SKUs (INT-JB-06)
     */
    public function syncGroup(int $itemGroupId, ?array $skuRows = null, bool $withStock = true): ?Product
    {
        $group = $this->jubelio->itemGroup($itemGroupId);

        if ($group === null) {
            Product::where('jubelio_item_group_id', $itemGroupId)->update(['is_active' => false]);
            CatalogCache::flush();

            return null;
        }

        $skuRows ??= $this->skuRows();

        $product = DB::transaction(function () use ($itemGroupId, $group, $skuRows): Product {
            $product = Product::firstOrNew(['jubelio_item_group_id' => $itemGroupId]);
            $product->fill($this->mapper->product($group));
            $product->category_id = Category::where('jubelio_category_id', (int) ($group['item_category_id'] ?? 0))->value('id');
            $product->synced_at = now();
            $product->slug ??= $this->uniqueSlug(Product::class, $product->name, 'produk-'.$itemGroupId);
            $product->save();

            $variants = $this->mapper->variants($group, $skuRows);
            foreach ($variants as $itemId => $attributes) {
                $this->releaseSku($attributes['sku'], $itemId);
                ProductVariant::updateOrCreate(
                    ['jubelio_item_id' => $itemId],
                    $attributes + ['product_id' => $product->id, 'is_active' => true],
                );
            }

            // SKUs removed in Jubelio stay for old orders and carts, but hidden.
            $product->variants()->whereNotIn('jubelio_item_id', array_keys($variants))->update(['is_active' => false]);

            $this->syncImages($product, $this->mapper->images($group));

            return $product;
        });

        if ($withStock) {
            $this->stock->syncVariants($product->activeVariants()->get());
        }

        CatalogCache::flush();

        return $product;
    }

    /**
     * Item group ids from the item and bundle lists, newest first. With $since,
     * reading stops at the first group last modified at or before it.
     *
     * @return list<int>
     */
    private function listedGroupIds(?CarbonInterface $since = null): array
    {
        $ids = [];

        foreach (['items', 'bundles'] as $list) {
            $page = 1;
            $seen = 0;

            do {
                $result = $this->jubelio->{$list}($page, newestFirst: $since !== null);

                foreach ($result['data'] as $group) {
                    $seen++;

                    if ($since !== null && ! Carbon::parse($group['last_modified'])->isAfter($since)) {
                        continue 3;
                    }

                    $ids[] = (int) $group['item_group_id'];
                }

                $page++;
            } while ($result['data'] !== [] && $seen < $result['totalCount']);
        }

        return array_values(array_unique($ids));
    }

    /**
     * INT-JB-05: SKU name, weight, and bundle flag for every SKU, by item_id.
     *
     * @return array<int, array<string, mixed>>
     */
    private function skuRows(): array
    {
        $rows = [];
        $page = 1;

        do {
            $result = $this->jubelio->variations($page);
            foreach ($result['data'] as $row) {
                $rows[(int) $row['item_id']] = $row;
            }
            $page++;
        } while ($result['data'] !== [] && count($rows) < $result['totalCount']);

        return $rows;
    }

    /**
     * @param  list<array{jubelio_image_id: int, jubelio_item_id: int|null, url: string, thumbnail_url: string|null, sort_order: int}>  $images
     */
    private function syncImages(Product $product, array $images): void
    {
        $variantIds = $product->variants()->pluck('id', 'jubelio_item_id');

        foreach ($images as $image) {
            ProductImage::updateOrCreate(
                ['product_id' => $product->id, 'jubelio_image_id' => $image['jubelio_image_id']],
                [
                    'product_variant_id' => $image['jubelio_item_id'] === null ? null : $variantIds[$image['jubelio_item_id']] ?? null,
                    'url' => $image['url'],
                    'thumbnail_url' => $image['thumbnail_url'],
                    'sort_order' => $image['sort_order'],
                ],
            );
        }

        $product->images()->getQuery()
            ->where(fn ($query) => $query->whereNull('jubelio_image_id')
                ->orWhereNotIn('jubelio_image_id', array_column($images, 'jubelio_image_id')))
            ->delete();
    }

    /**
     * SKU codes are unique locally. When Jubelio reuses a code on a new item
     * (e.g. a SKU deleted and created again), the old row keeps its history
     * under a renamed, inactive code instead of failing the whole sync.
     */
    private function releaseSku(string $sku, int $jubelioItemId): void
    {
        ProductVariant::where('sku', $sku)->where('jubelio_item_id', '!=', $jubelioItemId)->get()
            ->each(fn (ProductVariant $old) => $old->update([
                'sku' => Str::limit($sku, 80, '').'~'.$old->jubelio_item_id,
                'is_active' => false,
            ]));
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function uniqueSlug(string $model, string $name, string $fallback): string
    {
        $base = Str::limit(Str::slug($name) ?: $fallback, 250, '');
        $slug = $base;
        $suffix = 2;

        while ($model::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
