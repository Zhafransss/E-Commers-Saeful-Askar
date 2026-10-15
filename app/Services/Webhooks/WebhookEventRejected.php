<?php

namespace App\Services\Webhooks;

use RuntimeException;

/**
 * The event cannot be applied and needs an admin: marked `failed` without retry
 * and logged as a warning (e.g. WH-01 paid amount differs from the invoice).
 */
class WebhookEventRejected extends RuntimeException {}
