<?php

namespace App\Services\Shipping;

use App\Exceptions\IntegrationException;
use App\Models\Address;

/**
 * Where courier rates come from (docs/api/shipping.md). Bound by `config('shipping.driver')`.
 */
interface RateProvider
{
    /**
     * Rates from the origin warehouse to the address, for a total weight in grams.
     *
     * @return list<array<string, mixed>> SHIP-01 rate fields without `rate_id`
     *
     * @throws IntegrationException When the provider cannot answer
     */
    public function rates(Address $address, int $weightGrams): array;
}
