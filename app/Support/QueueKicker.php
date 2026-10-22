<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared hosting runs the queue from a cron that the host may run rarely or not at all, so a payment, a reset
 * mail or an invoice could wait for minutes. When a web request puts a job on the `database` queue, the queue
 * is worked for a few seconds right after that request's response has gone out. The cron stays as a backup;
 * two workers never take the same job (the queue table reserves each job for one of them).
 *
 * Off with QUEUE_KICK_ON_REQUEST=false, and never in the console (cron, queue:work and artisan already work
 * the queue) or on any queue other than `database`.
 */
class QueueKicker
{
    /** A job was queued during this request. */
    private static bool $wanted = false;

    /** The worker below is running, so jobs it queues itself do not start another one. */
    private static bool $working = false;

    /** Seconds the worker may run after the response; the next jobs wait for the next kick or the cron. */
    public const MAX_SECONDS = 25;

    public static function jobQueued(): void
    {
        if (! self::enabled()) {
            return;
        }

        self::$wanted = true;
    }

    /**
     * Called when the request has been answered.
     */
    public static function terminate(): void
    {
        if (! self::$wanted || self::$working) {
            return;
        }

        self::$wanted = false;
        self::$working = true;

        try {
            Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => self::MAX_SECONDS, '--tries' => 3]);
        } catch (Throwable $e) {
            // The response is already sent; the cron will still pick the jobs up.
            Log::warning("Queue kick failed: {$e->getMessage()}");
        } finally {
            self::$working = false;
        }
    }

    public static function reset(): void
    {
        self::$wanted = false;
        self::$working = false;
    }

    private static function enabled(): bool
    {
        return config('queue.kick_on_request')
            && ! app()->runningInConsole()
            && config('queue.connections.'.config('queue.default').'.driver') === 'database';
    }
}
