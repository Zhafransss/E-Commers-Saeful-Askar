<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListCategoriesRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogCache;
use Illuminate\Http\JsonResponse;

/**
 * Contract: docs/api/catalog.md
 */
class CategoryController extends Controller
{
    /**
     * CAT-01 · Daftar kategori
     */
    public function index(ListCategoriesRequest $request): JsonResponse
    {
        $withEmpty = $request->boolean('with_empty');

        $data = CatalogCache::categories($withEmpty, function () use ($withEmpty, $request): array {
            $all = Category::orderBy('name')->get();
            $direct = Product::visible()->whereNotNull('category_id')
                ->groupBy('category_id')
                ->selectRaw('category_id, COUNT(*) AS total')
                ->pluck('total', 'category_id');

            $categories = $all
                ->where('is_active', true)
                ->each(fn (Category $category) => $category->product_count = collect(Category::withDescendantIds($category->id, $all))
                    ->sum(fn (int $id) => (int) ($direct[$id] ?? 0)))
                ->filter(fn (Category $category) => $withEmpty || $category->product_count > 0)
                ->values();

            return CategoryResource::collection($categories)->resolve($request);
        });

        return response()->json(['data' => $data]);
    }
}
