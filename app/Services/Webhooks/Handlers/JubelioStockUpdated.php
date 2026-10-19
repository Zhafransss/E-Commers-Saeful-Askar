<?php

namespace App\Services\Webhooks\Handlers;

use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Models\ProductVariant;
use App\Models\SyncLog;
use App\Models\WebhookEvent;
use App\Services\Jubelio\ProductSyncService;
use App\Services\Jubelio\StockSyncService;
use App\Services\Webhooks\WebhookEventIgnored;
use App\Services\Webhooks\WebhookHandler;

/**
 * WH-04 `update-qty`: refreshes stock of the listed items (INT-JB-06).
 * Stock moving in another warehouse is ignored. Items the website does not
 * know yet belong to a new item group, which is then synced in full.
 */
class JubelioStockUpdated implements WebhookHandler
{
    public function __construct(
        private readonly StockSyncService $stock,
        private readonly ProductSyncService $products,
    ) {}

    public function handle(WebhookEvent $event): void
    {
        $payload = $event->payload;
        $location = (int) $payload['location_id'];

        if ($location !== (int) config('jubelio.location_id')) {
            throw new WebhookEventIgnored("Stok berubah di gudang lain (location_id {$location}).");
        }

        $itemIds = array_map('intval', $payload['item_ids']);
        $known = ProductVariant::whereIn('jubelio_item_id', $itemIds)->pluck('jubelio_item_id')->all();
        $groupId = isset($payload['item_group_id']) ? (int) $payload['item_group_id'] : null;

        if (count($known) < count(array_unique($itemIds)) && $groupId !== null) {
            SyncLog::track(SyncType::Products, SyncTrigger::Webhook, function () use ($groupId): int {
                $this->products->syncGroup($groupId);

                return 1;
            });

            return;
        }

        if ($known === []) {
            throw new WebhookEventIgnored('Item belum ada di katalog website.');
        }

        SyncLog::track(SyncType::Stock, SyncTrigger::Webhook, fn () => $this->stock->syncItems($known));
    }
}
