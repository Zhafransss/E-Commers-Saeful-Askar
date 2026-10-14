<?php

/*
| Scheduled jobs (PRD §4, docs/arsitektur-sistem.md). Owner: backend.
| Locally `composer dev` runs `schedule:work`; on the server a cron entry runs
| `php artisan schedule:run` every minute.
|
| Register each command here once it exists:
|
|   Schedule::command('jubelio:sync-categories')->dailyAt('02:00')->withoutOverlapping();
|   Schedule::command('jubelio:sync-products --full')->dailyAt('02:30')->withoutOverlapping();  // full sync, nightly
|   Schedule::command('jubelio:sync-products')->everyThirtyMinutes()->withoutOverlapping();    // incremental (last_modified)
|   Schedule::command('jubelio:reconcile-stock')->everyFifteenMinutes()->withoutOverlapping(); // backup for missed update-qty
|   Schedule::command('orders:expire-unpaid')->everyFiveMinutes()->withoutOverlapping();      // backup for missed EXPIRED (WH-01)
|   Schedule::command('sync-logs:prune')->daily();                                            // drop sync_logs older than 90 days (ADM-14)
|
| withoutOverlapping() uses atomic locks in the `database` cache store (no Redis).
*/

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
