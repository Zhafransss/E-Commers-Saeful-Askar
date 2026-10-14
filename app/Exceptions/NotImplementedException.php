<?php

namespace App\Exceptions;

/**
 * Thrown by endpoint stubs that are registered but not built yet.
 * Development-only: every endpoint must replace this before release.
 */
class NotImplementedException extends ApiException
{
    public function __construct(string $endpointCode)
    {
        parent::__construct(
            "Endpoint {$endpointCode} belum diimplementasikan.",
            'NOT_IMPLEMENTED',
            501,
            extra: ['endpoint' => $endpointCode],
        );
    }
}
