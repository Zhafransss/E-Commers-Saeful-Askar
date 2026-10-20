<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/*
| Black-box access control for EVERY route: who may call what. Guest → 401, customer on admin → 403, page shells redirect.
*/

$adminApi = [
    ['GET', '/api/v1/admin/orders'], ['GET', '/api/v1/admin/orders/WEB-000001'], ['POST', '/api/v1/admin/orders/WEB-000001/push-jubelio'],
    ['POST', '/api/v1/admin/orders/WEB-000001/refresh-payment'], ['POST', '/api/v1/admin/sync'], ['GET', '/api/v1/admin/sync-logs'],
];

test('admin API: a guest gets 401 and a customer gets 403 on every endpoint, and nothing is changed', function (string $method, string $uri) {
    Queue::fake();

    assertApiError($this->json($method, $uri, ['type' => 'full']), 401, 'UNAUTHENTICATED');
    assertApiError($this->actingAs(bbCustomer())->json($method, $uri, ['type' => 'full']), 403, 'FORBIDDEN');
    Queue::assertNothingPushed();
})->with($adminApi);

test('admin API: an admin passes the gate on every endpoint (not 401/403)', function (string $method, string $uri) {
    Queue::fake();

    $status = $this->actingAs(bbAdmin())->json($method, $uri, ['type' => 'stock'])->getStatusCode();

    expect($status)->not->toBeIn([401, 403])->and($status)->toBeLessThan(500);
})->with($adminApi);

test('a customer cannot make themselves admin through register, profile or any body field', function () {
    fromFrontend()->postJson('/api/v1/auth/register', ['name' => 'Eve', 'email' => 'eve@example.com', 'phone' => '081234567890', 'password' => 'abc12345', 'password_confirmation' => 'abc12345', 'role' => 'admin'])->assertCreated();
    $eve = User::firstWhere('email', 'eve@example.com');
    $this->actingAs($eve)->putJson('/api/v1/auth/profile', ['name' => 'Eve', 'phone' => '081234567890', 'role' => 'admin', 'is_admin' => 1])->assertOk();

    expect($eve->fresh()->isAdmin())->toBeFalse();
    assertApiError($this->actingAs($eve->fresh())->getJson('/api/v1/admin/orders'), 403, 'FORBIDDEN');
});

test('admin order list/detail expose all customers\' orders, with the documented filters', function () {
    $a = bbCustomer(['name' => 'Andi Pratama']);
    $b = bbCustomer(['name' => 'Budi Hartono']);
    $one = Order::factory()->for($a)->create(['order_number' => 'WEB-000101']);
    $two = Order::factory()->for($b)->create(['order_number' => 'WEB-000102', 'status' => 'paid']);
    $admin = bbAdmin();

    $all = $this->actingAs($admin)->getJson('/api/v1/admin/orders')->assertOk();
    expect(collect($all->json('data'))->pluck('order_number')->all())->toBe(['WEB-000102', 'WEB-000101']);
    expect(collect($this->actingAs($admin)->getJson('/api/v1/admin/orders?q=Andi')->json('data'))->pluck('order_number')->all())->toBe(['WEB-000101']);
    expect(collect($this->actingAs($admin)->getJson('/api/v1/admin/orders?status=paid')->json('data'))->pluck('order_number')->all())->toBe(['WEB-000102']);
    expect($this->actingAs($admin)->getJson('/api/v1/admin/orders?q=%25')->json('meta.total'))->toBe(0);
    $this->actingAs($admin)->getJson('/api/v1/admin/orders/WEB-000101')->assertOk()->assertJsonPath('data.order_number', 'WEB-000101');
    assertApiError($this->actingAs($admin)->getJson('/api/v1/admin/orders/WEB-999999'), 404, 'ORDER_NOT_FOUND');
});

test('admin filters validate: bad date, date_to before date_from, unknown status, per_page over 100', function (string $query) {
    assertApiError($this->actingAs(bbAdmin())->getJson("/api/v1/admin/orders?{$query}"), 422, 'VALIDATION_ERROR');
})->with(['date_from=31-12-2026', 'date_from=2026-12-31&date_to=2026-01-01', 'status=nope', 'per_page=101', 'jubelio_sync_status=nope', 'page=0']);

test('admin sync: unknown type is a validation error; a second run of the same kind while running is 409', function () {
    Queue::fake();
    $admin = bbAdmin();

    assertApiError($this->actingAs($admin)->postJson('/api/v1/admin/sync', ['type' => 'everything']), 422, 'VALIDATION_ERROR');
    assertApiError($this->actingAs($admin)->postJson('/api/v1/admin/sync', []), 422, 'VALIDATION_ERROR');

    $this->actingAs($admin)->postJson('/api/v1/admin/sync', ['type' => 'stock'])->assertStatus(202)->assertJsonPath('data.status', 'running');
    assertApiError($this->actingAs($admin)->postJson('/api/v1/admin/sync', ['type' => 'stock']), 409, 'SYNC_ALREADY_RUNNING');
});

test('admin refresh-payment on an order that never had a payment is 409 ORDER_HAS_NO_PAYMENT', function () {
    $order = Order::factory()->create(['order_number' => 'WEB-000201']);

    assertApiError($this->actingAs(bbAdmin())->postJson('/api/v1/admin/orders/WEB-000201/refresh-payment'), 409, 'ORDER_HAS_NO_PAYMENT');
});

test('web pages: customer pages redirect guests to /login; admin pages are 403 for customers and open for admins', function () {
    foreach (['/cart', '/checkout', '/checkout/finish', '/account/addresses', '/account/orders', '/account/orders/WEB-000001', '/admin', '/admin/orders', '/admin/sync'] as $uri) {
        $this->get($uri)->assertRedirect('/login');
    }
    foreach (['/admin/orders', '/admin/sync'] as $uri) {
        $this->actingAs(bbCustomer())->get($uri)->assertForbidden();
        $this->actingAs(bbAdmin())->get($uri)->assertOk();
    }
    $this->actingAs(bbCustomer())->get('/account/orders')->assertOk();
});

test('public pages render for guests', function (string $uri) {
    $this->get($uri)->assertOk();
})->with(['/', '/products', '/login', '/register', '/forgot-password', '/kontak', '/jasa-service', '/testimoni', '/robots.txt', '/sitemap.xml']);

test('API route-model hygiene: unknown API routes and wrong methods answer in the error shape', function () {
    bbExpectErrorShape($this->getJson('/api/v1/tidak-ada')->assertNotFound());
    bbExpectErrorShape($this->deleteJson('/api/v1/orders/WEB-000001')->assertStatus(405));
});

test('VITE_API_MOCK is off by default, so page access control is enforced', function () {
    expect(config('app.api_mock'))->toBeFalse();
});
