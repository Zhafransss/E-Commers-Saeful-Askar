<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\ApiResource;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * ProductSummary object (docs/api/README.md). The aggregates come from the
 * active variants, selected by Product::scopeWithVariantSummary().
 *
 * @mixin Product
 */
class ProductSummaryResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $available = (int) $this->total_available_qty;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ],
            'thumbnail_url' => $this->thumbnail_url,
            'price_min' => (int) $this->price_min,
            'price_max' => (int) $this->price_max,
            'total_available_qty' => $available,
            'in_stock' => $available > 0,
            'variant_count' => (int) $this->variant_count,
        ];
    }
}
