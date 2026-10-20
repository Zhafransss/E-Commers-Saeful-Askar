<?php

namespace App\Jobs;

use App\Enums\WebhookProvider;

/**
 * Processes a stored Gapura Finish Notify (WH-01).
 */
class ProcessGapuraWebhookJob extends ProcessWebhookEvent
{
    protected function provider(): WebhookProvider
    {
        return WebhookProvider::Gapura;
    }
}
