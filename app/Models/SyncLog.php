<?php

namespace App\Models;

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use Illuminate\Database\Eloquent\Model;

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
}
