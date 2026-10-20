<?php

namespace App\Services\JubelioShipment;

use App\Exceptions\IntegrationException;
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
     * INT-JS-01 · Courier rates from the origin warehouse to a destination, for a total weight.
     * With the `stub` driver they are sample rates; any other driver is not built yet, because
     * Jubelio has not shared the rates API.
     *
     * @param  array{city_id: string, province_id: string}  $destination
     * @return list<array<string, mixed>> SHIP-01 rate fields without `rate_id`
     */
    public function rates(array $destination, int $weightGrams): array
    {
        if ($this->usesStub()) {
            return StubRates::quote((string) $this->config['origin']['city_id'], $destination, $weightGrams);
        }

        throw new IntegrationException('jubelio_shipment', 'POST', '/rates', retryable: false,
            providerMessage: 'API rates Jubelio Shipment belum diimplementasikan; pakai JUBELIO_SHIPMENT_DRIVER=stub.');
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
