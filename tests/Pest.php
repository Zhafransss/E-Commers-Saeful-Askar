<?php

use App\Exceptions\IntegrationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against the MySQL database configured in phpunit.xml
| (e_commers_saeful_askar_testing), refreshed per test.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit tests get the application (config, storage paths) but no database refresh.
pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Asserts the API Contract error shape (docs/api/README.md "Format response gagal").
 */
function assertApiError(TestResponse $response, int $status, string $code): TestResponse
{
    return $response
        ->assertStatus($status)
        ->assertJsonPath('code', $code)
        ->assertJsonStructure(['message', 'code']);
}

/**
 * Raw content of a fixture in tests/Fixtures, e.g. fixtureContent('jubelio/login.json').
 * Webhook tests need the exact bytes, since signatures are computed over the raw body.
 */
function fixtureContent(string $path): string
{
    return file_get_contents(__DIR__.'/Fixtures/'.$path);
}

/**
 * Decoded JSON fixture, e.g. Http::fake(['*' => Http::response(fixture('jubelio/stores.json'))]).
 *
 * @return array<string, mixed>
 */
function fixture(string $path): array
{
    return json_decode(fixtureContent($path), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Jubelio webhook signature for the `sign` header, built the way Jubelio's
 * official example does (docs/api/dist.yaml, "Webhook Signature").
 */
function jubelioSignature(string $body, string $secret = 'rahasia-webhook'): string
{
    return hash_hmac('sha256', trim($body).$secret, $secret);
}

/**
 * Answers Jubelio catalog calls from tests/Fixtures/jubelio/catalog, routed by
 * path. $overrides replaces or adds paths, e.g. ['/inventory/items/group/18' =>
 * Http::response([], 404)]. Unknown paths answer 404, like Jubelio does.
 * Stock search (`/inventory/?q=`) also returns similar SKUs, as the real one does.
 *
 * @param  array<string, mixed>  $overrides  path => response or fn (Request) => response
 */
function fakeJubelioCatalog(array $overrides = []): void
{
    config([
        'jubelio.base_url' => 'https://api2.jubelio.com',
        'jubelio.email' => 'owner@saefulaskar.id',
        'jubelio.password' => 'rahasia-jubelio',
        'jubelio.store_id' => '-102',
        'jubelio.location_id' => -1,
    ]);

    $routes = $overrides + [
        '/login' => Http::response(fixture('jubelio/login.json')),
        '/inventory/categories/item-categories/' => Http::response(fixture('jubelio/catalog/categories.json')),
        '/inventory/items/' => Http::response(fixture('jubelio/catalog/items.json')),
        '/inventory/item-bundles/' => Http::response(fixture('jubelio/catalog/item-bundles.json')),
        '/inventory/items/group/18' => Http::response(fixture('jubelio/catalog/item-group-18.json')),
        '/inventory/items/group/19' => Http::response(fixture('jubelio/catalog/item-group-19.json')),
        '/inventory/items/group/25' => Http::response(fixture('jubelio/catalog/item-group-25.json')),
        '/variations/' => Http::response(fixture('jubelio/catalog/variations.json')),
        '/inventory/' => function (Request $request) {
            $stock = fixture('jubelio/catalog/inventory.json');
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $search = $query['q'] ?? null;

            if ($search !== null) {
                $stock['data'] = array_values(array_filter($stock['data'], fn (array $row) => str_contains($row['item_code'], $search)));
                $stock['totalCount'] = count($stock['data']);
            }

            return Http::response($stock);
        },
    ];

    // Http::fake() stubs accumulate and the first match wins, so later calls in
    // the same test only swap the routes the single registered stub reads.
    $registered = app()->bound('tests.jubelio-routes');
    app()->instance('tests.jubelio-routes', $routes);

    if (! $registered) {
        Http::fake(function (Request $request) {
            $response = app('tests.jubelio-routes')[parse_url($request->url(), PHP_URL_PATH)] ?? Http::response(['message' => 'Not Found'], 404);

            return $response instanceof Closure ? $response($request) : $response;
        });
    }
}

/**
 * Paths of the Jubelio calls recorded by Http::fake(), e.g. ['/login', '/variations/'].
 *
 * @return list<string>
 */
function jubelioCalls(): array
{
    return Http::recorded()->map(fn (array $pair) => parse_url($pair[0]->url(), PHP_URL_PATH))->values()->all();
}

/**
 * Runs a call that must fail with an IntegrationException and returns it for inspection.
 */
function catchIntegration(Closure $call): IntegrationException
{
    try {
        $call();
    } catch (IntegrationException $e) {
        return $e;
    }

    throw new RuntimeException('Expected an IntegrationException.');
}

/**
 * Records written to the `integrations` log channel. The test first points the
 * channel at Monolog's TestHandler:
 * config(['logging.channels.integrations' => ['driver' => 'monolog', 'handler' => TestHandler::class]]).
 */
function integrationLog(): TestHandler
{
    return Log::channel('integrations')->getLogger()->getHandlers()[0];
}

/**
 * Frontend endpoints from the contract index, as [code, method, uri], skipping v1.1.
 * Path parameters are filled with sample values.
 *
 * @return list<array{0: string, 1: string, 2: string}>
 */
function contractEndpoints(): array
{
    $readme = file_get_contents(dirname(__DIR__).'/docs/api/README.md');
    $frontend = explode('### Webhook masuk', explode('### Untuk frontend', $readme)[1])[0];
    $webhooks = explode('### Panggilan keluar', explode('### Webhook masuk', $readme)[1])[0];

    $samples = ['{slug}' => 'contoh-slug', '{id}' => '1', '{order_number}' => 'WEB-000001'];
    $endpoints = [];

    preg_match_all('/^\| ([A-Z]+-\d+)( \(v1\.1\))? \| (GET|POST|PUT|PATCH|DELETE) \| `([^`]+)`([^|]*)\|/m', $frontend, $rows, PREG_SET_ORDER);
    foreach ($rows as [, $code, $deferred, $method, $path, $note]) {
        if ($deferred) {
            continue;
        }
        // ADM-05 is sent as POST with _method=PUT (method spoofing).
        $method = str_contains($note, '_method=PUT') ? 'PUT' : $method;
        $uri = str_starts_with($path, '/sanctum') ? $path : '/api/v1'.$path;
        $endpoints[] = [$code, $method, strtr($uri, $samples)];
    }

    preg_match_all('/^\| (WH-\d+) \| (POST) \| `([^`]+)` \|/m', $webhooks, $rows, PREG_SET_ORDER);
    foreach ($rows as [, $code, $method, $path]) {
        $endpoints[] = [$code, $method, $path];
    }

    return $endpoints;
}
