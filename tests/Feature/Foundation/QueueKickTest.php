<?php

use App\Models\User;
use App\Support\QueueKicker;
use App\Support\ScheduleKicker;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
| Shared hosting: a web request that queues a job works the queue right after its response (App\Support\QueueKicker),
| so mail and payment follow-up do not wait for a cron the host may run rarely.
*/

/**
 * The test process is a console, which the kicker never serves; this makes the app believe it is serving a web request.
 */
function pretendWebRequest(bool $web): void
{
    $property = new ReflectionProperty(app(), 'isRunningInConsole');
    $property->setValue(app(), ! $web);
}

beforeEach(function () {
    // The scheduler kick is off here, so the real scheduled tasks (Jubelio, DANA) never run during these requests.
    config(['queue.default' => 'database', 'mail.default' => 'array', 'queue.kick_on_request' => true, 'queue.kick_schedule_on_request' => false]);
    app('mail.manager')->forgetMailers();
    QueueKicker::reset();
    ScheduleKicker::reset();
    DB::table('jobs')->delete();
    pretendWebRequest(true);
    $this->customer = User::factory()->create(['email' => 'siti@example.com']);
});

afterEach(function () {
    pretendWebRequest(false);
    QueueKicker::reset();
    ScheduleKicker::reset();
});

function sentMails(): int
{
    return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->count();
}

test('a request that queues a job has the queue worked right after its response', function () {
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'siti@example.com'])->assertOk();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(sentMails())->toBe(1);
});

test('nothing is worked when the request queued nothing', function () {
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

    expect(DB::table('jobs')->count())->toBe(0)->and(sentMails())->toBe(0);
});

test('the kick can be switched off, and then the job waits for the cron or a worker', function () {
    config(['queue.kick_on_request' => false]);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'siti@example.com'])->assertOk();

    expect(DB::table('jobs')->count())->toBe(1)->and(sentMails())->toBe(0);
});

test('a console, a cron run or a queue worker never kicks another worker', function () {
    pretendWebRequest(false);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'siti@example.com'])->assertOk();

    expect(DB::table('jobs')->count())->toBe(1);
});

test('the kick works only for the database queue', function () {
    config(['queue.default' => 'sync']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'siti@example.com'])->assertOk();

    // The sync queue already runs the job inside the request; nothing is left over to kick.
    expect(DB::table('jobs')->count())->toBe(0)->and(sentMails())->toBe(1);
});

// --- The scheduler, kicked the same way (App\Support\ScheduleKicker) ----------------------------

/**
 * Leaves the scheduler with one task of the test's own, so the real ones (Jubelio, DANA) cannot run.
 *
 * @return object{ran: int}
 */
function schedulerWithOneTask(): object
{
    $schedule = app(Schedule::class);
    (new ReflectionProperty($schedule, 'events'))->setValue($schedule, []);
    $probe = new stdClass;
    $probe->ran = 0;
    $schedule->call(function () use ($probe) {
        $probe->ran++;
    })->everyMinute();

    return $probe;
}

test('a web request runs the scheduler once a minute at most, after its response', function () {
    config(['queue.kick_schedule_on_request' => true]);
    Cache::forget('schedule:kick');
    $probe = schedulerWithOneTask();

    $this->get('/up')->assertOk();
    $this->get('/up')->assertOk();
    $this->get('/up')->assertOk();

    expect($probe->ran)->toBe(1);

    // The same minute is never looked at twice, even when the lock is gone; the next minute is.
    Cache::forget('schedule:kick');
    $this->get('/up')->assertOk();
    expect($probe->ran)->toBe(1);

    Carbon::setTestNow(Carbon::now()->addMinutes(2));
    Cache::forget('schedule:kick');
    $this->get('/up')->assertOk();

    expect($probe->ran)->toBe(2);

    Carbon::setTestNow();
});

