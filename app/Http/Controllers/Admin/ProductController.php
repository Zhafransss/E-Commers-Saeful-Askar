<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminProductResource;
use App\Models\Product;
use App\Support\CatalogCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Contract: docs/api/admin.md. Products come from Jubelio, so they are only listed here; the one thing the
 * admin changes is whether customers see a product (is_hidden), which the Jubelio sync never touches.
 */
class ProductController extends Controller
{
    /**
     * ADM-20 · Daftar produk dan varian (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'visibility' => ['nullable', Rule::in(['shown', 'hidden'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            ->with(['category:id,name', 'variants' => fn ($q) => $q->orderBy('id')])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->when($filters['visibility'] ?? null, fn ($q, $visibility) => $q->where('is_hidden', $visibility === 'hidden'))
            ->when(trim((string) ($filters['q'] ?? '')), function ($query, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('brand_name', 'like', $like)
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like)->orWhere('name', 'like', $like)));
            })
            ->latest('jubelio_last_modified')->latest('id')
            ->paginate($request->integer('per_page', 20));

        return AdminProductResource::collection($products)->response();
    }

    /**
     * ADM-34 · Tampilkan atau sembunyikan produk dari pelanggan
     */
    public function visibility(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['is_hidden' => ['required', 'boolean']]);

        $product = Product::with(['category:id,name', 'variants' => fn ($q) => $q->orderBy('id')])->find($id)
            ?? throw new ApiException('Produk tidak ditemukan.', 'PRODUCT_NOT_FOUND', 404);
        $product->update(['is_hidden' => $data['is_hidden']]);
        CatalogCache::flush();

        return (new AdminProductResource($product))->response();
    }
}
