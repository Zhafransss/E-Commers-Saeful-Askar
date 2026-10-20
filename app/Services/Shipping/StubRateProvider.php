<?php

namespace App\Services\Shipping;

use App\Models\Address;

/**
 * Sample rates while no real provider is set up (SHIPPING_DRIVER=stub).
 */
class StubRateProvider implements RateProvider
{
    public function rates(Address $address, int $weightGrams): array
    {
        return StubRates::quote((string) config('shipping.origin.city_id'), [
            'city_id' => (string) $address->city_id,
            'province_id' => (string) $address->province_id,
        ], $weightGrams);
    }
}
