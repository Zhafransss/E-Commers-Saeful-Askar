<?php

namespace App\Services\Webhooks;

use App\Models\WebhookEvent;

/**
 * Business logic for one webhook event type, registered in config/webhooks.php.
 *
 * Returning normally marks the event `processed`. Throw WebhookEventIgnored
 * when the event does not concern the website (e.g. another store's order),
 * or WebhookEventRejected when it cannot be applied and needs a human (e.g.
 * paid after cancellation). Any other exception is retried by the queue.
 */
interface WebhookHandler
{
    public function handle(WebhookEvent $event): void;
}
