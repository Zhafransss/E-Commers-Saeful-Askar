<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A cron that the host runs rarely or not at all leaves the catalog and stock sync, and the check on overdue
 * payments, undone. When a web request has been answered, the tasks of the scheduler that are due are run.
 *
 * The scheduler only knows "due in this minute", so a visit that lands in some other minute would let a task
 * go by. The kicker therefore remembers the last minute it looked at and, on the next visit, runs every task
 * that fell due since then, once each however many times it was missed (up to a day back).
 *
 * At most one kick a minute across all requests (an atomic lock in the cache). The cron stays as a backup, and
 * each task keeps its own `withoutOverlapping()`, so the two never run one task twice at the same time. The
 * queue is worked by QueueKicker, so the scheduler's own `queue:work` task skips itself during a kick.
 * Off with SCHEDULE_KICK_ON_REQUEST=false, and never in the console.
 */
class ScheduleKicker
{
    /** Longest a kick may take, in seconds, for the PHP time limit of the host. */
    private const TIME_LIMIT = 300;

    /** How far back a missed task is still caught up. */
    private const CATCH_UP_MINUTES = 24 * 60;

    private const LAST_KEY = 'schedule:last_checked';

    private static bool $inKick = false;

    /**
     * True while the scheduler runs because of a web request (used by the queue task to skip itself).
     */
    public static function inKick(): bool
    {
        return self::$inKick;
    }

    /**
     * Called when the request has been answered.
     */
    public static function terminate(): void
    {
        if (! self::enabled() || self::$inKick) {
            return;
        }

        // One kick a minute for the whole site; the first request of the minute takes it.
        if (! Cache::add('schedule:kick', true, 60)) {
            return;
        }

        self::$inKick = true;

        try {
            @set_time_limit(self::TIME_LIMIT);
            self::runDue();
        } catch (Throwable $e) {
            Log::warning("Schedule kick failed: {$e->getMessage()}");
        } finally {
            self::$inKick = false;
        }
    }

    public static function reset(): void
    {
        self::$inKick = false;
    }

    private static function runDue(): void
    {
        // The tasks are defined in routes/console.php, which a web request has not loaded.
        app(ConsoleKernel::class)->bootstrap();

        $schedule = app(Schedule::class);
        $now = Carbon::now()->startOfMinute();
        $last = Cache::get(self::LAST_KEY);
        $from = $last === null ? $now->copy() : Carbon::createFromTimestamp($last)->addMinute();

        if ($from->lt($now->copy()->subMinutes(self::CATCH_UP_MINUTES))) {
            $from = $now->copy()->subMinutes(self::CATCH_UP_MINUTES);
        }

        Cache::forever(self::LAST_KEY, $now->timestamp);

        foreach (self::dueSince($schedule, $from, $now) as $event) {
            if (! $event->filtersPass(app())) {
                continue;
            }

            try {
                self::runEvent($event);
            } catch (Throwable $e) {
                Log::warning("Scheduled task failed: {$e->getMessage()}");
            }
        }
    }

    /**
     * A command task runs inside this process (Artisan::call) rather than as `php artisan ...` in a new one: in a web
     * request the PHP of the server is not necessarily one that can be started from the command line. Its overlap
     * guard is kept; a task that is not a command (a closure) runs as the scheduler would.
     */
    private static function runEvent(Event $event): void
    {
        // The product sync takes minutes and the host stops a PHP process of a web request before that, leaving its
        // log `running`. It is left to the cron (`schedule:run` in the console).
        if (str_starts_with((string) $event->description, 'jubelio:sync-products')) {
            return;
        }

        if (! preg_match("/'artisan'\\s+(.+)$/s", (string) $event->command, $match)) {
            $event->run(app());

            return;
        }

        if ($event->withoutOverlapping && ! $event->mutex->create($event)) {
            return;
        }

        try {
            Artisan::call($match[1]);
        } finally {
            if ($event->withoutOverlapping) {
                $event->mutex->forget($event);
            }
        }
    }

    /**
     * Every task that was due in any minute from $from to $now, each once.
     *
     * @return array<int, Event>
     */
    private static function dueSince(Schedule $schedule, Carbon $from, Carbon $now): array
    {
        $previous = Carbon::getTestNow();
        $due = [];

        try {
            for ($minute = $from->copy(); $minute->lte($now); $minute->addMinute()) {
                Carbon::setTestNow($minute->copy());

                foreach ($schedule->events() as $index => $event) {
                    if (! isset($due[$index]) && $event->isDue(app())) {
                        $due[$index] = $event;
                    }
                }
            }
        } finally {
            Carbon::setTestNow($previous);
        }

        return $due;
    }

    private static function enabled(): bool
    {
        return config('queue.kick_schedule_on_request') && ! app()->runningInConsole();
    }
}
