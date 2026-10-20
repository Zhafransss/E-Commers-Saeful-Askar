<?php

use App\Models\Address;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| Black-box fuzzing: every input field of every endpoint gets wrong-typed and oversized values.
| The contract promises 4xx with the error shape for bad input, never a 500.
*/

$fuzz = [
    'array' => ['x'],
    'nested array' => [['a' => ['b']]],
    'object' => ['k' => 'v'],
    'empty string' => '',
    'whitespace' => '   ',
    'very long string' => str_repeat('a', 5000),
    'negative number' => -1,
    'huge number' => PHP_INT_MAX,
    'float' => 1.5,
    'boolean' => true,
    'sql quote' => "' OR 1=1 --",
    'null byte' => "a\0b",
    'emoji' => '😀😀😀',
];

dataset('fuzz', $fuzz);

function bbRobustSetup(): array
{
    $user = bbCustomer(['email' => 'budi@example.com']);
    $address = Address::factory()->for($user)->create();
    $variant = ProductVariant::factory()->for(Product::factory()->create(['slug' => 'sepeda']))->create(['price' => 1000000, 'available_qty' => 5]);
    $item = $user->cartItems()->create(['product_variant_id' => $variant->id, 'qty' => 1]);

    return [$user, $address, $variant, $item];
}

