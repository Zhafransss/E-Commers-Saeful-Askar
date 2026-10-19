<?php

namespace App\Jobs\Jubelio;

use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Jobs\Concerns\RetriesIntegrationCalls;
use App\Models\SyncLog;
use App\Services\Jubelio\ProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-reads one Jubelio item group in the queue (WH-02, INT-JB-04 + INT-JB-05).
 *
 * Webhook handlers call ProductSyncService directly, so the webhook event's own
 * status reflects the outcome; this job is for work that should run in the
 * background on its own, such as a manual resync (ADM-13).
 */
class SyncProductGroupJob implements ShouldQueue
{
    use Queueable, RetriesIntegrationCalls;

    public function __construct(
        public int $itemGroupId,
        public SyncTrigger $trigger = SyncTrigger::Manual,
    ) {}

    public function handle(ProductSyncService $sync): void
    {
        SyncLog::track(SyncType::Products, $this->trigger, function () use ($sync): int {
            $sync->syncGroup($this->itemGroupId);

            return 1;
        });
    }
}
