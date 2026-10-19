<?php

namespace App\Console\Commands;

use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Exceptions\IntegrationException;
use App\Models\SyncLog;
use App\Services\Jubelio\ProductSyncService;
use Illuminate\Console\Command;

/**
 * INT-JB-03 – INT-JB-05 · Incremental every 30 minutes, full every night.
 */
class JubelioSyncProducts extends Command
{
    protected $signature = 'jubelio:sync-products
        {--full : Baca semua produk (termasuk bundle), nonaktifkan yang hilang, lalu sinkron stok}';

    protected $description = 'Salin produk, varian, gambar, harga, dan berat dari Jubelio';

    public function handle(ProductSyncService $sync): int
    {
        $full = (bool) $this->option('full');

        try {
            $log = SyncLog::track(SyncType::Products, SyncTrigger::Schedule, fn () => $full ? $sync->syncAll() : $sync->syncChanged());
        } catch (IntegrationException $e) {
            $this->components->error('Sinkron produk gagal: '.$e->detail());

            return self::FAILURE;
        }

        $this->components->info(($full ? 'Sinkron penuh' : 'Sinkron perubahan').": {$log->items_processed} produk.");

        return self::SUCCESS;
    }
}
