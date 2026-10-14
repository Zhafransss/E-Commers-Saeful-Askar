<?php

namespace App\Models;

use App\Enums\WebhookEventStatus;
use App\Enums\WebhookProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Every incoming webhook, recorded before processing so retries are idempotent.
 */
class WebhookEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'provider' => WebhookProvider::class,
            'status' => WebhookEventStatus::class,
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