test('a task that fell due while nobody visited is run on the next visit, once however often it was missed', function () {
    config(['queue.kick_schedule_on_request' => true]);
    Cache::forget('schedule:kick');
    $schedule = app(Schedule::class);
    (new ReflectionProperty($schedule, 'events'))->setValue($schedule, []);
    $every15 = $nightly = $notYet = 0;
    $schedule->call(function () use (&$every15) {
        $every15++;
    })->everyFifteenMinutes();
    $schedule->call(function () use (&$nightly) {
        $nightly++;
    })->dailyAt('02:00');
    $schedule->call(function () use (&$notYet) {
        $notYet++;
    })->dailyAt('23:00');

    // It is 08:07: the last look was at 01:00, and no visit came at 02:00 or at any quarter hour since.
    Carbon::setTestNow('2026-10-22 08:07:20');
    Cache::forever('schedule:last_checked', Carbon::parse('2026-10-22 01:00:00')->timestamp);

    $this->get('/up')->assertOk();

    expect($every15)->toBe(1)->and($nightly)->toBe(1)->and($notYet)->toBe(0);
    expect(Carbon::now()->toDateTimeString())->toBe('2026-10-22 08:07:20');

    // The next visit, a minute later, has nothing left over to catch up.
    Cache::forget('schedule:kick');
    Carbon::setTestNow('2026-10-22 08:08:20');
    $this->get('/up')->assertOk();

    expect($every15)->toBe(1)->and($nightly)->toBe(1);

    Carbon::setTestNow();
});

test('a command task runs inside the request, once, and keeps its overlap guard', function () {
    config(['queue.kick_schedule_on_request' => true]);
    Cache::forget('schedule:kick');
    Cache::forget('schedule:last_checked');
    $runs = [];
    Artisan::command('kicktest {--flag}', function () use (&$runs) {
        $runs[] = $this->option('flag');
    });
    $schedule = app(Schedule::class);
    (new ReflectionProperty($schedule, 'events'))->setValue($schedule, []);
    $schedule->command('kicktest --flag')->everyMinute()->withoutOverlapping();

    Carbon::setTestNow('2026-10-22 08:07:20');
    $this->get('/up')->assertOk();
    expect($runs)->toBe([true]);

    // A task still marked as running (another worker, or the cron) is not started again.
    $event = $schedule->events()[0];
    $event->mutex->create($event);
    Cache::forget('schedule:kick');
    Carbon::setTestNow('2026-10-22 08:09:20');
    $this->get('/up')->assertOk();
    expect($runs)->toBe([true]);

    Carbon::setTestNow();
});

test('the first kick does not catch up on a past it never saw, and a very old last look is caught up at most a day back', function () {
    config(['queue.kick_schedule_on_request' => true]);
    Cache::forget('schedule:kick');
    Cache::forget('schedule:last_checked');
    $schedule = app(Schedule::class);
    (new ReflectionProperty($schedule, 'events'))->setValue($schedule, []);
    $ran = 0;
    $schedule->call(function () use (&$ran) {
        $ran++;
    })->dailyAt('02:00');

    Carbon::setTestNow('2026-10-22 08:07:20');
    $this->get('/up')->assertOk();
    expect($ran)->toBe(0);

    Cache::forget('schedule:kick');
    Cache::forever('schedule:last_checked', Carbon::parse('2026-10-01 00:00:00')->timestamp);
    $this->get('/up')->assertOk();
    expect($ran)->toBe(1);

    Carbon::setTestNow();
});

test('the scheduler kick can be switched off, and never runs in the console', function () {
    $probe = schedulerWithOneTask();
    Cache::forget('schedule:kick');

    config(['queue.kick_schedule_on_request' => false]);
    $this->get('/up')->assertOk();

    config(['queue.kick_schedule_on_request' => true]);
    pretendWebRequest(false);
    $this->get('/up')->assertOk();

    expect($probe->ran)->toBe(0);
});

test('the queue task of the scheduler skips itself during a kick, since the queue is already worked', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'queue:work'));
    $inKick = new ReflectionProperty(ScheduleKicker::class, 'inKick');

    expect($event)->not->toBeNull()->and($event->filtersPass(app()))->toBeTrue();

    $inKick->setValue(null, true);
    expect($event->filtersPass(app()))->toBeFalse();

    $inKick->setValue(null, false);
});
