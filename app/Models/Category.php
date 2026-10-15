<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Copy of a Jubelio item category; written only by the catalog sync.
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'jubelio_category_id',
        'parent_id',
        'name',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The category and every category below it. A parent category also shows
     * its children's products (CAT-02 `category`, CAT-01 `product_count`).
     *
     * @param  Collection<int, self>  $all  Every category (id, parent_id)
     * @return list<int>
     */
    public static function withDescendantIds(int $id, Collection $all): array
    {
        $children = $all->groupBy('parent_id');
        $ids = [];
        $queue = [$id];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (in_array($current, $ids, true)) {
                continue;
            }
            $ids[] = $current;
            foreach ($children->get($current, []) as $child) {
                $queue[] = $child->id;
            }
        }

        return $ids;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
