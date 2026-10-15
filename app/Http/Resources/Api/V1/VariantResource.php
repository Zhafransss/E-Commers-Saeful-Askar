<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\ApiResource;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

/**
 * Variant object (docs/api/README.md). Stock never goes below 0, and the
 * weight falls back to the item group weight (INT-JB-05).
 *
 * @mixin ProductVariant
 */
class VariantResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $available = $this->sellableQty();

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'variation_values' => $this->variation_values ?? [],
            'price' => $this->price,
            'available_qty' => $available,
            'in_stock' => $available > 0,
            'package_weight' => $this->effectiveWeight() ?: null,
            'image_url' => $this->image_url,
        ];
    }
}