/** Endpoint => [method, uri, valid body, needs login]. Query-string endpoints put their params in the body slot. */
function bbBodies(): array
{
    return [
        'register' => ['POST', '/api/v1/auth/register', ['name' => 'Budi', 'email' => 'baru@example.com', 'phone' => '081234567890', 'password' => 'abc12345', 'password_confirmation' => 'abc12345'], false],
        'login' => ['POST', '/api/v1/auth/login', ['email' => 'budi@example.com', 'password' => 'rahasia123', 'remember' => false], false],
        'forgot' => ['POST', '/api/v1/auth/forgot-password', ['email' => 'budi@example.com'], false],
        'reset' => ['POST', '/api/v1/auth/reset-password', ['token' => 'abc', 'email' => 'budi@example.com', 'password' => 'abc12345', 'password_confirmation' => 'abc12345'], false],
        'profile' => ['PUT', '/api/v1/auth/profile', ['name' => 'Budi', 'phone' => '081234567890'], true],
        'password' => ['PUT', '/api/v1/auth/password', ['current_password' => 'rahasia123', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'], true],
        'cart add' => ['POST', '/api/v1/cart/items', ['variant_id' => '{variant}', 'qty' => 1], true],
        'cart update' => ['PATCH', '/api/v1/cart/items/{item}', ['qty' => 2], true],
        'address create' => ['POST', '/api/v1/addresses', ['recipient_name' => 'Budi', 'phone' => '081234567890', 'address_line' => 'Jl. Mawar No. 1', 'province_id' => '32', 'city_id' => '3216', 'district_id' => '321605', 'subdistrict_id' => '3216052001', 'post_code' => '17565', 'is_default' => false], true],
        'address update' => ['PUT', '/api/v1/addresses/{address}', ['recipient_name' => 'Budi', 'phone' => '081234567890', 'address_line' => 'Jl. Mawar No. 1', 'province_id' => '32', 'city_id' => '3216', 'district_id' => '321605', 'subdistrict_id' => '3216052001', 'post_code' => '17565'], true],
        'shipping' => ['POST', '/api/v1/shipping/rates', ['address_id' => '{address}', 'service_types' => ['regular']], true],
        'checkout' => ['POST', '/api/v1/checkout', ['address_id' => '{address}', 'rate_id' => 'rt_x', 'note' => 'n', 'expected_grand_total' => 1000], true],
        'cancel' => ['POST', '/api/v1/orders/WEB-000001/cancel', ['reason' => 'x'], true],
    ];
}

function bbFill(mixed $value, array $ids): mixed
{
    if (is_array($value)) {
        return array_map(fn ($v) => bbFill($v, $ids), $value);
    }

    return is_string($value) && preg_match('/^\{(\w+)\}$/', $value, $m) ? $ids[$m[1]] : $value;
}

test('no body field of any endpoint turns wrong-typed or oversized input into a 5xx', function () {
    $failures = [];
    fakeJubelioRegions();

    foreach (bbBodies() as $name => [$method, $uri, $body, $login]) {
        foreach (array_keys($body) as $field) {
            foreach (bbFuzzValues() as $label => $value) {
                [$user, $address, $variant, $item] = bbRobustSetup();
                $ids = ['variant' => $variant->id, 'address' => $address->id, 'item' => $item->id];
                $payload = bbFill($body, $ids);
                $payload[$field] = $value;

                $request = $login ? fromFrontend()->actingAs($user) : fromFrontend();
                $headers = $name === 'checkout' ? ['Idempotency-Key' => (string) Str::uuid()] : [];
                $response = $request->json($method, bbFill($uri, $ids), $payload, $headers);

                if ($response->getStatusCode() >= 500) {
                    $failures[] = "{$name}.{$field} = {$label} → {$response->getStatusCode()} ".($response->json('debug.message') ?? '');
                }

                // Reset between iterations: every case starts from a clean database.
                DB::rollBack();
                DB::beginTransaction();
                app('auth')->forgetGuards();
                Cache::flush();
            }
        }
    }

    expect($failures)->toBe([]);
});

function bbFuzzValues(): array
{
    return [
        'array' => ['x'], 'nested array' => [['a' => ['b']]], 'object' => ['k' => 'v'], 'empty string' => '', 'whitespace' => '   ',
        'long string' => str_repeat('a', 5000), 'negative' => -1, 'huge number' => PHP_INT_MAX, 'float' => 1.5, 'boolean' => true,
        'sql quote' => "' OR 1=1 --", 'null byte' => "a\0b", 'emoji' => '😀😀😀', 'null' => null,
    ];
}

test('no query parameter of any public or customer list endpoint turns wrong-typed input into a 5xx', function () {
    $failures = [];
    $params = [
        '/api/v1/products' => ['q', 'category', 'in_stock', 'price_min', 'price_max', 'sort', 'page', 'per_page'],
        '/api/v1/categories' => ['with_empty'],
        '/api/v1/orders' => ['status', 'page', 'per_page'],
        '/api/v1/regions/cities' => ['province_id'],
        '/api/v1/regions/districts' => ['city_id'],
        '/api/v1/regions/subdistricts' => ['district_id'],
    ];
    $user = bbCustomer();

    foreach ($params as $uri => $names) {
        foreach ($names as $name) {
            foreach (bbFuzzValues() as $label => $value) {
                $response = $this->actingAs($user)->getJson($uri.'?'.http_build_query([$name => $value]));
                if ($response->getStatusCode() >= 500) {
                    $failures[] = "{$uri}?{$name} = {$label} → {$response->getStatusCode()} ".($response->json('debug.message') ?? '');
                }
            }
        }
    }

    expect($failures)->toBe([]);
});

test('path parameters that are not what the route expects never cause a 5xx', function () {
    $user = bbCustomer();
    $segments = ['0', '-1', '99999999999999999999', 'abc', '%00', '1.5', '😀', str_repeat('a', 3000), 'WEB-000001%20', '..%2F..'];
    $uris = [
        'GET /api/v1/products/', 'GET /api/v1/orders/', 'GET /api/v1/addresses/', 'PUT /api/v1/addresses/', 'DELETE /api/v1/addresses/', 'PATCH /api/v1/addresses/%s/default',
        'PATCH /api/v1/cart/items/', 'DELETE /api/v1/cart/items/', 'POST /api/v1/orders/%s/cancel', 'GET /api/v1/orders/%s/payment',
    ];
    $failures = [];

    foreach ($segments as $segment) {
        foreach ($uris as $entry) {
            [$method, $uri] = explode(' ', $entry);
            $uri = str_contains($uri, '%s') ? sprintf($uri, $segment) : $uri.$segment;
            $status = $this->actingAs($user)->json($method, $uri, ['qty' => 1])->getStatusCode();
            if ($status >= 500) {
                $failures[] = "{$method} ".substr($uri, 0, 80)." → {$status}";
            }
        }
    }

    expect($failures)->toBe([]);
});
