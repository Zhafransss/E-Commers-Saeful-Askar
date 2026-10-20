<?php

namespace App\Jobs\Jubelio;

use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Models\SyncLog;
use App\Services\Jubelio\ProductSyncService;
use App\Services\Jubelio\StockSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * ADM-13: a sync an admin started. The controller took the lock(s) and made the first sync log; this job does
 * the work, closes the log, and always lets the lock(s) go. `full` runs categories → products → stock, each
 * with its own sync log. There is no retry: a failed sync shows in ADM-14 and the admin starts it again.
 *
 * @phpstan-type Lock array{name: string, owner: string}
 */
class RunManualSyncJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * @param  list<array{name: string, owner: string}>  $locks
     */
    public function __construct(public string $type, public bool $fullProducts, public int $syncLogId, public array $locks) {}

    public function handle(ProductSyncService $products, StockSyncService $stock): void
    {
        try {
            $log = SyncLog::findOrFail($this->syncLogId);

            if ($this->type === 'full') {
                $this->step($log, fn () => $products->syncCategories());
                SyncLog::track(SyncType::Products, SyncTrigger::Manual, fn () => $products->syncAll());
                SyncLog::track(SyncType::Stock, SyncTrigger::Manual, fn () => $stock->reconcile());

                return;
            }

            $this->step($log, fn () => match ($this->type) {
                'categories' => $products->syncCategories(),
                'products' => $this->fullProducts ? $products->syncAll() : $products->syncChanged(),
                'stock' => $stock->reconcile(),
            });
        } finally {
            $this->release();
        }
    }

    public function failed(Throwable $e): void
    {
        SyncLog::whereKey($this->syncLogId)->where('status', 'running')->first()?->markFailed($e);
        $this->release();
    }

    /**
     * @param  callable(): int  $work
     */
    private function step(SyncLog $log, callable $work): void
    {
        try {
            $log->markSucceeded($work());
        } catch (Throwable $e) {
            $log->markFailed($e);

            throw $e;
        }
    }

    private function release(): void
    {
        foreach ($this->locks as $lock) {
            Cache::restoreLock($lock['name'], $lock['owner'])->release();
        }
    }
}
