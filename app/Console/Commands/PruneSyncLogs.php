<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use Illuminate\Console\Command;

/**
 * Deletes sync logs older than 90 days (docs/api/admin.md ADM-14).
 */
class PruneSyncLogs extends Command
{
    protected $signature = 'sync-logs:prune {--days=90 : Hapus log yang lebih lama dari jumlah hari ini}';

    protected $description = 'Hapus riwayat sinkron yang sudah lewat 90 hari';

    public function handle(): int
    {
        $deleted = SyncLog::where('started_at', '<', now()->subDays((int) $this->option('days')))->delete();

        $this->components->info("{$deleted} log sinkron dihapus.");

        return self::SUCCESS;
    }
}
