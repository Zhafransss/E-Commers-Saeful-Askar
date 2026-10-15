<?php

namespace App\Http\Controllers\Webhook;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use App\Services\Webhooks\WebhookReceiver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/webhooks.md
 */
class JubelioWebhookController extends Controller
{
    /**
     * WH-02 · Produk dibuat/diubah di Jubelio
     */
    public function product(Request $request, WebhookReceiver $receiver): JsonResponse
    {
        return $receiver->jubelio($request, 'update-product', ['item_group_id' => ['required', 'integer']]);
    }

    /**
     * WH-03 · Harga diubah di Jubelio
     */
    public function price(Request $request, WebhookReceiver $receiver): JsonResponse
    {
        return $receiver->jubelio($request, 'update-price', ['item_group_id' => ['required', 'integer']]);
    }

    /**
     * WH-04 · Stok berubah di Jubelio
     */
    public function stock(Request $request, WebhookReceiver $receiver): JsonResponse
    {
        return $receiver->jubelio($request, 'update-qty', [
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
            'location_id' => ['required', 'integer'],
            'item_group_id' => ['nullable', 'integer'],
        ]);
    }

    /**
     * WH-05 · Sales order berubah di Jubelio
     */
    public function salesorder(Request $request): JsonResponse
    {
        throw new NotImplementedException('WH-05');
    }
}
