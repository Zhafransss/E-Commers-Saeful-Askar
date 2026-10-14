<?php

use App\Models\User;
use Database\Seeders\DemoCatalogSeeder;

beforeEach(fn () => $this->withoutVite());

test('public pages render', function (string $uri) {
    $this->get($uri)->assertOk()->assertSee(config('app.name'));
})->with(['/', '/products', '/articles', '/login', '/register', '/forgot-password', '/reset-password?token=abc&email=a@b.test']);

test('product page renders SEO meta from the synced catalog', function () {
    $this->seed(DemoCatalogSeeder::class);

    $this->get('/products/sepeda-listrik-uwinfly-c1-pro')
        ->assertOk()
        ->assertSee('<title>Sepeda Listrik Uwinfly C1 Pro | ', false)
        ->assertSee('Motor 500W, baterai 48V 12Ah');
});

test('unknown product slugs are 404 outside mock mode', function () {
    config(['app.api_mock' => false]);

    $this->get('/products/tidak-ada')->assertNotFound();
});

test('unknown product slugs still render in mock mode', function () {
    config(['app.api_mock' => true]);

    $this->get('/products/hanya-ada-di-mock')->assertOk();
});

test('account pages send guests to login outside mock mode', function () {
    config(['app.api_mock' => false]);

    $this->get('/account/orders')->assertRedirect('/login');
});

test('account pages render for guests in mock mode', function () {
    config(['app.api_mock' => true]);

    $this->get('/account/orders')->assertOk();
});

test('admin pages reject customers outside mock mode', function () {
    config(['app.api_mock' => false]);
    $this->actingAs(User::factory()->create());

    $this->get('/admin/orders')->assertForbidden();
});

test('admin pages render for admins', function (string $uri) {
    config(['app.api_mock' => false]);
    $this->actingAs(User::factory()->admin()->create());

    $this->get($uri)->assertOk();
})->with(['/admin/orders', '/admin/articles', '/admin/articles/create', '/admin/articles/1/edit', '/admin/sync']);

test('customer pages render for logged-in customers', function (string $uri) {
    config(['app.api_mock' => false]);
    $this->actingAs(User::factory()->create());

    $this->get($uri)->assertOk();
})->with(['/cart', '/checkout', '/checkout/finish?order=WEB-000001', '/account/orders', '/account/orders/WEB-000001', '/account/addresses']);
