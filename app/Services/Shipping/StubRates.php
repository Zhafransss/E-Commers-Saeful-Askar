<?php

namespace App\Services\Shipping;

/**
 * Sample courier rates in the SHIP-01 shape, used while JUBELIO_SHIPMENT_DRIVER=stub (the real
 * rates API is not documented publicly yet, docs/api/integrations.md INT-JS-01). The numbers only
 * make checkout behave plausibly: heavier and farther costs more, and bikes (heavy) go by cargo.
 */
final class StubRates
{
    private const JABODETABEK_PROVINCES = ['31', '36'];

    private const JABODETABEK_CITIES = ['3216', '3275', '3201', '3271', '3276'];

    /**
     * @param  array{city_id: string, province_id: string}  $destination
     * @return list<array<string, mixed>> Without `rate_id`; ShippingRateService adds it
     */
    public static function quote(string $originCityId, array $destination, int $weightGrams): array
    {
        $kg = max(1, (int) ceil($weightGrams / 1000));
        $zone = match (true) {
            $destination['city_id'] === $originCityId => 0,
            in_array($destination['city_id'], self::JABODETABEK_CITIES, true),
            in_array($destination['province_id'], self::JABODETABEK_PROVINCES, true) => 1,
            default => 2,
        };

        $services = [];

        if ($zone === 0 && $kg <= 20) {
            $services[] = ['gosend', 'GoSend', 'INSTANT', 'GoSend Instant', 'instant', 25000 + $kg * 2500, [0, 0]];
        }
        if ($kg <= 30) {
            $services[] = ['jne', 'JNE', 'REG', 'JNE Reguler', 'regular', $kg * [9000, 12000, 24000][$zone], [[1, 2], [2, 3], [3, 5]][$zone]];
            $services[] = ['jnt', 'J&T Express', 'EZ', 'J&T EZ', 'regular', $kg * [8500, 11500, 23000][$zone], [[1, 2], [2, 3], [3, 6]][$zone]];
        }

        $cargoKg = max(10, $kg);
        $services[] = ['jne', 'JNE', 'JTR', 'JNE Trucking', 'cargo', $cargoKg * [5000, 6500, 11000][$zone], [[2, 3], [3, 5], [5, 8]][$zone]];
        $services[] = ['jnt', 'J&T Express', 'CARGO', 'J&T Cargo', 'cargo', $cargoKg * [4800, 6200, 10500][$zone], [[2, 4], [3, 5], [5, 9]][$zone]];
        $services[] = ['sap', 'SAP Express', 'CARGO', 'SAP Cargo', 'cargo', $cargoKg * [5200, 6800, 11500][$zone], [[1, 3], [2, 4], [4, 7]][$zone]];

        return array_map(fn (array $row) => [
            'courier_code' => $row[0],
            'courier_name' => $row[1],
            'service_code' => $row[2],
            'service_name' => $row[3],
            'service_type' => $row[4],
            'cost' => (int) (round($row[5] / 500) * 500),
            'insurance_cost' => 0,
            'etd_min_days' => $row[6][0],
            'etd_max_days' => $row[6][1],
            'etd_text' => $row[6][1] === 0 ? 'Hari ini' : ($row[6][0] === $row[6][1] ? "{$row[6][0]} hari" : "{$row[6][0]}–{$row[6][1]} hari"),
        ], $services);
    }
}
