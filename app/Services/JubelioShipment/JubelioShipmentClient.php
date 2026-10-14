<?php

namespace App\Services\JubelioShipment;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The only class allowed to call Jubelio Shipment (INT-JS-01). The rates API is
 * not documented yet: while config('jubelio_shipment.driver') is `stub`, rate
 * quotes must come from sample data in the SHIP-01 shape instead of HTTP.
 */
class JubelioShipmentClient
{
    /**
     * @param  array<string, mixed>  $config  config('jubelio_shipment')
     */
    public function __construct(private readonly array $config) {}

    public function usesStub(): bool
    {
        return $this->config['driver'] === 'stub';
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl((string) $this->config['base_url'])
            ->timeout($this->config['timeout'])
            ->acceptJson()
            ->asJson();
    }
}
