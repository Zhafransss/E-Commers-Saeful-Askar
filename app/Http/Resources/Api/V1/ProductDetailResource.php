<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\ApiResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

/**
 * ProductDetail object (docs/api/README.md, CAT-03). Expects `category`,
 * `activeVariants`, and `images` to be loaded.
 *
 * @mixin Product
 */
class ProductDetailResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variants = $this->activeVariants;
        $variantIds = $variants->pluck('id')->all();
        $prices = $variants->pluck('price');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ],
            'brand_name' => $this->brand_name,
            'description_html' => $this->description_html,
            'sell_unit' => $this->sell_unit,
            'min_qty' => $this->min_qty,
            'max_qty' => $this->max_qty,
            'package_dimensions' => [
                'length' => $this->package_length,
                'width' => $this->package_width,
                'height' => $this->package_height,
            ],
            // Images of a hidden variant are left out with the variant itself.
            'images' => $this->images
                ->filter(fn (ProductImage $image) => $image->product_variant_id === null || in_array($image->product_variant_id, $variantIds, true))
                ->values()
                ->map(fn (ProductImage $image) => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'thumbnail_url' => $image->thumbnail_url,
                    'variant_id' => $image->product_variant_id,
                    'sort_order' => $image->sort_order,
                ]),
            'variations' => $this->variations ?? [],
            'variants' => VariantResource::collection($variants),
            'price_min' => (int) $prices->min(),
            'price_max' => (int) $prices->max(),
            'in_stock' => $variants->contains(fn (ProductVariant $variant) => $variant->sellableQty() > 0),
            'updated_at' => $this->updated_at,
        ];
    }
}
