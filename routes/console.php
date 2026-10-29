<?php

/*
| Scheduled jobs (PRD §4, docs/arsitektur-sistem.md). Owner: backend.
| Locally `composer dev` runs `schedule:work`; on the server a cron entry runs
| `php artisan schedule:run` every minute.
|
| Still to register when their commands exist:
|
|   (orders:expire-unpaid is registered below.)
|
| withoutOverlapping() uses atomic locks in the `database` cache store (no Redis).
| The nightly times are a proposal (docs/roadmap-backend.md Fase 2), not fixed by the contract.
*/

use App\Models\SyncLog;
use App\Support\ScheduleKicker;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Catalog sync (PRD §4): categories before the full product sync.
Schedule::command('jubelio:sync-categories')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('jubelio:sync-products --full')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('jubelio:sync-products')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('jubelio:reconcile-stock')->everyFifteenMinutes()->withoutOverlapping();

// Drop sync logs older than 90 days (ADM-14).
Schedule::command('sync-logs:prune')->daily();

// A catalog sync whose process the host killed would stay `running`; close it after 30 minutes (ADM-14).
Schedule::call(fn () => SyncLog::failStale())->name('sync-logs:fail-stale')->everyFiveMinutes();

// Backup for a Finish Notify that never arrived (WH-01): ask Gapura about orders past their deadline.
Schedule::command('orders:expire-unpaid')->everyFiveMinutes()->withoutOverlapping();

// Shared hosting (branch `deployment`): no long-running worker is possible, so the minutely cron
// that runs `schedule:run` also drains the queue. --max-time stays below retry_after (90 s) and
// typical PHP time limits; withoutOverlapping() keeps two workers from running at once.
//
// A scheduler run started by a web request (App\Support\ScheduleKicker) skips this one: the same request
// has already worked the queue (App\Support\QueueKicker).
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->skip(fn () => ScheduleKicker::inKick());

// Manual syncs of the admin (ADM-13) wait on the `sync` queue of the `database-long` connection: they run for
// minutes, so only this CLI worker takes them, never a web request. The cron must run `schedule:run` every minute.
Schedule::command('queue:work database-long --queue=sync --stop-when-empty --tries=1')
    ->everyMinute()
    ->withoutOverlapping(35)
    ->skip(fn () => ScheduleKicker::inKick());
