<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Contract: docs/api/admin.md. Products come from Jubelio, so they are only listed here.
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            ->with(['category:id,name', 'variants' => fn ($q) => $q->orderBy('id')])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->when(trim((string) ($filters['q'] ?? '')), function ($query, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('brand_name', 'like', $like)
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like)->orWhere('name', 'like', $like)));
            })
            ->latest('jubelio_last_modified')->latest('id')
            ->paginate($request->integer('per_page', 20));

        return AdminProductResource::collection($products)->response();
    }
}
