<?php

namespace App\Services\RajaOngkir;

use App\Models\Address;
use App\Services\Shipping\RateProvider;

/**
 * Courier rates from RajaOngkir in the SHIP-01 shape: destination ids are found first, then one cost request
 * asks every offered courier. Services that cost nothing are dropped.
 */
class RajaOngkirRates implements RateProvider
{
    public function __construct(
        private readonly RajaOngkirClient $client,
        private readonly DestinationResolver $destinations,
    ) {}

    public function rates(Address $address, int $weightGrams): array
    {
        $couriers = (array) config('shipping.rajaongkir.couriers');
        $client = $this->client->withinCustomerRequest();

        $services = $client->domesticCost(
            $this->destinations->origin(),
            $this->destinations->forAddress($address),
            max(1, $weightGrams),
            $couriers,
        );

        return array_values(array_map(fn (array $row) => $this->rate($row), array_filter($services, fn (array $row) => $row['cost'] > 0)));
    }

    /**
     * @param  array{name: string, code: string, service: string, description: string, cost: int, etd: string}  $row
     * @return array<string, mixed>
     */
    private function rate(array $row): array
    {
        [$min, $max] = $this->days($row['etd']);

        return [
            'courier_code' => $row['code'],
            'courier_name' => $row['name'],
            'service_code' => $row['service'],
            'service_name' => trim("{$row['name']} {$row['service']}"),
            'service_type' => $this->type($row),
            'cost' => $row['cost'],
            'insurance_cost' => 0,
            'etd_min_days' => $min,
            'etd_max_days' => $max,
            'etd_text' => match (true) {
                $min === null => 'Estimasi belum tersedia',
                $max === 0 => 'Hari ini',
                $min === $max => "{$min} hari",
                default => "{$min}–{$max} hari",
            },
        ];
    }

    /**
     * "2-3 day", "1 day", "2 - 4 hari", "1-2" → [2, 3]; nothing readable → [null, null].
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function days(string $etd): array
    {
        if (! preg_match_all('/\d+/', $etd, $numbers)) {
            return str_contains(mb_strtolower($etd), 'hari ini') || str_contains(mb_strtolower($etd), 'same day') ? [0, 0] : [null, null];
        }

        $days = array_map('intval', $numbers[0]);

        return [min($days), max($days)];
    }

    /**
     * RajaOngkir does not label the kind of service, so it is read from the service code and description.
     *
     * @param  array{code: string, service: string, description: string}  $row
     */
    private function type(array $row): string
    {
        $text = mb_strtolower("{$row['service']} {$row['description']}");

        return match (true) {
            (bool) preg_match('/instant|gosend|grab|\bsame\s*day|\bsds\b|\bsd\b|hari ini/', $text) => preg_match('/instant|gosend|grab/', $text) ? 'instant' : 'same_day',
            (bool) preg_match('/cargo|kargo|trucking|\bjtr\b|\bgokil\b|bigpack|heavy|\bdarat\b|sentral|logistik/', $text),
            in_array($row['code'], ['sentral', 'rex'], true) => 'cargo',
            default => 'regular',
        };
    }
}
