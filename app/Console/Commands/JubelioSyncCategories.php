<?php

namespace App\Console\Commands;

use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Exceptions\IntegrationException;
use App\Models\SyncLog;
use App\Services\Jubelio\ProductSyncService;
use Illuminate\Console\Command;

/**
 * INT-JB-02 · Nightly, and before the first product sync.
 */
class JubelioSyncCategories extends Command
{
    protected $signature = 'jubelio:sync-categories';

    protected $description = 'Salin kategori dari Jubelio';

    public function handle(ProductSyncService $sync): int
    {
        try {
            $log = SyncLog::track(SyncType::Categories, SyncTrigger::Schedule, fn () => $sync->syncCategories());
        } catch (IntegrationException $e) {
            $this->components->error('Sinkron kategori gagal: '.$e->detail());

            return self::FAILURE;
        }

        $this->components->info("{$log->items_processed} kategori disinkron.");

        return self::SUCCESS;
    }
}
