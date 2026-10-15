<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Framework errors must come out in the contract shape { message, code, errors? }
| (docs/api/README.md "Kode error umum").
*/

test('guests get 401 UNAUTHENTICATED on customer endpoints', function () {
    assertApiError($this->getJson('/api/v1/auth/me'), 401, 'UNAUTHENTICATED')
        ->assertJsonPath('message', 'Silakan masuk terlebih dahulu.');
});

test('customers get 403 FORBIDDEN on admin endpoints', function () {
    $this->actingAs(User::factory()->create());

    assertApiError($this->getJson('/api/v1/admin/orders'), 403, 'FORBIDDEN');
});

test('admins reach admin endpoints', function () {
    $this->actingAs(User::factory()->admin()->create());

    assertApiError($this->getJson('/api/v1/admin/orders'), 501, 'NOT_IMPLEMENTED')
        ->assertJsonPath('endpoint', 'ADM-09');
});

test('unknown API paths get 404 NOT_FOUND', function () {
    assertApiError($this->getJson('/api/v1/tidak-ada'), 404, 'NOT_FOUND');
});

test('wrong HTTP method gets 405 METHOD_NOT_ALLOWED', function () {
    assertApiError($this->deleteJson('/api/v1/categories'), 405, 'METHOD_NOT_ALLOWED');
});

test('malformed JSON body gets 400 BAD_REQUEST', function () {
    $response = $this->call('POST', '/api/v1/auth/login', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], '{"email": ');

    assertApiError($response, 400, 'BAD_REQUEST');
});

test('validation errors use VALIDATION_ERROR with Indonesian field messages', function () {
    Route::post('/api/v1/__test/validation', fn (Request $request) => $request->validate([
        'email' => ['required', 'email'],
    ]))->middleware('api');

    assertApiError($this->postJson('/api/v1/__test/validation', ['email' => 'bukan-email']), 422, 'VALIDATION_ERROR')
        ->assertJsonPath('message', 'Data yang dikirim tidak valid.')
        ->assertJsonPath('errors.email.0', 'Email harus berupa alamat email yang valid.');
});

test('rate limits answer 429 TOO_MANY_REQUESTS with Retry-After', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => 'budi@example.com']);
    }

    assertApiError($this->postJson('/api/v1/auth/login', ['email' => 'budi@example.com']), 429, 'TOO_MANY_REQUESTS')
        ->assertHeader('Retry-After');
});

test('auth rate limits are counted per endpoint, so a locked login still allows forgot-password', function () {
    foreach (range(1, 6) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => 'budi@example.com']);
    }

    assertApiError($this->postJson('/api/v1/auth/forgot-password', ['email' => 'budi@example.com']), 501, 'NOT_IMPLEMENTED');
});

test('unexpected exceptions get 500 SERVER_ERROR without leaking details in production', function () {
    config(['app.debug' => false]);
    Route::get('/api/v1/__test/boom', fn () => throw new RuntimeException('secret detail'))->middleware('api');

    assertApiError($this->getJson('/api/v1/__test/boom'), 500, 'SERVER_ERROR')
        ->assertJsonMissingPath('debug');
});

test('the CSRF cookie endpoint answers 204 and sets XSRF-TOKEN', function () {
    $this->get('/sanctum/csrf-cookie')
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN');
});

test('webhooks run without the web group, so no session or CSRF check', function (string $name) {
    $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();

    expect($middleware)->not->toContain('web')
        ->and($middleware)->not->toContain('api');
})->with(['webhooks.xendit.invoice', 'webhooks.jubelio.product', 'webhooks.jubelio.price', 'webhooks.jubelio.stock', 'webhooks.jubelio.salesorder']);

test('webhooks answer JSON without setting cookies', function () {
    $response = $this->postJson('/webhooks/xendit/invoice', []);

    assertApiError($response, 501, 'NOT_IMPLEMENTED');
    expect($response->headers->getCookies())->toBeEmpty();
});
