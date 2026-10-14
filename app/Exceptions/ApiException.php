<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Base exception for errors that must reach the client in the API Contract
 * error format: { "message", "code", "errors"?, ...extra }.
 *
 * Domain exceptions (e.g. OUT_OF_STOCK, ORDER_NOT_FOUND) extend or instantiate
 * this class with their contract `code` from docs/api/README.md.
 */
class ApiException extends Exception
{
    /**
     * @param  array<string, list<string>>  $errors  Field errors (VALIDATION_ERROR style)
     * @param  array<string, mixed>  $extra  Additional top-level keys, e.g. `available_qty`
     */
    public function __construct(
        string $message,
        protected string $errorCode,
        protected int $status = 400,
        protected array $errors = [],
        protected array $extra = [],
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(): JsonResponse
    {
        $payload = ['message' => $this->getMessage(), 'code' => $this->errorCode];

        if ($this->errors !== []) {
            $payload['errors'] = $this->errors;
        }

        return response()->json($payload + $this->extra, $this->status);
    }
}
