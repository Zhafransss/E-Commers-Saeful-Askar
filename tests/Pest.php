<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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
