<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Copy of a Jubelio SKU. Never deleted when it disappears from Jubelio
 * (is_active = false) so old orders and carts keep resolving.
 */
class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'jubelio_item_id',
        'sku',
        'name',
        'variation_values',
        'price',
        'available_qty',
        'package_weight',
        'barcode',
        'image_url',
        'is_bundle',
        'is_active',
        'stock_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'variation_values' => 'array',
            'price' => 'integer',
            'available_qty' => 'integer',
            'package_weight' => 'integer',
            'is_bundle' => 'boolean',
            'is_active' => 'boolean',
            'stock_synced_at' => 'datetime',
        ];
    }

    /**
     * Stock as shown to customers; Jubelio may report negative quantities.
     */
    public function sellableQty(): int
    {
        return max(0, $this->available_qty);
    }

    /**
     * Variant weight, falling back to the item group weight (INT-JB-05).
     */
    public function effectiveWeight(): int
    {
        return $this->package_weight ?: (int) $this->product?->package_weight;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }
}
