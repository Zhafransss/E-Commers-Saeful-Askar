<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/admin.md
 */
class OrderController extends Controller
{
    /**
     * ADM-09 · Daftar pesanan website (admin)
     */
    public function index(Request $request): JsonResponse
    {
        throw new NotImplementedException('ADM-09');
    }

    /**
     * ADM-10 · Detail pesanan (admin)
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        throw new NotImplementedException('ADM-10');
    }

    /**
     * ADM-11 · Kirim ulang pesanan ke Jubelio
     */
    public function pushJubelio(Request $request, string $orderNumber): JsonResponse
    {
        throw new NotImplementedException('ADM-11');
    }

    /**
     * ADM-12 · Periksa ulang status pembayaran
     */
    public function refreshPayment(Request $request, string $orderNumber): JsonResponse
    {
        throw new NotImplementedException('ADM-12');
    }
}
