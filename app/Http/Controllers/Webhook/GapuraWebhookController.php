<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Gapura\GapuraClient;
use App\Services\Webhooks\WebhookReceiver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/webhooks.md
 */
class GapuraWebhookController extends Controller
{
    /**
     * WH-01 · Finish Notify dari Gapura (DANA)
     *
     * The notification is verified, stored, and queued. What the order does with it (paid,
     * expired) is the handler registered in config/webhooks.php, added with the order flow
     * (Fase 5); until then the queued event is settled as failed ("no handler") and stays visible
     * in webhook_events.
     */
    public function notify(Request $request, WebhookReceiver $receiver, GapuraClient $gapura): JsonResponse
    {
        return $receiver->gapura($request, $gapura);
    }
}
