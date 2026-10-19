<?php

use App\Services\Xendit\XenditClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
| INT-HDL for Xendit: HTTP Basic auth with the secret key, 4xx not retried,
| error_code/message kept. Endpoint methods (INT-XD-01 – 03) come in Fase 4–5,
| so the protected transport is reached through a small subclass.
*/

beforeEach(function () {
    config(['xendit.base_url' => 'https://api.xendit.co', 'xendit.secret_key' => 'xnd_development_rahasia']);

    $this->xendit = new class(config('xendit')) extends XenditClient
    {
        public function createInvoice(array $body): array
        {
            return $this->post('/v2/invoices', $body)->json();
        }
    };
});

test('authenticates with the secret key as Basic username', function () {
    Http::fake(['*' => Http::response(['id' => 'inv_1'])]);

    $this->xendit->createInvoice(['external_id' => 'WEB-000001']);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.xendit.co/v2/invoices'
        && $request->header('Authorization') === ['Basic '.base64_encode('xnd_development_rahasia:')]);
});

test('a 4xx is not retried and keeps the Xendit error_code and message', function () {
    Http::fake(['*' => Http::response(fixture('xendit/error-api-validation.json'), 400)]);

    $e = catchIntegration(fn () => $this->xendit->createInvoice([]));

    expect($e->retryable)->toBeFalse()
        ->and($e->providerCode)->toBe('API_VALIDATION_ERROR')
        ->and($e->detail())->toBe('xendit POST /v2/invoices → HTTP 400 [API_VALIDATION_ERROR] amount must be a number');
});

test('a 5xx is retryable', function () {
    Http::fake(['*' => Http::response([], 503)]);

    expect(catchIntegration(fn () => $this->xendit->createInvoice([]))->retryable)->toBeTrue();
});

test('live mode is detected from the key prefix', function () {
    expect($this->xendit->isLiveMode())->toBeFalse()
        ->and((new XenditClient(['secret_key' => 'xnd_production_x'] + config('xendit')))->isLiveMode())->toBeTrue();
});
