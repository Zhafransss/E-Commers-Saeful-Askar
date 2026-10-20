<?php

namespace App\Services;

use App\Exceptions\IntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Shared rules for every outgoing call (docs/api/integrations.md INT-HDL):
 * timeout, one log line per call without bodies, and failures turned into
 * IntegrationException.
 *
 * Retrying is the queue's job (App\Jobs\Concerns\RetriesIntegrationCalls).
 * Calls made while a customer waits (checkout, shipping rates, regions) use
 * withinCustomerRequest(), which retries a transient failure once at most.
 */
abstract class IntegrationClient
{
    protected int $retries = 0;

    /** Label used in logs and exceptions: jubelio, jubelio_shipment, gapura. */
    abstract protected function service(): string;

    public function withinCustomerRequest(): static
    {
        $client = clone $this;
        $client->retries = 1;

        return $client;
    }

    /**
     * Hook that runs before each HTTP attempt (Jubelio uses it for its rate limit).
     */
    protected function beforeSend(): void {}

    /**
     * @param  array<string, mixed>  $options  Guzzle options: `query`, `json`, …
     */
    protected function send(PendingRequest $request, string $method, string $path, array $options = []): Response
    {
        $attempt = 0;

        while (true) {
            try {
                return $this->sendOnce($request, $method, $path, $options);
            } catch (IntegrationException $e) {
                // A 429 asks for a minute of waiting: never worth it while a customer waits.
                if (! $e->retryable || $e->retryAfter !== null || $attempt >= $this->retries) {
                    throw $e;
                }
                $attempt++;
                Sleep::for(200)->milliseconds();
            }
        }
    }

    /**
     * Maps a non-2xx response. 5xx is retryable; 429 is retryable after 60 seconds;
     * other 4xx are not, since sending the same request again cannot succeed.
     */
    protected function toException(Response $response, string $method, string $path): IntegrationException
    {
        $status = $response->status();

        return new IntegrationException(
            service: $this->service(),
            method: $method,
            path: $path,
            httpStatus: $status,
            retryable: $status >= 500 || $status === 429,
            retryAfter: $status === 429 ? 60 : null,
            providerCode: $this->stringOrNull($response->json('error_code') ?? $response->json('code')),
            providerMessage: $this->stringOrNull($response->json('message')),
        );
    }

    protected function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function sendOnce(PendingRequest $request, string $method, string $path, array $options): Response
    {
        $this->beforeSend();
        $startedAt = hrtime(true);

        try {
            $response = $request->send($method, $path, $options);
        } catch (ConnectionException $e) {
            // cURL messages end with the full URL; its query string may hold an email (INT-JB-09 search).
            $error = preg_replace('/\?\S*/', '?…', $e->getMessage());
            $this->log('warning', $method, $path, null, $startedAt, ['error' => $error]);

            throw new IntegrationException($this->service(), $method, $path, providerMessage: $error);
        }

        if ($response->successful()) {
            $this->log('info', $method, $path, $response->status(), $startedAt);

            return $response;
        }

        $exception = $this->toException($response, $method, $path);
        $this->log('warning', $method, $path, $response->status(), $startedAt, ['provider_code' => $exception->providerCode]);

        throw $exception;
    }

    /**
     * Only method, path, status, and duration are logged: request and response
     * bodies may hold customer data or credentials (INT-HDL).
     *
     * @param  array<string, mixed>  $extra
     */
    protected function log(string $level, string $method, string $path, ?int $status, int $startedAt, array $extra = []): void
    {
        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        Log::channel('integrations')->log($level, sprintf('%s %s %s %s %dms', $this->service(), $method, $path, $status ?? '-', $durationMs), [
            'service' => $this->service(),
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'duration_ms' => $durationMs,
        ] + $extra);
    }
}
