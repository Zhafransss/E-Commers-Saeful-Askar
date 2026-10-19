<?php

namespace App\Jobs;

use App\Enums\WebhookProvider;

/**
 * Processes a stored Jubelio webhook (WH-02 – WH-05).
 */
class ProcessJubelioWebhookJob extends ProcessWebhookEvent
{
    protected function provider(): WebhookProvider
    {
        return WebhookProvider::Jubelio;
    }
}
