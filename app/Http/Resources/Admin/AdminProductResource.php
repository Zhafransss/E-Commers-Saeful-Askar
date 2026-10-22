<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ApiResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

/**
 * One product with all its variants (active or not), ADM-20. Expects `category` and `variants` to be loaded.
 *
 * @mixin Product
 */
class AdminProductResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $active = $this->variants->where('is_active', true);

        return [
            'id' => $this->id,
            'jubelio_item_group_id' => $this->jubelio_item_group_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'brand_name' => $this->brand_name,
            'category' => $this->category?->name,
            'thumbnail_url' => $this->thumbnail_url,
            'is_active' => $this->is_active,
            'price_min' => $active->min('price'),
            'price_max' => $active->max('price'),
            'total_available_qty' => (int) $active->sum(fn (ProductVariant $variant) => $variant->sellableQty()),
            'variants' => $this->variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'price' => $variant->price,
                'available_qty' => $variant->available_qty,
                'package_weight' => $variant->effectiveWeight(),
                'image_url' => $variant->image_url,
                'is_active' => $variant->is_active,
            ])->values()->all(),
            'synced_at' => $this->synced_at,
        ];
    }
}
