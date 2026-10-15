<?php

use App\Exceptions\IntegrationException;
use App\Jobs\Concerns\RetriesIntegrationCalls;
use App\Jobs\Middleware\HandleIntegrationErrors;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/*
| INT-HDL queue retry: 5 retries at 10 s, 30 s, 2 min, 5 min, 15 min; Xendit 4xx
| (not retryable) fails at once; Jubelio 429 waits 60 seconds.
*/

class RetryPolicyTestJob implements ShouldQueue
{
    use Queueable, RetriesIntegrationCalls;

    public function __construct(public ?Throwable $error = null) {}

    public function handle(): void
    {
        if ($this->error !== null) {
            throw $this->error;
        }
    }
}

function runThroughIntegrationMiddleware(RetryPolicyTestJob $job): void
{
    (new HandleIntegrationErrors)->handle($job, fn (RetryPolicyTestJob $job) => $job->handle());
}

test('jobs get the first attempt plus 5 retries with the contract backoff', function () {
    $job = new RetryPolicyTestJob;

    expect($job->tries)->toBe(6)
        ->and($job->backoff())->toBe([10, 30, 120, 300, 900])
        ->and($job->middleware()[0])->toBeInstanceOf(HandleIntegrationErrors::class);
});

test('a non-retryable error fails the job immediately', function () {
    $error = new IntegrationException('xendit', 'POST', '/v2/invoices', 400, retryable: false);
    $job = (new RetryPolicyTestJob($error))->withFakeQueueInteractions();

    runThroughIntegrationMiddleware($job);

    $job->assertFailedWith($error);
});

test('a 429 releases the job for the requested 60 seconds', function () {
    $job = (new RetryPolicyTestJob(new IntegrationException('jubelio', 'GET', '/inventory/', 429, retryAfter: 60)))->withFakeQueueInteractions();

    runThroughIntegrationMiddleware($job);

    $job->assertReleased(60);
    $job->assertNotFailed();
});

test('other retryable errors are rethrown so the queue retries with backoff', function () {
    $job = (new RetryPolicyTestJob(new IntegrationException('jubelio', 'GET', '/inventory/', 503)))->withFakeQueueInteractions();

    expect(fn () => runThroughIntegrationMiddleware($job))->toThrow(IntegrationException::class);
    $job->assertNotFailed();
    $job->assertNotReleased();
});
