<?php

namespace App\Models;

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Exceptions\IntegrationException;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * History of catalog syncs and order pushes to Jubelio. For order_* types,
 * reference_id is orders.id.
 */
class SyncLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => SyncType::class,
            'trigger' => SyncTrigger::class,
            'status' => SyncStatus::class,
            'items_processed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public static function start(SyncType $type, SyncTrigger $trigger, ?int $referenceId = null): self
    {
        return self::create([
            'type' => $type,
            'trigger' => $trigger,
            'status' => SyncStatus::Running,
            'reference_id' => $referenceId,
            'started_at' => now(),
        ]);
    }

    /**
     * Runs $work inside a log row: success with the item count it returns, or
     * failure with the error text, rethrowing so the caller (or queue) still sees it.
     *
     * @param  Closure(self): (int|null)  $work
     */
    public static function track(SyncType $type, SyncTrigger $trigger, Closure $work, ?int $referenceId = null): self
    {
        $log = self::start($type, $trigger, $referenceId);

        try {
            $items = $work($log);
        } catch (Throwable $e) {
            $log->markFailed($e);

            throw $e;
        }

        $log->markSucceeded($items ?? $log->items_processed ?? 0);

        return $log;
    }

    /**
     * A catalog sync whose process was killed (the host stops a long PHP process without letting it close its log)
     * stays `running` for ever. Past $minutes (the sync lock lasts 30) it is closed as failed so the admin can start
     * it again. Order pushes are left alone: the queue retries those itself.
     *
     * @return int Logs closed
     */
    public static function failStale(int $minutes = 30): int
    {
        return self::where('status', SyncStatus::Running->value)
            ->whereIn('type', [SyncType::Categories->value, SyncType::Products->value, SyncType::Stock->value])
            ->where('started_at', '<', now()->subMinutes($minutes))
            ->update([
                'status' => SyncStatus::Failed->value,
                'message' => 'Proses sinkron terhenti sebelum selesai (kemungkinan dihentikan server). Jalankan ulang.',
                'finished_at' => now(),
            ]);
    }

    public function markSucceeded(int $itemsProcessed = 0, ?string $message = null): void
    {
        $this->update([
            'status' => SyncStatus::Success,
            'items_processed' => $itemsProcessed,
            'message' => $message,
            'finished_at' => now(),
        ]);
    }

    /**
     * Jubelio's own error text is stored as-is, since it is often only a SQL
     * error code that support needs verbatim (INT-HDL).
     */
    public function markFailed(Throwable|string $error, ?int $itemsProcessed = null): void
    {
        $message = match (true) {
            $error instanceof IntegrationException => $error->detail(),
            $error instanceof Throwable => $error->getMessage(),
            default => $error,
        };

        $this->update([
            'status' => SyncStatus::Failed,
            // A run that fails before counting anything has no count yet; the column is NOT NULL.
            'items_processed' => $itemsProcessed ?? $this->items_processed ?? 0,
            'message' => Str::limit($message, 5000),
            'finished_at' => now(),
        ]);
    }
}
