<?php

use Illuminate\Console\Scheduling\Schedule;

test('the scheduler drains the queue every minute (shared hosting has no long-running worker)', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'queue:work'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->command)->toContain('--stop-when-empty')
        ->and($event->withoutOverlapping)->toBeTrue();
});
