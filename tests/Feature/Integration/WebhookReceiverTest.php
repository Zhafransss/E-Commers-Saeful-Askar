<?php

use App\Enums\WebhookEventStatus;
use App\Enums\WebhookProvider;
use App\Jobs\ProcessGapuraWebhookJob;
use App\Jobs\ProcessJubelioWebhookJob;
use App\Models\WebhookEvent;
use App\Services\Gapura\GapuraClient;
use App\Services\Webhooks\WebhookReceiver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
| The intake pattern shared by WH-01 – WH-05 (docs/api/webhooks.md): verify,
| record (also when rejected), deduplicate by idempotency_key, answer 200, queue.
| WH-01 is verified with DANA's public key; the tests sign with a throwaway pair (gapuraKeys()).
| The real WH routes stay 501 until their phase, so test routes are used here.
*/

beforeEach(function () {
    Route::post('/webhooks/__test/jubelio', fn (Request $request, WebhookReceiver $receiver) => $receiver
        ->jubelio($request, 'update-product', ['item_group_id' => ['required', 'integer']]));
    Route::post('/webhooks/__test/gapura', fn (Request $request, WebhookReceiver $receiver, GapuraClient $gapura) => $receiver->gapura($request, $gapura));

    config(['jubelio.webhook_secret' => 'rahasia-webhook']);

    // Production mode makes the SDK verify with the public key from our file instead of DANA's built-in sandbox key.
    app()->instance(GapuraClient::class, new GapuraClient([
        'env' => 'production',
        'dana_public_key_path' => gapuraKeys('dana')['public'],
    ] + config('gapura')));

    Queue::fake();
});

/**
 * @param  array<string, string>  $headers
 */
function postRawWebhook(string $uri, string $body, array $headers): TestResponse
{
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call('POST', $uri, [], [], [], $server, $body);
}

function postJubelio(string $body, ?string $signature = null): TestResponse
{
    return postRawWebhook('/webhooks/__test/jubelio', $body, [
        'sign' => $signature ?? jubelioSignature($body),
    ]);
}

// --- Jubelio -------------------------------------------------------------------

test('a Jubelio webhook with a valid signature is stored, queued, and answered with status ok', function () {
    $body = fixtureContent('jubelio/webhooks/update-product.json');

    postJubelio($body)->assertOk()->assertExactJson(['status' => 'ok']);

    $event = WebhookEvent::sole();
    expect($event)
        ->provider->toBe(WebhookProvider::Jubelio)
        ->event_type->toBe('update-product')
        ->idempotency_key->toBe('jubelio:update-product:'.hash('sha256', $body))
        ->signature_valid->toBeTrue()
        ->status->toBe(WebhookEventStatus::Received)
        ->summary->toBe('update-product item_group_id=1931')
        ->and($event->payload['item_group_name'])->toBe('Sepeda Listrik Uwinfly C1 Pro');

    Queue::assertPushed(ProcessJubelioWebhookJob::class, fn ($job) => $job->webhookEventId === $event->id);
});

test('the same Jubelio delivery twice is stored and queued once, and both get 200', function () {
    $body = fixtureContent('jubelio/webhooks/update-product.json');

    postJubelio($body)->assertOk();
    postJubelio($body)->assertOk()->assertExactJson(['status' => 'ok']);

    expect(WebhookEvent::count())->toBe(1);
    Queue::assertPushed(ProcessJubelioWebhookJob::class, 1);
});

test('the signature is checked against the raw body, so a re-encoded body is rejected', function () {
    $body = fixtureContent('jubelio/webhooks/update-product.json');
    $reencoded = json_encode(json_decode($body, true));

    assertApiError(postJubelio($reencoded, jubelioSignature($body)), 401, 'INVALID_SIGNATURE');
});

test('the signature matches the vector from the official Jubelio Node.js example', function () {
    // Vector computed independently with Node's crypto.createHmac for the body and
    // secret shown in docs/api/dist.yaml ("Webhook Signature").
    config(['jubelio.webhook_secret' => 'abcd1234']);
    $body = '{"action":"update-product","item_group_id":5495}';

    postJubelio($body, 'a90c27525492da2b91ecfc18311904c85c8950cbe5648138a54e1a09d04613ff')->assertOk();
});

test('a plain SHA256 of body + secret (the earlier contract assumption) is rejected', function () {
    $body = fixtureContent('jubelio/webhooks/update-product.json');

    assertApiError(postJubelio($body, hash('sha256', trim($body).'rahasia-webhook')), 401, 'INVALID_SIGNATURE');
});

test('surrounding whitespace in the body is ignored, as in the Jubelio example (trim)', function () {
    $body = "\n".trim(fixtureContent('jubelio/webhooks/update-product.json'))."\n\n";

    postJubelio($body, jubelioSignature(trim($body)))->assertOk();
});

test('a wrong signature answers 401 INVALID_SIGNATURE and is recorded with signature_valid = false', function () {
    assertApiError(postJubelio(fixtureContent('jubelio/webhooks/update-product.json'), 'salah'), 401, 'INVALID_SIGNATURE')
        ->assertJsonPath('message', 'Signature webhook tidak valid.');

    expect(WebhookEvent::sole())
        ->signature_valid->toBeFalse()
        ->status->toBe(WebhookEventStatus::Ignored)
        ->idempotency_key->toStartWith('invalid:')
        ->and(WebhookEvent::sole()->payload['item_group_id'])->toBe(1931);
    Queue::assertNothingPushed();
});

