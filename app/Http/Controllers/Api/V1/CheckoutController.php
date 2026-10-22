<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckoutRequest;
use App\Http\Resources\Api\V1\OrderDetailResource;
use App\Models\Order;
use App\Services\Shop\CheckoutService;
use Illuminate\Http\JsonResponse;

/**
 * Contract: docs/api/checkout-orders.md
 */
class CheckoutController extends Controller
{
    /**
     * ORD-01 · Checkout (buat pesanan dan order Gapura)
     */
    public function store(CheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        $data = $checkout->handle(
            $request->user(),
            $request->validated('idempotency_key'),
            $request->integer('address_id'),
            $request->validated('rate_id'),
            $request->validated('note'),
            $request->integer('expected_grand_total'),
            fn (Order $order) => [
                'order' => (new OrderDetailResource($order))->response()->getData(true)['data'],
                'redirect_url' => $order->payment->checkout_url,
            ],
            $request->validated('item_ids'),
        );

        return response()->json(['data' => $data], 201);
    }
}
