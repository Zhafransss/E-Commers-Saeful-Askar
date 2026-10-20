<?php

namespace App\Services\Webhooks;

use App\Enums\WebhookEventStatus;
use App\Enums\WebhookProvider;
use App\Exceptions\ApiException;
use App\Jobs\ProcessGapuraWebhookJob;
use App\Jobs\ProcessJubelioWebhookJob;
use App\Models\WebhookEvent;
use App\Services\Gapura\GapuraClient;
use App\Services\Gapura\GapuraSignatureException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Intake shared by every webhook (docs/api/webhooks.md, "pola yang sama"):
 *
 *   1. verify the signature; failure → 401, but the event is still recorded
 *      with signature_valid = false;
 *   2. store in webhook_events under a unique idempotency_key; a key seen before
 *      → 200 without processing it again;
 *   3. answer 200 right away;
 *   4. process in the queue (ProcessGapuraWebhookJob / ProcessJubelioWebhookJob).
 *
 * WH-01 – WH-05 controllers call gapura() / jubelio(); the business logic of
 * each event lives in a WebhookHandler (config/webhooks.php).
 */
class WebhookReceiver
{
    /**
     * WH-01, DANA's Finish Notify. The signature is checked by the SDK against DANA's public key
     * and the fixed path /v1.0/debit/notify. DANA wants `{"responseCode":"2005600", …}` back, and
     * retries for up to 7 days when it does not get it.
     */
    public function gapura(Request $request, GapuraClient $gapura): JsonResponse
    {
        $raw = $request->getContent();
        $headers = collect($request->headers->all())->map(fn (array $values) => (string) ($values[0] ?? ''))->all();

        try {
            $notification = $gapura->parseNotification($raw, $headers);
        } catch (GapuraSignatureException $e) {
            $this->recordRejected(WebhookProvider::Gapura, 'notify', $request, "Signature webhook tidak valid: {$e->getMessage()}");

            throw new ApiException('Signature webhook tidak valid.', 'INVALID_SIGNATURE', 401);
        }

        $payload = $this->validated($request, [
            'originalPartnerReferenceNo' => ['required', 'string'],
            'originalReferenceNo' => ['required', 'string'],
            'latestTransactionStatus' => ['required', 'string'],
        ]);

        $this->store(
            WebhookProvider::Gapura,
            'notify',
            "gapura:{$notification->partnerReferenceNo}:{$notification->statusCode}",
            $payload,
            "{$notification->partnerReferenceNo} {$notification->statusCode}",
            ProcessGapuraWebhookJob::class,
        );

        return response()->json(['responseCode' => '2005600', 'responseMessage' => 'Successful']);
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
     * Header `sign` = HMAC-SHA256 of (trimmed raw body + secret) keyed with the
     * secret, as in Jubelio's official PHP example (docs/api/dist.yaml, "Webhook
     * Signature"; WH-02). Compared in constant time. No secret means nothing can
     * be trusted.
     */
    private function validJubelioSignature(Request $request, string $raw): bool
    {
        $secret = (string) config('jubelio.webhook_secret');
        $header = (string) config('jubelio.webhook_signature_header');

        if ($secret === '' || $header === '') {
            return false;
        }

        $expected = hash_hmac('sha256', trim($raw).$secret, $secret);

        return hash_equals($expected, strtolower((string) $request->header($header)));
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
