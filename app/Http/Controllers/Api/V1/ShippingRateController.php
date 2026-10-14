<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotImplementedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contract: docs/api/shipping.md
 */
class ShippingRateController extends Controller
{
    /**
     * SHIP-01 · Hitung pilihan kurir dan ongkir
     */
    public function store(Request $request): JsonResponse
    {
        throw new NotImplementedException('SHIP-01');
    }
}
