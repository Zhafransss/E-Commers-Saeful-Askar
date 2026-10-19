<?php

namespace App\Exceptions;

/**
 * A Jubelio call failed. Jubelio reports many errors as HTTP 500 with a
 * SQLSTATE in `code` (e.g. "23505"); that code and message are kept verbatim
 * in providerCode/providerMessage (INT-HDL).
 */
class JubelioApiException extends IntegrationException
{
    /** Returned by POST /login for wrong credentials (INT-JB-01). */
    public const INVALID_CREDENTIALS = 'Invalid email or password';

    public function isInvalidCredentials(): bool
    {
        return $this->providerCode === self::INVALID_CREDENTIALS;
    }
}
