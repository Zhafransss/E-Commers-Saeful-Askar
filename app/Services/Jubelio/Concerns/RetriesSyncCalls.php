<?php

namespace App\Services\Jubelio\Concerns;

use App\Exceptions\IntegrationException;
use Closure;
use Illuminate\Support\Sleep;

/**
 * For the catalog and stock syncs, which run from the scheduler or a command and have no queue to try
 * again (App\Jobs\Concerns\RetriesIntegrationCalls does that for jobs). A full sync is hundreds of calls,
 * so one that Jubelio does not answer (timeout, 5xx) or refuses for a minute (429) must not end it.
 *
 * Only for the sync's own calls, never while a customer waits: a 429 is waited out here.
 */
trait RetriesSyncCalls
{
    /**
     * @template T
     *
     * @param  Closure(): T  $call  Safe to run again: it only reads from Jubelio and writes with upserts
     * @return T
     *
     * @throws IntegrationException When it is not worth trying again, or the last attempt failed
     */
    protected function retrying(Closure $call): mixed
    {
        $pauses = [5, 15, 30];

        foreach ($pauses as $pause) {
            try {
                return $call();
            } catch (IntegrationException $e) {
                if (! $e->retryable) {
                    throw $e;
                }

                Sleep::for($e->retryAfter ?? $pause)->seconds();
            }
        }

        return $call();
    }
}
