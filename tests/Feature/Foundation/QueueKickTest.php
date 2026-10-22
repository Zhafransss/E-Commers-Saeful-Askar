<?php

use App\Models\User;
use App\Support\QueueKicker;
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
    config(['queue.default' => 'database', 'mail.default' => 'array', 'queue.kick_on_request' => true]);
    app('mail.manager')->forgetMailers();
    QueueKicker::reset();
    DB::table('jobs')->delete();
    pretendWebRequest(true);
    $this->customer = User::factory()->create(['email' => 'siti@example.com']);
});

afterEach(function () {
    pretendWebRequest(false);
    QueueKicker::reset();
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
