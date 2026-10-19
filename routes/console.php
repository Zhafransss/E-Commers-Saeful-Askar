<?php

/*
| Scheduled jobs (PRD §4, docs/arsitektur-sistem.md). Owner: backend.
| Locally `composer dev` runs `schedule:work`; on the server a cron entry runs
| `php artisan schedule:run` every minute.
|
| Still to register when their commands exist:
|
|   Schedule::command('orders:expire-unpaid')->everyFiveMinutes()->withoutOverlapping();  // backup for missed EXPIRED (WH-01), Fase 5
|   Schedule::command('sync-logs:prune')->daily();                                        // drop sync_logs older than 90 days (ADM-14), Fase 6
|
| withoutOverlapping() uses atomic locks in the `database` cache store (no Redis).
| The nightly times are a proposal (docs/roadmap-backend.md Fase 2), not fixed by the contract.
*/

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
