<?php

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\HandleIntegrationErrors;

/**
 * Retry policy for queued jobs that call external services (INT-HDL):
 * the first attempt plus 5 retries, waiting 10 s, 30 s, 2 min, 5 min, 15 min.
 *
 * A job that needs more middleware merges its own with integrationMiddleware().
 * After the last attempt Laravel calls the job's failed() method, which is
 * where a job marks its record as failed (e.g. jubelio_sync_status = failed).
 */
trait RetriesIntegrationCalls
{
    public int $tries = 6;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 120, 300, 900];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return $this->integrationMiddleware();
    }

    /**
     * @return list<object>
     */
    protected function integrationMiddleware(): array
    {
        return [new HandleIntegrationErrors];
    }
}
