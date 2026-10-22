<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShippingRatesRequest;
use App\Services\Shop\ShippingRateService;
use Illuminate\Http\JsonResponse;

/**
 * Contract: docs/api/shipping.md
 */
class ShippingRateController extends Controller
{
    /**
     * SHIP-01 · Hitung pilihan kurir dan ongkir
     */
    public function store(ShippingRatesRequest $request, ShippingRateService $rates): JsonResponse
    {
        // Another user's address is "not found", not forbidden, so ids do not leak.
        $address = $request->user()->addresses()->find($request->integer('address_id'))
            ?? throw new ApiException('Alamat tidak ditemukan.', 'ADDRESS_NOT_FOUND', 404);

        $data = $rates->quote($request->user(), $address, $request->validated('service_types', []), $request->validated('item_ids'));
        $data['expires_at'] = $data['expires_at']->toIso8601String();

        return response()->json(['data' => $data]);
    }
}
