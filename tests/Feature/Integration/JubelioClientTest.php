<?php

use App\Exceptions\IntegrationException;
use App\Exceptions\JubelioApiException;
use App\Services\Jubelio\JubelioClient;
use App\Services\Jubelio\JubelioTokenManager;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Sleep;
use Monolog\Handler\TestHandler;

/*
| INT-HDL and INT-JB-01 (docs/api/integrations.md): token caching, re-login on
| 401, error mapping, rate limit, and logging without bodies or credentials.
*/

beforeEach(function () {
    config([
        'jubelio.base_url' => 'https://api2.jubelio.com',
        'jubelio.email' => 'owner@saefulaskar.id',
        'jubelio.password' => 'rahasia-jubelio',
        'logging.channels.integrations' => ['driver' => 'monolog', 'handler' => TestHandler::class],
    ]);
});

test('logs in once, caches the token, and sends it as the authorization header', function () {
    Http::fake([
        '*/login' => Http::response(fixture('jubelio/login.json')),
        '*/locations/*' => Http::response(fixture('jubelio/locations.json')),
    ]);
    $token = fixture('jubelio/login.json')['token'];

    $client = app(JubelioClient::class);
    expect($client->locations())->toHaveCount(2);
    $client->locations();

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api2.jubelio.com/login'
        && $request['email'] === 'owner@saefulaskar.id');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/locations/')
        && $request->header('authorization') === [$token]);
    expect(Cache::get('jubelio:token'))->toBe($token);
});

test('a process that waited for the login lock reuses the token another process just stored', function () {
    // The first read misses (as if checked just before the other process saved
    // its token); the read made after acquiring the lock finds it.
    $store = new class extends ArrayStore
    {
        private bool $missedOnce = false;

        public function get($key)
        {
            if ($key === 'jubelio:token' && ! $this->missedOnce) {
                $this->missedOnce = true;

                return null;
            }

            return parent::get($key);
        }
    };
    $cache = new Repository($store);
    $cache->put('jubelio:token', 'token-proses-lain', 60);

    $token = (new JubelioTokenManager($cache, 41400, 300))
        ->token(fn () => throw new RuntimeException('Tidak boleh login lagi.'));

    expect($token)->toBe('token-proses-lain');
});

test('the token is cached for 11 hours 30 minutes, then fetched again', function () {
    Http::fake([
        '*/login' => Http::response(fixture('jubelio/login.json')),
        '*/locations/*' => Http::response(fixture('jubelio/locations.json')),
    ]);
    $client = app(JubelioClient::class);

    $client->locations();
    $this->travel(11 * 60 + 29)->minutes();
    $client->locations();
    $this->travel(2)->minutes();
    $client->locations();

    Http::assertSentCount(5); // 2 logins + 3 calls
});

test('a 401 drops the token, logs in again once, and repeats the request once', function () {
    Http::fake([
        '*/login' => Http::sequence()->push(['token' => 'token-lama'])->push(['token' => 'token-baru']),
        '*/locations/*' => Http::sequence()->push([], 401)->push(fixture('jubelio/locations.json')),
    ]);

    expect(app(JubelioClient::class)->locations())->toHaveCount(2);

    $calls = Http::recorded(fn (Request $request) => str_contains($request->url(), '/locations/'))->values();
    expect($calls)->toHaveCount(2)
        ->and($calls[0][0]->header('authorization'))->toBe(['token-lama'])
        ->and($calls[1][0]->header('authorization'))->toBe(['token-baru'])
        ->and(Cache::get('jubelio:token'))->toBe('token-baru');
});

test('a second 401 in a row is not retried again', function () {
    Http::fake([
        '*/login' => Http::response(fixture('jubelio/login.json')),
        '*/locations/*' => Http::response([], 401),
    ]);

    $e = catchIntegration(fn () => app(JubelioClient::class)->locations());

    expect($e)->toBeInstanceOf(JubelioApiException::class)
        ->and($e->httpStatus)->toBe(401);
    Http::assertSentCount(4); // login, call, login, call
});

test('rejected credentials are not retried, are logged as critical, and pause further logins', function () {
    Http::fake(['*/login' => Http::response(fixture('jubelio/login-invalid-credentials.json'), 500)]);
    $client = app(JubelioClient::class);

    $e = catchIntegration(fn () => $client->locations());
    expect($e->retryable)->toBeFalse()
        ->and($e->isInvalidCredentials())->toBeTrue()
        ->and(integrationLog()->hasCriticalThatContains('JUBELIO_EMAIL/JUBELIO_PASSWORD'))->toBeTrue();

    $second = catchIntegration(fn () => $client->locations());
    expect($second->retryable)->toBeFalse();
    Http::assertSentCount(1);

    $this->travel(6)->minutes();
    catchIntegration(fn () => $client->locations());
    Http::assertSentCount(2);
});

