<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Copy of a Jubelio item group; written only by the catalog sync.
 * Price range and stock totals are computed from active variants at query time.
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'jubelio_item_group_id',
        'category_id',
        'name',
        'slug',
        'brand_name',
        'description_html',
        'sell_unit',
        'min_qty',
        'max_qty',
        'package_weight',
        'package_length',
        'package_width',
        'package_height',
        'variations',
        'thumbnail_url',
        'is_active',
        'jubelio_last_modified',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'variations' => 'array',
            'is_active' => 'boolean',
            'jubelio_last_modified' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Visible on the storefront: active and with at least one active variant (catalog.md).
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('variants', fn (Builder $variants) => $variants->where('is_active', true));
    }

    /**
     * Adds price_min, price_max, total_available_qty (negative stock counted as
     * 0), and variant_count from active variants, as subqueries (ProductSummary).
     */
    public function scopeWithVariantSummary(Builder $query): void
    {
        $active = fn (string $aggregate) => ProductVariant::query()
            ->selectRaw($aggregate)
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('product_variants.is_active', true);

        $query->addSelect([
            'price_min' => $active('MIN(price)'),
            'price_max' => $active('MAX(price)'),
            'total_available_qty' => $active('COALESCE(SUM(GREATEST(available_qty, 0)), 0)'),
            'variant_count' => $active('COUNT(*)'),
        ]);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }
}
