<?php

namespace App\Services\Webhooks;

use App\Enums\WebhookEventStatus;
use App\Enums\WebhookProvider;
use App\Exceptions\ApiException;
use App\Jobs\ProcessJubelioWebhookJob;
use App\Jobs\ProcessXenditWebhookJob;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Intake shared by every webhook (docs/api/webhooks.md, "pola yang sama"):
 *
 *   1. verify the token/signature; failure → 401, but the event is still recorded
 *      with signature_valid = false;
 *   2. store in webhook_events under a unique idempotency_key; a key seen before
 *      → 200 without processing it again;
 *   3. answer 200 right away;
 *   4. process in the queue (ProcessXenditWebhookJob / ProcessJubelioWebhookJob).
 *
 * WH-01 – WH-05 controllers call xendit() / jubelio(); the business logic of
 * each event lives in a WebhookHandler (config/webhooks.php).
 */
class WebhookReceiver
{
    /**
     * WH-01. `id`, `external_id`, and `status` are required by the contract.
     */
    public function xendit(Request $request): JsonResponse
    {
        $expected = (string) config('xendit.callback_token');
        $given = (string) $request->header('x-callback-token');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            $this->recordRejected(WebhookProvider::Xendit, 'invoice', $request, 'Callback token tidak valid.');

            throw new ApiException('Callback token tidak valid.', 'INVALID_CALLBACK_TOKEN', 401);
        }

        $payload = $this->validated($request, [
            'id' => ['required', 'string'],
            'external_id' => ['required', 'string'],
            'status' => ['required', 'string'],
        ]);

        $this->store(
            WebhookProvider::Xendit,
            'invoice',
            "xendit:{$payload['id']}:{$payload['status']}",
            $payload,
            "{$payload['external_id']} {$payload['status']}",
            ProcessXenditWebhookJob::class,
        );

        return response()->json(['received' => true]);
    }

    /**
     * WH-02 – WH-05.
     *
     * @param  string  $action  Jubelio action the route receives, e.g. `update-qty`
     * @param  array<string, mixed>  $rules  Required payload fields from the endpoint's contract
     */
    public function jubelio(Request $request, string $action, array $rules = []): JsonResponse
    {
        $raw = $request->getContent();

        if (! $this->validJubelioSignature($request, $raw)) {
            $headers = implode(', ', array_keys($request->headers->all()));
            $this->recordRejected(WebhookProvider::Jubelio, $action, $request, "Signature webhook tidak valid. Header diterima: {$headers}.");

            throw new ApiException('Signature webhook tidak valid.', 'INVALID_SIGNATURE', 401);
        }

        $payload = $this->validated($request, $rules);

        $this->store(
            WebhookProvider::Jubelio,
            $action,
            "jubelio:{$action}:".hash('sha256', $raw),
            $payload,
            $this->jubelioSummary($action, $payload),
            ProcessJubelioWebhookJob::class,
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * signature = sha256(raw body + secret), compared in constant time (WH-02).
     * No secret or no configured header means nothing can be trusted.
     */
    private function validJubelioSignature(Request $request, string $raw): bool
    {
        $secret = (string) config('jubelio.webhook_secret');
        $header = (string) config('jubelio.webhook_signature_header');

        if ($secret === '' || $header === '') {
            return false;
        }

        return hash_equals(hash('sha256', $raw.$secret), strtolower((string) $request->header($header)));
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed> The full payload (handlers may need fields beyond the rules)
     */
    private function validated(Request $request, array $rules): array
    {
        $payload = $request->json()->all();
        Validator::make($payload, $rules)->validate();

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  class-string  $job
     */
    private function store(WebhookProvider $provider, string $eventType, string $key, array $payload, string $summary, string $job): void
    {
        // createOrFirst relies on the unique index, so two identical deliveries
        // arriving at the same moment still produce a single event.
        $event = WebhookEvent::createOrFirst(['idempotency_key' => $key], [
            'provider' => $provider,
            'event_type' => $eventType,
            'signature_valid' => true,
            'status' => WebhookEventStatus::Received,
            'payload' => $payload,
            'summary' => Str::limit($summary, 250),
            'received_at' => now(),
        ]);

        if ($event->wasRecentlyCreated) {
            $job::dispatch($event->id);
        }
    }

    /**
     * Rejected deliveries are kept so forgery attempts stay visible (ADM-15, v1.1).
     * Each gets its own key: they must never block a later genuine delivery.
     */
    private function recordRejected(WebhookProvider $provider, string $eventType, Request $request, string $error): void
    {
        $payload = json_decode($request->getContent(), true);

        WebhookEvent::create([
            'provider' => $provider,
            'event_type' => $eventType,
            'idempotency_key' => 'invalid:'.Str::uuid(),
            'signature_valid' => false,
            'status' => WebhookEventStatus::Ignored,
            'payload' => is_array($payload) ? $payload : ['_raw' => Str::limit($request->getContent(), 2000)],
            'summary' => 'Ditolak: verifikasi gagal',
            'error' => $error,
            'received_at' => now(),
            'processed_at' => now(),
        ]);
    }

    /**
     * e.g. "update-qty item_id=2976 location_id=1".
     *
     * @param  array<string, mixed>  $payload
     */
    private function jubelioSummary(string $action, array $payload): string
    {
        $ids = collect($payload)
            ->filter(fn (mixed $value, string $key) => is_scalar($value) && preg_match('/(_id|_no)$/', $key))
            ->map(fn (mixed $value, string $key) => "{$key}={$value}");

        return trim($action.' '.$ids->implode(' '));
    }
}
