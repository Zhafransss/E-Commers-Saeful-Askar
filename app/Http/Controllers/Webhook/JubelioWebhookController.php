<?php

namespace App\Http\Controllers\Webhook;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
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
    public function product(Request $request): JsonResponse
    {
        throw new NotImplementedException('WH-02');
    }

    /**
     * WH-03 · Harga diubah di Jubelio
     */
    public function price(Request $request): JsonResponse
    {
        throw new NotImplementedException('WH-03');
    }

    /**
     * WH-04 · Stok berubah di Jubelio
     */
    public function stock(Request $request): JsonResponse
    {
        throw new NotImplementedException('WH-04');
    }

    /**
     * WH-05 · Sales order berubah di Jubelio
     */
    public function salesorder(Request $request): JsonResponse
    {
        throw new NotImplementedException('WH-05');
    }
}
