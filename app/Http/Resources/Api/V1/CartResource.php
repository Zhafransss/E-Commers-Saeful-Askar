<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\ApiResource;
use App\Models\CartItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Cart object (docs/api/README.md "CartItem dan Cart"), built from the user's
 * cart lines with `variant.product` loaded (CartService::items()).
 *
 * Lines whose product was deactivated stay listed but count neither in the
 * subtotal nor in the weight used for shipping rates (CART-01, SHIP-01).
 *
 * @property Collection<int, CartItem> $resource
 */
class CartResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->resource->map(function (CartItem $item) use ($request): array {
            $variant = $item->variant;
            $product = $variant->product;

            return [
                'id' => $item->id,
                'variant' => (new VariantResource($variant))->toArray($request),
                'product' => [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'min_qty' => $product->min_qty,
                    'max_qty' => $product->max_qty,
                ],
                'qty' => $item->qty,
                'subtotal' => $variant->price * $item->qty,
                'weight_total' => $variant->effectiveWeight() * $item->qty,
                'issue' => $item->issue(),
            ];
        });

        $payable = $items->where('issue', '!==', CartItem::ISSUE_PRODUCT_INACTIVE);

        return [
            'items' => $items->values()->all(),
            'summary' => [
                'item_count' => $items->count(),
                'total_qty' => $items->sum('qty'),
                'subtotal' => $payable->sum('subtotal'),
                'total_weight' => $payable->sum('weight_total'),
                'has_issue' => $items->contains(fn (array $item) => $item['issue'] !== null),
            ],
        ];
    }
}
