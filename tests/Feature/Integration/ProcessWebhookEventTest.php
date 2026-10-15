<?php

use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessJubelioWebhookJob;
use App\Models\WebhookEvent;
use App\Services\Webhooks\WebhookEventIgnored;
use App\Services\Webhooks\WebhookEventRejected;
use App\Services\Webhooks\WebhookHandler;
use App\Services\Webhooks\WebhookReceiver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
| Step 4 of the webhook pattern: the queued job runs the handler registered in
| config/webhooks.php and settles the event as processed / ignored / failed.
*/

class RecordingWebhookHandler implements WebhookHandler
{
    /** @var list<int> */
    public static array $handled = [];

    public function handle(WebhookEvent $event): void
    {
        self::$handled[] = $event->id;
    }
}

class IgnoringWebhookHandler implements WebhookHandler
{
    public function handle(WebhookEvent $event): void
    {
        throw new WebhookEventIgnored('Order dari toko lain.');
    }
}

class RejectingWebhookHandler implements WebhookHandler
{
    public function handle(WebhookEvent $event): void
    {
        throw new WebhookEventRejected('Dibayar setelah dibatalkan — perlu refund/proses manual.');
    }
}

class CrashingWebhookHandler implements WebhookHandler
{
    public function handle(WebhookEvent $event): void
    {
        throw new RuntimeException('Jubelio sedang gangguan.');
    }
}

function useJubelioHandler(string $handler): void
{
    config(['webhooks.handlers.jubelio.update-product' => $handler]);
}

beforeEach(fn () => RecordingWebhookHandler::$handled = []);

test('a handled event becomes processed', function () {
    useJubelioHandler(RecordingWebhookHandler::class);
    $event = WebhookEvent::factory()->create();

    (new ProcessJubelioWebhookJob($event->id))->handle();

    expect(RecordingWebhookHandler::$handled)->toBe([$event->id])
        ->and($event->fresh())
        ->status->toBe(WebhookEventStatus::Processed)
        ->attempts->toBe(1)
        ->processed_at->not->toBeNull();
});

test('an event that is no longer received is never processed again', function () {
    useJubelioHandler(RecordingWebhookHandler::class);
    $event = WebhookEvent::factory()->create();

    (new ProcessJubelioWebhookJob($event->id))->handle();
    (new ProcessJubelioWebhookJob($event->id))->handle();

    expect(RecordingWebhookHandler::$handled)->toHaveCount(1)
        ->and($event->fresh()->attempts)->toBe(1);
});

test('WebhookEventIgnored marks the event ignored with its reason', function () {
    useJubelioHandler(IgnoringWebhookHandler::class);
    $event = WebhookEvent::factory()->create();

    (new ProcessJubelioWebhookJob($event->id))->handle();

    expect($event->fresh())->status->toBe(WebhookEventStatus::Ignored)->error->toBe('Order dari toko lain.');
});

test('WebhookEventRejected marks the event failed without retry and logs a warning', function () {
    Log::spy();
    useJubelioHandler(RejectingWebhookHandler::class);
    $event = WebhookEvent::factory()->create();

    (new ProcessJubelioWebhookJob($event->id))->handle();

    expect($event->fresh())->status->toBe(WebhookEventStatus::Failed)
        ->error->toBe('Dibayar setelah dibatalkan — perlu refund/proses manual.');
    Log::shouldHaveReceived('warning')->once();
});

test('an event type without a handler is marked failed', function () {
    $event = WebhookEvent::factory()->create();

    (new ProcessJubelioWebhookJob($event->id))->handle();

    expect($event->fresh())->status->toBe(WebhookEventStatus::Failed)
        ->error->toBe('Belum ada pemroses untuk event update-product.');
});

test('other errors are rethrown for the queue to retry, and failed() settles the event at the end', function () {
    useJubelioHandler(CrashingWebhookHandler::class);
    $event = WebhookEvent::factory()->create();
    $job = new ProcessJubelioWebhookJob($event->id);

    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
    expect($event->fresh())->status->toBe(WebhookEventStatus::Received)->attempts->toBe(1);

    $job->failed(new RuntimeException('Jubelio sedang gangguan.'));
    expect($event->fresh())->status->toBe(WebhookEventStatus::Failed)->error->toBe('Jubelio sedang gangguan.');
});

test('end to end: a delivered webhook is processed through the queue', function () {
    useJubelioHandler(RecordingWebhookHandler::class);
    config(['jubelio.webhook_secret' => 'rahasia-webhook']);
    Route::post('/webhooks/__test/jubelio', fn (Request $request, WebhookReceiver $receiver) => $receiver
        ->jubelio($request, 'update-product', ['item_group_id' => ['required', 'integer']]));
    $body = fixtureContent('jubelio/webhooks/update-product.json');

    $this->call('POST', '/webhooks/__test/jubelio', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_SIGN' => jubelioSignature($body),
    ], $body)->assertOk();

    expect(WebhookEvent::sole()->status)->toBe(WebhookEventStatus::Processed)
        ->and(RecordingWebhookHandler::$handled)->toHaveCount(1);
});
