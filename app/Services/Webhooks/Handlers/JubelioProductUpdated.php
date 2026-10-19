<?php

namespace App\Services\Webhooks\Handlers;

use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Models\SyncLog;
use App\Models\WebhookEvent;
use App\Services\Jubelio\ProductSyncService;
use App\Services\Webhooks\WebhookHandler;

/**
 * WH-02 `update-product` and WH-03 `update-price`: both re-read the whole item
 * group (INT-JB-04), since the price lives on the group's SKUs and an unknown
 * group must be created in full either way.
 */
class JubelioProductUpdated implements WebhookHandler
{
    public function __construct(private readonly ProductSyncService $sync) {}

    public function handle(WebhookEvent $event): void
    {
        $itemGroupId = (int) $event->payload['item_group_id'];

        SyncLog::track(SyncType::Products, SyncTrigger::Webhook, function () use ($itemGroupId): int {
            $this->sync->syncGroup($itemGroupId);

            return 1;
        });
    }
}
