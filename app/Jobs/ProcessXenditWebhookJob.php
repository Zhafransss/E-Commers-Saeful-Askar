<?php

namespace App\Jobs;

use App\Enums\WebhookProvider;

/**
 * Processes a stored Xendit callback (WH-01).
 */
class ProcessXenditWebhookJob extends ProcessWebhookEvent
{
    protected function provider(): WebhookProvider
    {
        return WebhookProvider::Xendit;
    }
}
