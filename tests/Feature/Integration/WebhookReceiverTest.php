<?php

use App\Enums\WebhookEventStatus;
use App\Enums\WebhookProvider;
use App\Jobs\ProcessJubelioWebhookJob;
use App\Jobs\ProcessXenditWebhookJob;
use App\Models\WebhookEvent;
use App\Services\Webhooks\WebhookReceiver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
| The intake pattern shared by WH-01 – WH-05 (docs/api/webhooks.md): verify,
| record (also when rejected), deduplicate by idempotency_key, answer 200, queue.
| The real WH routes stay 501 until their phase, so test routes are used here.
*/

beforeEach(function () {
    Route::post('/webhooks/__test/jubelio', fn (Request $request, WebhookReceiver $receiver) => $receiver
        ->jubelio($request, 'update-product', ['item_group_id' => ['required', 'integer']]));
    Route::post('/webhooks/__test/xendit', fn (Request $request, WebhookReceiver $receiver) => $receiver->xendit($request));

    config([
        'jubelio.webhook_secret' => 'rahasia-webhook',
        'jubelio.webhook_signature_header' => 'X-Test-Signature',
        'xendit.callback_token' => 'token-callback',
    ]);

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
        'X-Test-Signature' => $signature ?? hash('sha256', $body.'rahasia-webhook'),
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

    assertApiError(postJubelio($reencoded, hash('sha256', $body.'rahasia-webhook')), 401, 'INVALID_SIGNATURE');
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

test('while the signature header name is not configured, deliveries are rejected and list the headers they came with', function () {
    config(['jubelio.webhook_signature_header' => null]);
    $body = fixtureContent('jubelio/webhooks/update-product.json');

    $response = postRawWebhook('/webhooks/__test/jubelio', $body, ['Sign' => hash('sha256', $body.'rahasia-webhook')]);

    assertApiError($response, 401, 'INVALID_SIGNATURE');
    expect(WebhookEvent::sole()->error)->toContain('sign')->toContain('content-type');
});

test('without a webhook secret nothing is trusted', function () {
    config(['jubelio.webhook_secret' => null]);
    $body = fixtureContent('jubelio/webhooks/update-product.json');

    assertApiError(postJubelio($body, hash('sha256', $body)), 401, 'INVALID_SIGNATURE');
});

test('a Jubelio payload missing a required field answers 422 VALIDATION_ERROR', function () {
    assertApiError(postJubelio('{"action":"update-product"}'), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('item_group_id');

    expect(WebhookEvent::count())->toBe(0);
});

// --- Xendit --------------------------------------------------------------------

test('a Xendit callback with the right token is stored as xendit:{id}:{status} and answered with received', function () {
    postRawWebhook('/webhooks/__test/xendit', fixtureContent('xendit/invoice-callback-paid.json'), ['x-callback-token' => 'token-callback'])
        ->assertOk()
        ->assertExactJson(['received' => true]);

    expect(WebhookEvent::sole())
        ->provider->toBe(WebhookProvider::Xendit)
        ->event_type->toBe('invoice')
        ->idempotency_key->toBe('xendit:6527a1b2c3d4e5f6a7b8c9d0:PAID')
        ->summary->toBe('WEB-000123 PAID');
    Queue::assertPushed(ProcessXenditWebhookJob::class, 1);
});

test('PAID and SETTLED for the same invoice are two separate events', function () {
    $paid = fixture('xendit/invoice-callback-paid.json');

    foreach (['PAID', 'SETTLED', 'PAID'] as $status) {
        postRawWebhook('/webhooks/__test/xendit', json_encode(['status' => $status] + $paid), ['x-callback-token' => 'token-callback'])->assertOk();
    }

    expect(WebhookEvent::pluck('idempotency_key')->all())->toBe([
        'xendit:6527a1b2c3d4e5f6a7b8c9d0:PAID',
        'xendit:6527a1b2c3d4e5f6a7b8c9d0:SETTLED',
    ]);
    Queue::assertPushed(ProcessXenditWebhookJob::class, 2);
});

test('a wrong or missing callback token answers 401 INVALID_CALLBACK_TOKEN and is recorded', function (?string $token) {
    $headers = $token === null ? [] : ['x-callback-token' => $token];

    assertApiError(postRawWebhook('/webhooks/__test/xendit', fixtureContent('xendit/invoice-callback-paid.json'), $headers), 401, 'INVALID_CALLBACK_TOKEN')
        ->assertJsonPath('message', 'Callback token tidak valid.');

    expect(WebhookEvent::sole())->signature_valid->toBeFalse()->provider->toBe(WebhookProvider::Xendit);
    Queue::assertNothingPushed();
})->with(['wrong token' => 'salah', 'no header' => null]);

test('without a configured callback token every Xendit callback is rejected', function () {
    config(['xendit.callback_token' => null]);

    assertApiError(postRawWebhook('/webhooks/__test/xendit', fixtureContent('xendit/invoice-callback-paid.json'), ['x-callback-token' => '']), 401, 'INVALID_CALLBACK_TOKEN');
});

test('a Xendit callback without id, external_id, or status answers 422', function (string $field) {
    $payload = fixture('xendit/invoice-callback-paid.json');
    unset($payload[$field]);

    assertApiError(postRawWebhook('/webhooks/__test/xendit', json_encode($payload), ['x-callback-token' => 'token-callback']), 422, 'VALIDATION_ERROR')
        ->assertJsonValidationErrors($field);
})->with(['id', 'external_id', 'status']);

test('webhook intake runs without session cookies', function () {
    $response = postRawWebhook('/webhooks/__test/xendit', fixtureContent('xendit/invoice-callback-paid.json'), ['x-callback-token' => 'token-callback']);

    expect($response->headers->getCookies())->toBeEmpty();
});
