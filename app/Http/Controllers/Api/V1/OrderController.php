<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/checkout-orders.md
 */
class OrderController extends Controller
{
    /**
     * ORD-02 · Riwayat pesanan saya
     */
    public function index(Request $request): JsonResponse
    {
        throw new NotImplementedException('ORD-02');
    }

    /**
     * ORD-03 · Detail pesanan
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        throw new NotImplementedException('ORD-03');
    }

    /**
     * ORD-04 · Lanjutkan pembayaran
     */
    public function payment(Request $request, string $orderNumber): JsonResponse
    {
        throw new NotImplementedException('ORD-04');
    }

    /**
     * ORD-05 · Batalkan pesanan yang belum dibayar
     */
    public function cancel(Request $request, string $orderNumber): JsonResponse
    {
        throw new NotImplementedException('ORD-05');
    }
}
