<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Webhooks\Handlers\JubelioSalesOrderUpdated;
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
     * WH-05 · Sales order berubah di Jubelio. Jubelio sends it for the orders of every channel; only the
     * website's own orders are recorded.
     */
    public function salesorder(Request $request, WebhookReceiver $receiver): JsonResponse
    {
        return $receiver->jubelio(
            $request,
            'update-salesorder',
            ['salesorder_id' => ['required', 'integer']],
            fn (array $payload) => JubelioSalesOrderUpdated::findOrder($payload) !== null,
        );
    }
}
