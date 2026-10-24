<?php

namespace App\Services\Jubelio;

use App\Models\ProductVariant;
use App\Services\Jubelio\Concerns\RetriesSyncCalls;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Copies available stock of the website's warehouse (JUBELIO_LOCATION_ID) into
 * product_variants.available_qty (INT-JB-06). Used by WH-04 for a few SKUs and
 * by `jubelio:reconcile-stock` for all of them.
 *
 * On the real account location_stocks also lists location 0 next to the
 * warehouse, and ids arrive as integers; both are compared as integers.
 */
class StockSyncService
{
    use RetriesSyncCalls;

    public function __construct(
        private readonly JubelioClient $jubelio,
        private readonly ?int $locationId,
    ) {}

    /**
     * Refreshes the given Jubelio item ids that exist locally.
     *
     * @param  list<int>  $jubelioItemIds
     * @return int Number of variants refreshed
     */
    public function syncItems(array $jubelioItemIds): int
    {
        return $this->syncVariants(ProductVariant::whereIn('jubelio_item_id', $jubelioItemIds)->get());
    }

    /**
     * One INT-JB-06 search per SKU, matched on item_code exactly.
     *
     * @param  Collection<int, ProductVariant>  $variants
     */
    public function syncVariants(Collection $variants): int
    {
        $location = $this->location();
        $refreshed = 0;

        foreach ($variants as $variant) {
            $rows = $this->jubelio->inventory(1, 200, $variant->sku)['data'];
            $row = collect($rows)->first(fn (array $row) => (string) ($row['item_code'] ?? '') === $variant->sku);

            // Not in Jubelio's stock list at all: leave it to the product sync.
            if ($row === null) {
                continue;
            }

            $this->store($variant, $this->available($row, $location));
            $refreshed++;
        }

        return $refreshed;
    }

    /**
     * Reads every stock page and updates all known variants (backup for missed
     * update-qty webhooks, every 15 minutes).
     *
     * @return int Number of variants refreshed
     */
    public function reconcile(): int
    {
        $location = $this->location();
        $stock = [];
        $page = 1;

        do {
            $result = $this->retrying(fn () => $this->jubelio->inventory($page));
            foreach ($result['data'] as $row) {
                $stock[(int) $row['item_id']] = $this->available($row, $location);
            }
            $page++;
        } while ($result['data'] !== [] && count($stock) < $result['totalCount']);

        $refreshed = 0;
        ProductVariant::whereIn('jubelio_item_id', array_keys($stock))->each(function (ProductVariant $variant) use ($stock, &$refreshed): void {
            $this->store($variant, $stock[$variant->jubelio_item_id]);
            $refreshed++;
        });

        return $refreshed;
    }

    /**
     * Available stock at the warehouse; an item without a row there has none.
     *
     * @param  array<string, mixed>  $row
     */
    private function available(array $row, int $location): int
    {
        foreach ($row['location_stocks'] ?? [] as $stock) {
            if ((int) ($stock['location_id'] ?? 0) === $location) {
                return (int) round((float) ($stock['available'] ?? 0));
            }
        }

        return 0;
    }

    private function store(ProductVariant $variant, int $available): void
    {
        $variant->forceFill(['available_qty' => $available, 'stock_synced_at' => now()])->save();
    }

    private function location(): int
    {
        if ($this->locationId === null) {
            throw new RuntimeException('JUBELIO_LOCATION_ID belum diisi di .env; jalankan php artisan jubelio:setup.');
        }

        return $this->locationId;
    }
}
