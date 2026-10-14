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

test('the contract index lists 49 frontend endpoints and 5 webhooks for v1', function () {
    $codes = array_column(contractEndpoints(), 0);

    expect(array_filter($codes, fn ($code) => ! str_starts_with($code, 'WH-')))->toHaveCount(49)
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
