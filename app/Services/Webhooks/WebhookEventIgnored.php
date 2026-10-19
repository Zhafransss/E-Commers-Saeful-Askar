<?php

namespace App\Services\Webhooks;

use RuntimeException;

/**
 * The event is valid but not for the website: marked `ignored` with this message.
 */
class WebhookEventIgnored extends RuntimeException {}
