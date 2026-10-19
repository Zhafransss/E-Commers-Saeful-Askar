<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Holds only user + variant + qty; price and stock always come from the variant.
 */
class CartItem extends Model
{
    public const ISSUE_PRODUCT_INACTIVE = 'PRODUCT_INACTIVE';

    public const ISSUE_OUT_OF_STOCK = 'OUT_OF_STOCK';

    public const ISSUE_QTY_EXCEEDS_STOCK = 'QTY_EXCEEDS_STOCK';

    protected $fillable = [
        'product_variant_id',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
        ];
    }

    /**
     * Why this line cannot be checked out as is (cart.md CART-01), or null.
     * Problem lines stay in the cart so the customer decides what to do.
     * Expects `variant.product` to be loaded.
     */
    public function issue(): ?string
    {
        $variant = $this->variant;

        return match (true) {
            ! $variant->is_active || ! $variant->product->is_active => self::ISSUE_PRODUCT_INACTIVE,
            $variant->sellableQty() === 0 => self::ISSUE_OUT_OF_STOCK,
            $this->qty > $variant->sellableQty() => self::ISSUE_QTY_EXCEEDS_STOCK,
            default => null,
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
