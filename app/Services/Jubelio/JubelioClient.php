<?php

namespace App\Services\Jubelio;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The only class allowed to call api2.jubelio.com (docs/api/integrations.md INT-HDL).
 * Endpoint methods (INT-JB-01 – INT-JB-14) are added here by the backend team;
 * the `authorization` token header comes from INT-JB-01 (cached in the database store).
 */
class JubelioClient
{
    /**
     * @param  array<string, mixed>  $config  config('jubelio')
     */
    public function __construct(private readonly array $config) {}

    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->config['base_url'])
            ->timeout($this->config['timeout'])
            ->acceptJson()
            ->asJson();
    }
}
