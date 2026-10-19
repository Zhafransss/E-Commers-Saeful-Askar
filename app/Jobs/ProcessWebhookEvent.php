<?php

namespace App\Jobs;

use App\Enums\WebhookEventStatus;
use App\Enums\WebhookProvider;
use App\Jobs\Concerns\RetriesIntegrationCalls;
use App\Models\WebhookEvent;
use App\Services\Webhooks\WebhookEventIgnored;
use App\Services\Webhooks\WebhookEventRejected;
use App\Services\Webhooks\WebhookHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Step 4 of the webhook pattern (docs/api/webhooks.md): runs the handler
 * registered for the event type, then settles the event as `processed`,
 * `ignored`, or `failed`. An event that is no longer `received` is never run
 * again, which keeps redelivered webhooks idempotent.
 */
abstract class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable, RetriesIntegrationCalls;

    // Not readonly: the queue unserializes it from the subclass scope.
    public function __construct(public int $webhookEventId) {}

    abstract protected function provider(): WebhookProvider;

    public function handle(): void
    {
        $event = WebhookEvent::find($this->webhookEventId);

        if ($event === null || $event->status !== WebhookEventStatus::Received) {
            return;
        }

        $event->increment('attempts');

        $handler = config("webhooks.handlers.{$this->provider()->value}.{$event->event_type}");

        if ($handler === null) {
            $this->settle($event, WebhookEventStatus::Failed, "Belum ada pemroses untuk event {$event->event_type}.");

            return;
        }

        /** @var WebhookHandler $instance */
        $instance = app($handler);

        try {
            $instance->handle($event);
        } catch (WebhookEventIgnored $e) {
            $this->settle($event, WebhookEventStatus::Ignored, $e->getMessage());

            return;
        } catch (WebhookEventRejected $e) {
            Log::warning("Webhook {$event->provider->value} #{$event->id} ditolak: {$e->getMessage()}", ['summary' => $event->summary]);
            $this->settle($event, WebhookEventStatus::Failed, $e->getMessage());

            return;
        }

        $this->settle($event, WebhookEventStatus::Processed);
    }

    /**
     * All attempts used up, or a non-retryable integration error.
     */
    public function failed(Throwable $exception): void
    {
        $event = WebhookEvent::find($this->webhookEventId);

        if ($event?->status === WebhookEventStatus::Received) {
            $this->settle($event, WebhookEventStatus::Failed, $exception->getMessage());
        }
    }

    private function settle(WebhookEvent $event, WebhookEventStatus $status, ?string $error = null): void
    {
        $event->update([
            'status' => $status,
            'error' => $error === null ? null : Str::limit($error, 5000),
            'processed_at' => now(),
        ]);
    }
}
