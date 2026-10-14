<?php

namespace App\Http\Controllers\Webhook;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/webhooks.md
 */
class XenditWebhookController extends Controller
{
    /**
     * WH-01 · Status invoice Xendit
     */
    public function invoice(Request $request): JsonResponse
    {
        throw new NotImplementedException('WH-01');
    }
}
