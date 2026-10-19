<?php

namespace App\Jobs\Jubelio;

use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Jobs\Concerns\RetriesIntegrationCalls;
use App\Models\SyncLog;
use App\Services\Jubelio\StockSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refreshes stock of some Jubelio items in the queue (WH-04, INT-JB-06).
 *
 * Like SyncProductGroupJob, the WH-04 handler calls StockSyncService directly;
 * this job is for background refreshes outside a webhook.
 */
class SyncStockJob implements ShouldQueue
{
    use Queueable, RetriesIntegrationCalls;

    /**
     * @param  list<int>  $jubelioItemIds
     */
    public function __construct(
        public array $jubelioItemIds,
        public SyncTrigger $trigger = SyncTrigger::Manual,
    ) {}

    public function handle(StockSyncService $stock): void
    {
        SyncLog::track(SyncType::Stock, $this->trigger, fn () => $stock->syncItems($this->jubelioItemIds));
    }
}
