<?php

namespace App\Services\JubelioShipment;

use App\Services\IntegrationClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The only class allowed to call Jubelio Shipment (INT-JS-01). The rates API is
 * not documented yet: while config('jubelio_shipment.driver') is `stub`, rate
 * quotes must come from sample data in the SHIP-01 shape instead of HTTP.
 */
class JubelioShipmentClient extends IntegrationClient
{
    /**
     * @param  array<string, mixed>  $config  config('jubelio_shipment')
     */
    public function __construct(private readonly array $config) {}

    protected function service(): string
    {
        return 'jubelio_shipment';
    }

    public function usesStub(): bool
    {
        return $this->config['driver'] === 'stub';
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function post(string $path, array $body = []): Response
    {
        return $this->send($this->request(), 'POST', $path, ['json' => $body]);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) $this->config['base_url'])
            ->timeout($this->config['timeout'])
            ->acceptJson()
            ->asJson();
    }
}
