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

        $log->markSucceeded($items ?? $log->items_processed);

        return $log;
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
            'items_processed' => $itemsProcessed ?? $this->items_processed,
            'message' => Str::limit($message, 5000),
            'finished_at' => now(),
        ]);
    }
}
