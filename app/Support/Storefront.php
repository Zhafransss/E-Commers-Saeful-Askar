<?php

namespace App\Support;

use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Http\Resources\Api\V1\ProductSummaryResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Catalog data for server-rendered Blade pages (product detail, home, related
 * products), read from the local copy of the catalog like the API does and
 * shaped by the same resources, so a page sees exactly the CAT-02/CAT-03 objects.
 */
class Storefront
{
    /**
     * ProductSummary objects (CAT-02), newest first. A product from the same category
     * as $prefer comes first when given, and $except is left out.
     *
     * @return list<array<string, mixed>>
     */
    public static function summaries(int $limit, ?int $preferCategoryId = null, ?int $exceptProductId = null): array
    {
        $products = Product::visible()
            ->select('products.*')
            ->withVariantSummary()
            ->with('category:id,name,slug')
            ->when($exceptProductId, fn ($query) => $query->where('products.id', '!=', $exceptProductId))
            ->when($preferCategoryId, fn ($query) => $query->orderByRaw('category_id = ? desc', [$preferCategoryId]))
            ->orderByDesc('jubelio_last_modified')
            ->orderByDesc('products.id')
            ->limit($limit)
            ->get();

        return self::toArray(ProductSummaryResource::collection($products));
    }

    /**
     * ProductDetail object (CAT-03), or null when the product is hidden or unknown.
     *
     * @return array<string, mixed>|null
     */
    public static function detail(string $slug): ?array
    {
        $product = Product::visible()
            ->where('slug', $slug)
            ->with(['category', 'activeVariants' => fn ($variants) => $variants->orderBy('id'), 'images'])
            ->first();

        if ($product === null) {
            return null;
        }

        // Variants read the product's weight as a fallback; reuse the loaded model.
        $product->activeVariants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $product));

        return self::toArray(new ProductDetailResource($product));
    }

    /**
     * Resources keep nested resources as objects until they are encoded; going through
     * the response gives plain arrays that Blade and @js can use.
     *
     * @return array<mixed>
     */
    private static function toArray(JsonResource $resource): array
    {
        return $resource->response()->getData(true)['data'];
    }
}