test('a rejected delivery never blocks the genuine one that follows', function () {
    $body = fixtureContent('jubelio/webhooks/update-product.json');

    postJubelio($body, 'salah')->assertUnauthorized();
    postJubelio($body)->assertOk();

    expect(WebhookEvent::where('signature_valid', true)->count())->toBe(1);
    Queue::assertPushed(ProcessJubelioWebhookJob::class, 1);
});

test('the signature is read from the sign header by default; a rejected delivery lists the headers it came with', function () {
    $body = fixtureContent('jubelio/webhooks/update-product.json');

    $response = postRawWebhook('/webhooks/__test/jubelio', $body, ['X-Signature' => jubelioSignature($body)]);

    assertApiError($response, 401, 'INVALID_SIGNATURE');
    expect(config('jubelio.webhook_signature_header'))->toBe('sign')
        ->and(WebhookEvent::sole()->error)->toContain('x-signature')->toContain('content-type');
});

test('the signature header name can be changed in config', function () {
    config(['jubelio.webhook_signature_header' => 'X-Jubelio-Sign']);
    $body = fixtureContent('jubelio/webhooks/update-product.json');

    postRawWebhook('/webhooks/__test/jubelio', $body, ['X-Jubelio-Sign' => jubelioSignature($body)])->assertOk();
});

test('without a webhook secret nothing is trusted', function () {
    config(['jubelio.webhook_secret' => null]);
    $body = fixtureContent('jubelio/webhooks/update-product.json');

    assertApiError(postJubelio($body, jubelioSignature($body, '')), 401, 'INVALID_SIGNATURE');
});

test('a Jubelio payload missing a required field answers 422 VALIDATION_ERROR', function () {
    assertApiError(postJubelio('{"action":"update-product"}'), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('item_group_id');

    expect(WebhookEvent::count())->toBe(0);
});

// --- Gapura --------------------------------------------------------------------

function postGapura(string $body, ?array $headers = null): TestResponse
{
    return postRawWebhook('/webhooks/__test/gapura', $body, $headers ?? gapuraNotifyHeaders($body));
}

test('a signed Gapura notification is stored as gapura:{order}:{status} and answered with 2005600', function () {
    postGapura(fixtureContent('gapura/finish-notify-paid.json'))
        ->assertOk()
        ->assertExactJson(['responseCode' => '2005600', 'responseMessage' => 'Successful']);

    expect(WebhookEvent::sole())
        ->provider->toBe(WebhookProvider::Gapura)
        ->event_type->toBe('notify')
        ->idempotency_key->toBe('gapura:WEB-000123:00')
        ->summary->toBe('WEB-000123 00')
        ->signature_valid->toBeTrue();
    Queue::assertPushed(ProcessGapuraWebhookJob::class, 1);
});

test('the same notification delivered twice is processed once', function () {
    $body = fixtureContent('gapura/finish-notify-paid.json');

    postGapura($body)->assertOk();
    postGapura($body)->assertOk()->assertJsonPath('responseCode', '2005600');

    expect(WebhookEvent::count())->toBe(1);
    Queue::assertPushed(ProcessGapuraWebhookJob::class, 1);
});

test('paid and cancelled for different orders are separate events', function () {
    postGapura(fixtureContent('gapura/finish-notify-paid.json'))->assertOk();
    postGapura(fixtureContent('gapura/finish-notify-cancelled.json'))->assertOk();

    expect(WebhookEvent::pluck('idempotency_key')->all())->toBe(['gapura:WEB-000123:00', 'gapura:WEB-000124:05']);
    Queue::assertPushed(ProcessGapuraWebhookJob::class, 2);
});

test('a bad or missing signature answers 401 INVALID_SIGNATURE and is recorded', function (string $case) {
    $body = fixtureContent('gapura/finish-notify-paid.json');
    $headers = match ($case) {
        'wrong key' => gapuraNotifyHeaders($body, gapuraKeys('someone-else')['private']),
        'no headers' => [],
        'tampered body' => gapuraNotifyHeaders(str_replace('6935000.00', '1.00', $body)),
    };

    assertApiError(postGapura($body, $headers), 401, 'INVALID_SIGNATURE')
        ->assertJsonPath('message', 'Signature webhook tidak valid.');

    expect(WebhookEvent::sole())->signature_valid->toBeFalse()->provider->toBe(WebhookProvider::Gapura);
    Queue::assertNothingPushed();
})->with(['wrong key', 'no headers', 'tampered body']);

test('a signed Gapura notification missing a required field answers 422', function (string $field) {
    $payload = fixture('gapura/finish-notify-paid.json');
    unset($payload[$field]);
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

    assertApiError(postGapura($body), 422, 'VALIDATION_ERROR')->assertJsonValidationErrors($field);
})->with(['originalPartnerReferenceNo', 'originalReferenceNo', 'latestTransactionStatus']);

test('webhook intake runs without session cookies', function () {
    $response = postGapura(fixtureContent('gapura/finish-notify-paid.json'));

    expect($response->headers->getCookies())->toBeEmpty();
});
