<?php

namespace App\Services\RajaOngkir;

use App\Exceptions\IntegrationException;
use App\Services\IntegrationClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The only class allowed to call RajaOngkir (Komerce, API V2): INT-RO-01 destination search and
 * INT-RO-02 domestic cost. The key goes in the `key` header and bodies are form-encoded, as in the docs.
 */
class RajaOngkirClient extends IntegrationClient
{
    /**
     * @param  array<string, mixed>  $config  config('shipping.rajaongkir')
     */
    public function __construct(private readonly array $config) {}

    protected function service(): string
    {
        return 'rajaongkir';
    }

    /**
     * INT-RO-01 · Finds destination ids by a free-text search ("satriajaya tambun utara").
     *
     * @return list<array{id: int, label: string, subdistrict_name: string, district_name: string, city_name: string, province_name: string, zip_code: string}>
     */
    public function searchDestinations(string $keyword, int $limit = 10): array
    {
        $response = $this->send($this->request(), 'GET', 'destination/domestic-destination', [
            'query' => ['search' => $keyword, 'limit' => $limit, 'offset' => 0],
        ]);

        return array_values(array_map(fn (array $row) => [
            'id' => (int) $row['id'],
            'label' => (string) ($row['label'] ?? ''),
            'subdistrict_name' => (string) ($row['subdistrict_name'] ?? ''),
            'district_name' => (string) ($row['district_name'] ?? ''),
            'city_name' => (string) ($row['city_name'] ?? ''),
            'province_name' => (string) ($row['province_name'] ?? ''),
            'zip_code' => (string) ($row['zip_code'] ?? ''),
        ], array_filter((array) $response->json('data'), fn ($row) => is_array($row) && isset($row['id']))));
    }

    /**
     * INT-RO-02 · Costs of every service of the given couriers. "No cost found" for a route is not a
     * failure: the answer is an empty list.
     *
     * @param  list<string>  $couriers  Codes such as `jne`, joined with `:` as the docs show
     * @return list<array{name: string, code: string, service: string, description: string, cost: int, etd: string}>
     */
    public function domesticCost(int $origin, int $destination, int $weightGrams, array $couriers): array
    {
        try {
            $response = $this->send($this->request()->asForm(), 'POST', 'calculate/domestic-cost', ['form_params' => [
                'origin' => $origin,
                'destination' => $destination,
                'weight' => $weightGrams,
                'courier' => implode(':', $couriers),
                'price' => 'lowest',
            ]]);
        } catch (IntegrationException $e) {
            if ($e->httpStatus === 400) {
                return [];
            }

            throw $e;
        }

        // RajaOngkir may also answer HTTP 200 with the failure in `meta`.
        $code = (int) $response->json('meta.code', 200);
        if ($code === 400) {
            return [];
        }
        if ($code >= 400) {
            throw new IntegrationException('rajaongkir', 'POST', 'calculate/domestic-cost', httpStatus: $code, retryable: $code >= 500,
                providerMessage: $this->stringOrNull($response->json('meta.message')));
        }

        return array_values(array_filter(array_map(fn (array $row) => [
            'name' => (string) ($row['name'] ?? ''),
            'code' => strtolower((string) ($row['code'] ?? '')),
            'service' => (string) ($row['service'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'cost' => (int) round((float) ($row['cost'] ?? 0)),
            'etd' => (string) ($row['etd'] ?? ''),
        ], array_filter((array) $response->json('data'), 'is_array')), fn (array $row) => $row['code'] !== '' && $row['service'] !== ''));
    }

    protected function toException(Response $response, string $method, string $path): IntegrationException
    {
        return new IntegrationException(
            service: $this->service(),
            method: $method,
            path: $path,
            httpStatus: $response->status(),
            retryable: $response->status() >= 500 || $response->status() === 429,
            retryAfter: $response->status() === 429 ? 60 : null,
            providerCode: $this->stringOrNull($response->json('meta.code')),
            providerMessage: $this->stringOrNull($response->json('meta.message') ?? $response->json('message')),
        );
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) $this->config['base_url'], '/').'/')
            ->withHeaders(['key' => (string) $this->config['api_key']])
            ->timeout($this->config['timeout'])
            ->acceptJson();
    }
}
