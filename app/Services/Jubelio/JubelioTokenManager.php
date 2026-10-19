<?php

namespace App\Services\Jubelio;

use App\Exceptions\JubelioApiException;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;

/**
 * Keeps the Jubelio token in the `database` cache store (INT-JB-01). Only one
 * process logs in at a time; the others wait on the lock and reuse its token.
 *
 * After a rejected login (wrong credentials) further attempts are paused for a
 * few minutes, so every queued job does not hammer POST /login.
 */
class JubelioTokenManager
{
    private const TOKEN_KEY = 'jubelio:token';

    private const LOGIN_LOCK = 'jubelio:login';

    private const LOGIN_FAILED_KEY = 'jubelio:login-failed';

    public function __construct(
        private readonly Repository $cache,
        private readonly int $tokenTtl,
        private readonly int $loginFailureCooldown,
    ) {}

    /**
     * @param  Closure(): string  $login  Performs INT-JB-01 and returns the token
     */
    public function token(Closure $login): string
    {
        if (is_string($token = $this->cache->get(self::TOKEN_KEY))) {
            return $token;
        }

        try {
            return $this->cache->lock(self::LOGIN_LOCK, 30)->block(20, function () use ($login): string {
                // Another process may have logged in while this one waited for the lock.
                if (is_string($token = $this->cache->get(self::TOKEN_KEY))) {
                    return $token;
                }

                if ($this->cache->has(self::LOGIN_FAILED_KEY)) {
                    throw new JubelioApiException('jubelio', 'POST', '/login', retryable: false,
                        providerMessage: 'Login Jubelio ditahan sementara setelah kredensial ditolak.');
                }

                try {
                    $token = $login();
                } catch (JubelioApiException $e) {
                    // Only a rejection by Jubelio pauses logins; empty .env values do
                    // not, so filling them in works right away.
                    if ($e->isInvalidCredentials()) {
                        $this->cache->put(self::LOGIN_FAILED_KEY, true, $this->loginFailureCooldown);
                    }

                    throw $e;
                }

                $this->cache->put(self::TOKEN_KEY, $token, $this->tokenTtl);

                return $token;
            });
        } catch (LockTimeoutException) {
            throw new JubelioApiException('jubelio', 'POST', '/login',
                providerMessage: 'Menunggu proses lain login ke Jubelio terlalu lama.');
        }
    }

    /**
     * Drops a token Jubelio rejected (401), unless another process already replaced it.
     */
    public function forget(string $rejectedToken): void
    {
        if ($this->cache->get(self::TOKEN_KEY) === $rejectedToken) {
            $this->cache->forget(self::TOKEN_KEY);
        }
    }
}
