<?php

namespace App\Services\Xendit;

use App\Services\IntegrationClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The only class allowed to call api.xendit.co (INT-XD-01 – INT-XD-03).
 * Auth is HTTP Basic with the secret key as username and an empty password.
 * Xendit 4xx errors are not retried; their `error_code` and `message` are kept
 * on the exception (INT-HDL).
 */
class XenditClient extends IntegrationClient
{
    /**
     * @param  array<string, mixed>  $config  config('xendit')
     */
    public function __construct(private readonly array $config) {}

    protected function service(): string
    {
        return 'xendit';
    }

    public function isLiveMode(): bool
    {
        return str_starts_with((string) $this->config['secret_key'], 'xnd_production_');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function get(string $path, array $query = []): Response
    {
        return $this->send($this->request(), 'GET', $path, ['query' => $query]);
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
        return Http::baseUrl($this->config['base_url'])
            ->withBasicAuth((string) $this->config['secret_key'], '')
            ->timeout($this->config['timeout'])
            ->acceptJson()
            ->asJson();
    }
}
