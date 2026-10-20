<?php

namespace App\Exceptions;

/**
 * An outgoing call to Jubelio, Jubelio Shipment, or Gapura failed (INT-HDL).
 *
 * Reaches the customer as `502 INTEGRATION_ERROR` with a non-technical message;
 * the technical detail (status, provider code/message) is kept for logs and
 * sync_logs through detail(). Queued jobs read `retryable` and `retryAfter`
 * (see App\Jobs\Middleware\HandleIntegrationErrors).
 */
class IntegrationException extends ApiException
{
    public const DEFAULT_MESSAGE = 'Layanan sedang tidak bisa dihubungi. Coba lagi beberapa saat.';

    public function __construct(
        public readonly string $service,
        public readonly string $method,
        public readonly string $path,
        public readonly ?int $httpStatus = null,
        public readonly bool $retryable = true,
        public readonly ?int $retryAfter = null,
        public readonly ?string $providerCode = null,
        public readonly ?string $providerMessage = null,
        string $message = self::DEFAULT_MESSAGE,
    ) {
        parent::__construct($message, 'INTEGRATION_ERROR', 502);
    }

    /**
     * Same failure with a message written for a specific endpoint, e.g. the
     * REG-01 "Data wilayah sedang tidak bisa dimuat…" text from the contract.
     */
    public function withMessage(string $message): static
    {
        return new static(
            $this->service, $this->method, $this->path, $this->httpStatus,
            $this->retryable, $this->retryAfter, $this->providerCode, $this->providerMessage, $message,
        );
    }

    /**
     * Technical description for logs and sync_logs.message, e.g.
     * "jubelio POST /sales/orders/ → HTTP 500 [23505] duplicate key value…", or
     * "jubelio GET /locations/: cURL error 28: …" when no response came back.
     * Provider messages are kept as-is (INT-HDL: Jubelio SQL errors).
     */
    public function detail(): string
    {
        $call = "{$this->service} {$this->method} {$this->path}";

        if ($this->httpStatus === null) {
            return "{$call}: ".($this->providerMessage ?? 'tidak ada respons');
        }

        $code = $this->providerCode === null ? '' : " [{$this->providerCode}]";
        $message = $this->providerMessage === null ? '' : " {$this->providerMessage}";

        return "{$call} → HTTP {$this->httpStatus}{$code}{$message}";
    }
}
