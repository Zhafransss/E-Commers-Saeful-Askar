<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cached CAT-01 responses (at most 10 minutes), dropped after every category
 * or product sync so new data shows up right away (catalog.md CAT-01).
 * The `database` cache store has no tags, so the few keys are listed here.
 */
class CatalogCache
{
    private const TTL = 600;

    /**
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T
     */
    public static function categories(bool $withEmpty, Closure $build): mixed
    {
        return Cache::remember(self::categoriesKey($withEmpty), self::TTL, $build);
    }

    public static function flush(): void
    {
        Cache::forget(self::categoriesKey(true));
        Cache::forget(self::categoriesKey(false));
    }

    private static function categoriesKey(bool $withEmpty): string
    {
        return 'catalog:categories:'.($withEmpty ? 'all' : 'non-empty');
    }
}