test('missing credentials fail without calling Jubelio, and do not pause logins once filled in', function () {
    config(['jubelio.email' => null]);
    Http::fake([
        '*/login' => Http::response(fixture('jubelio/login.json')),
        '*/locations/*' => Http::response(fixture('jubelio/locations.json')),
    ]);

    $e = catchIntegration(fn () => app(JubelioClient::class)->locations());

    expect($e->retryable)->toBeFalse()
        ->and($e->detail())->toBe('jubelio POST /login: JUBELIO_EMAIL dan JUBELIO_PASSWORD belum diisi di .env.');
    Http::assertNothingSent();

    config(['jubelio.email' => 'owner@saefulaskar.id']);
    $this->app->forgetInstance(JubelioClient::class);

    expect(app(JubelioClient::class)->locations())->toHaveCount(2);
});

test('a Jubelio SQL error keeps its code and message verbatim and stays retryable', function () {
    Cache::put('jubelio:token', 'token-ada', 60);
    Http::fake(['*/sales/orders/' => Http::response(fixture('jubelio/error-sql.json'), 500)]);

    $e = catchIntegration(fn () => app(JubelioClient::class)->post('/sales/orders/', ['ref_no' => 'WEB-000001']));

    expect($e)->toBeInstanceOf(JubelioApiException::class)
        ->and($e->retryable)->toBeTrue()
        ->and($e->providerCode)->toBe('23505')
        ->and($e->detail())->toBe('jubelio POST /sales/orders/ → HTTP 500 [23505] duplicate key value violates unique constraint "salesorder_ref_no_key"');
});

test('a 429 is retryable after 60 seconds', function () {
    Cache::put('jubelio:token', 'token-ada', 60);
    Http::fake(['*' => Http::response([], 429)]);

    $e = catchIntegration(fn () => app(JubelioClient::class)->locations());

    expect($e->retryable)->toBeTrue()->and($e->retryAfter)->toBe(60);
});

test('a timeout becomes a retryable IntegrationException without the query string in the message', function () {
    Cache::put('jubelio:token', 'token-ada', 60);
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out for https://api2.jubelio.com/contacts/customers/?q=budi@example.com'));

    $e = catchIntegration(fn () => app(JubelioClient::class)->get('/contacts/customers/', ['q' => 'budi@example.com']));

    expect($e->retryable)->toBeTrue()
        ->and($e->httpStatus)->toBeNull()
        ->and($e->detail())->not->toContain('budi@example.com');
});

test('outside a customer request a 5xx is not retried; inside one it is retried once', function () {
    Cache::put('jubelio:token', 'token-ada', 60);
    Sleep::fake();
    Http::fake(['*' => Http::response([], 503)]);

    catchIntegration(fn () => app(JubelioClient::class)->locations());
    Http::assertSentCount(1);

    catchIntegration(fn () => app(JubelioClient::class)->withinCustomerRequest()->locations());
    Http::assertSentCount(3);
});

test('calls wait for the next minute instead of exceeding the Jubelio rate limit', function () {
    config(['jubelio.rate_limit_per_minute' => 2]);
    Cache::put('jubelio:token', 'token-ada', 3600);
    Sleep::fake(syncWithCarbon: true);
    Http::fake(['*' => Http::response(fixture('jubelio/locations.json'))]);

    $client = app(JubelioClient::class);
    $client->locations();
    $client->locations();
    Sleep::assertNeverSlept();

    $client->locations();
    Sleep::assertSleptTimes(1);
    Http::assertSentCount(3);
});

test('each call is logged with method, path, status, and duration, but no body, query, or credentials', function () {
    Http::fake([
        '*/login' => Http::response(fixture('jubelio/login.json')),
        '*/contacts/*' => Http::response(['data' => []]),
    ]);

    app(JubelioClient::class)->get('/contacts/customers/', ['q' => 'budi@example.com']);

    $records = integrationLog()->getRecords();
    expect($records)->toHaveCount(2)
        ->and($records[1]->context)->toMatchArray(['service' => 'jubelio', 'method' => 'GET', 'path' => '/contacts/customers/', 'status' => 200])
        ->and($records[1]->context)->toHaveKey('duration_ms');

    $logged = json_encode(array_map(fn ($record) => [$record->message, $record->context], $records));
    expect($logged)->not->toContain('budi@example.com')
        ->not->toContain('rahasia-jubelio')
        ->not->toContain('owner@saefulaskar.id')
        ->not->toContain(fixture('jubelio/login.json')['token']);
});

test('an IntegrationException reaching the API answers 502 INTEGRATION_ERROR without technical detail', function () {
    Route::get('/api/v1/__test/integration', fn () => throw (new IntegrationException('jubelio', 'GET', '/region/provinces', 500, providerMessage: 'SQL secret'))
        ->withMessage('Data wilayah sedang tidak bisa dimuat. Coba lagi beberapa saat.'))->middleware('api');

    assertApiError($this->getJson('/api/v1/__test/integration'), 502, 'INTEGRATION_ERROR')
        ->assertJsonPath('message', 'Data wilayah sedang tidak bisa dimuat. Coba lagi beberapa saat.')
        ->assertDontSee('SQL secret');
});
