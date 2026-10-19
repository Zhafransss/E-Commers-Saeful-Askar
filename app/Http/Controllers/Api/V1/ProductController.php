<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListProductsRequest;
use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Http\Resources\Api\V1\ProductSummaryResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Contract: docs/api/catalog.md. Reads only the local copy of the catalog;
 * never calls Jubelio per request.
 */
class ProductController extends Controller
{
    /**
     * CAT-02 · Daftar dan pencarian produk
     */
    public function index(ListProductsRequest $request): JsonResponse
    {
        $query = Product::visible()
            ->select('products.*')
            ->withVariantSummary()
            ->with('category:id,name,slug');

        if (filled($search = $request->validated('q'))) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $like)
                ->orWhereHas('activeVariants', fn (Builder $variants) => $variants->where(fn (Builder $match) => $match
                    ->where('name', 'like', $like)
                    ->orWhere('sku', $search))));
        }

        if (filled($slug = $request->validated('category'))) {
            $category = Category::where('slug', $slug)->first();
            // An unknown category is not an error: it simply matches nothing.
            $category === null
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('category_id', Category::withDescendantIds($category->id, Category::get(['id', 'parent_id'])));
        }

        if ($request->boolean('in_stock')) {
            $query->whereHas('activeVariants', fn (Builder $variants) => $variants->where('available_qty', '>', 0));
        }

        $priceMin = $request->validated('price_min');
        $priceMax = $request->validated('price_max');
        if ($priceMin !== null || $priceMax !== null) {
            // A product matches when one of its variants is inside the range.
            $query->whereHas('activeVariants', fn (Builder $variants) => $variants
                ->when($priceMin !== null, fn (Builder $q) => $q->where('price', '>=', $priceMin))
                ->when($priceMax !== null, fn (Builder $q) => $q->where('price', '<=', $priceMax)));
        }

        match ($request->validated('sort', 'latest')) {
            'price_asc' => $query->orderBy('price_min')->orderBy('products.id'),
            'price_desc' => $query->orderByDesc('price_max')->orderBy('products.id'),
            'name_asc' => $query->orderBy('products.name')->orderBy('products.id'),
            default => $query->orderByDesc('jubelio_last_modified')->orderByDesc('products.id'),
        };

        return ProductSummaryResource::collection($query->paginate((int) $request->validated('per_page', 12)))
            ->response();
    }

    /**
     * CAT-03 · Detail produk
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::visible()
            ->where('slug', $slug)
            ->with(['category', 'activeVariants' => fn ($variants) => $variants->orderBy('id'), 'images'])
            ->first();

        if ($product === null) {
            throw new ApiException('Produk tidak ditemukan atau sudah tidak dijual.', 'PRODUCT_NOT_FOUND', 404);
        }

        // Variants read the product's weight as a fallback; reuse the loaded model.
        $product->activeVariants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $product));

        return (new ProductDetailResource($product))->response();
    }
}
