<?php

use Illuminate\Console\Scheduling\Schedule;

test('the scheduler drains the queue every minute (shared hosting has no long-running worker)', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => $e->description === 'queue:work --stop-when-empty --max-time=50 --tries=3');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

test('no scheduled task starts a child process (proc_open is off on shared hosting)', function () {
    $commands = collect(app(Schedule::class)->events())->filter(fn ($e) => $e->command !== null && $e->command !== '');

    expect($commands)->toBeEmpty();
});

test('the Jubelio, Gapura and queue tasks are all scheduled', function () {
    $names = collect(app(Schedule::class)->events())->pluck('description');

    expect($names)->toContain('jubelio:sync-categories', 'jubelio:sync-products', 'jubelio:sync-products --full', 'jubelio:reconcile-stock', 'orders:expire-unpaid', 'sync-logs:prune');
});
