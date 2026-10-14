<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/checkout-orders.md
 */
class CheckoutController extends Controller
{
    /**
     * ORD-01 · Checkout (buat pesanan dan invoice)
     */
    public function store(Request $request): JsonResponse
    {
        throw new NotImplementedException('ORD-01');
    }
}
