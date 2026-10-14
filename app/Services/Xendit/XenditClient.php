<?php

namespace App\Services\Xendit;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The only class allowed to call api.xendit.co (INT-XD-01 – INT-XD-03).
 * Auth is HTTP Basic with the secret key as username and an empty password.
 */
class XenditClient
{
    /**
     * @param  array<string, mixed>  $config  config('xendit')
     */
    public function __construct(private readonly array $config) {}

    public function isLiveMode(): bool
    {
        return str_starts_with((string) $this->config['secret_key'], 'xnd_production_');
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->config['base_url'])
            ->withBasicAuth((string) $this->config['secret_key'], '')
            ->timeout($this->config['timeout'])
            ->acceptJson()
            ->asJson();
    }
}
