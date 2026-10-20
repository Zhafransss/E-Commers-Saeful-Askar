<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ApiResource;
use App\Models\SyncLog;
use Illuminate\Http\Request;

/**
 * One row of the sync history, ADM-14.
 *
 * @mixin SyncLog
 */
class SyncLogResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'reference_id' => $this->reference_id,
            'items_processed' => $this->items_processed,
            'message' => $this->message,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration_seconds' => $this->finished_at === null ? null : (int) $this->started_at->diffInSeconds($this->finished_at),
        ];
    }
}
