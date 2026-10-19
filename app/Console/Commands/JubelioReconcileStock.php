<?php

namespace App\Console\Commands;

use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Exceptions\IntegrationException;
use App\Models\SyncLog;
use App\Services\Jubelio\StockSyncService;
use Illuminate\Console\Command;

/**
 * INT-JB-06 · Every 15 minutes, as the backup for missed update-qty webhooks (WH-04).
 */
class JubelioReconcileStock extends Command
{
    protected $signature = 'jubelio:reconcile-stock';

    protected $description = 'Samakan stok semua varian dengan gudang website di Jubelio';

    public function handle(StockSyncService $stock): int
    {
        try {
            $log = SyncLog::track(SyncType::Stock, SyncTrigger::Schedule, fn () => $stock->reconcile());
        } catch (IntegrationException $e) {
            $this->components->error('Rekonsiliasi stok gagal: '.$e->detail());

            return self::FAILURE;
        }

        $this->components->info("Stok {$log->items_processed} varian diperbarui.");

        return self::SUCCESS;
    }
}
