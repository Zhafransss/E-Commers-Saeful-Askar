<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Keeps routes and the API Contract in lockstep: every v1 endpoint listed in
| docs/api/README.md must be routed, and nothing deferred to v1.1 may be.
*/

test('every v1 contract endpoint has a route', function (string $code, string $method, string $uri) {
    $route = Route::getRoutes()->match(Request::create($uri, $method));

    expect($route->uri())->not->toBe('{fallbackPlaceholder}');
})->with(contractEndpoints());

test('every API and webhook route is documented in the contract', function () {
    // Normalise "/api/v1/orders/WEB-000001" and "api/v1/orders/{orderNumber}" to the same shape.
    $shape = fn (string $method, string $uri) => $method.' /'.ltrim(preg_replace('#\{[^}]+\}|contoh-slug|WEB-000001|(?<=/)1(?=/|$)#', '{}', $uri), '/');

    $documented = collect(contractEndpoints())->map(fn ($endpoint) => $shape($endpoint[1], $endpoint[2]));

    $routed = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/') || str_starts_with($route->uri(), 'webhooks/') || str_starts_with($route->uri(), 'v1.0/'))
        ->flatMap(fn ($route) => collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->map(fn ($m) => $shape($m, $route->uri())));

    expect($routed->diff($documented)->values()->all())->toBe([]);
});

test('the contract index lists 40 frontend endpoints and 5 webhooks for v1', function () {
    $codes = array_column(contractEndpoints(), 0);

    expect(array_filter($codes, fn ($code) => ! str_starts_with($code, 'WH-')))->toHaveCount(40)
        ->and(array_filter($codes, fn ($code) => str_starts_with($code, 'WH-')))->toHaveCount(5);
});

test('endpoints deferred to v1.1 are not routed', function (string $uri) {
    $this->actingAs(User::factory()->admin()->create());

    assertApiError($this->getJson($uri), 404, 'NOT_FOUND');
})->with([
    'ADM-01' => '/api/v1/admin/dashboard',
    'ADM-15' => '/api/v1/admin/webhook-events',
    'ADM-17' => '/api/v1/admin/settings/integrations',
]);
