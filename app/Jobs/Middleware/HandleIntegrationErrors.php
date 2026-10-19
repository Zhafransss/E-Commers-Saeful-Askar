<?php

namespace App\Jobs\Middleware;

use App\Exceptions\IntegrationException;
use Closure;

/**
 * Applies INT-HDL to a queued job that calls Jubelio, Jubelio Shipment, or Xendit:
 * - not retryable (Xendit 4xx, wrong Jubelio credentials) → fail now, no retry;
 * - Jubelio 429 → try again after the wait it asked for (60 seconds);
 * - anything else → rethrow, so the queue retries with the job's backoff.
 */
class HandleIntegrationErrors
{
    public function handle(object $job, Closure $next): void
    {
        try {
            $next($job);
        } catch (IntegrationException $e) {
            if (! $e->retryable) {
                $job->fail($e);

                return;
            }

            if ($e->retryAfter !== null) {
                $job->release($e->retryAfter);

                return;
            }

            throw $e;
        }
    }
}
