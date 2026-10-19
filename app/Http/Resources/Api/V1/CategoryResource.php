<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\ApiResource;
use App\Models\Category;
use Illuminate\Http\Request;

/**
 * Category object (docs/api/README.md). `product_count` is set by CAT-01 and
 * counts visible products of the category and its children.
 *
 * @mixin Category
 */
class CategoryResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'product_count' => (int) $this->product_count,
        ];
    }
}
