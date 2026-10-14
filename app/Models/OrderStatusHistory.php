<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderStatusSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only status trail; `created_at` is exposed as `at` in the API.
 */
class OrderStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'source' => OrderStatusSource::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
