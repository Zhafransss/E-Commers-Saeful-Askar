<?php

use App\Models\User;
use Database\Seeders\DemoCatalogSeeder;

beforeEach(fn () => $this->withoutVite());

test('public pages render', function (string $uri) {
    $this->get($uri)->assertOk()->assertSee(config('app.name'));
})->with(['/', '/products', '/login', '/register', '/forgot-password', '/reset-password?token=abc&email=a@b.test']);

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

test('unknown product slugs are 404 in mock mode too', function () {
    // The detail page is rendered from the synced catalog (SEO), so mocks cannot supply a product.
    config(['app.api_mock' => true]);

    $this->get('/products/hanya-ada-di-mock')->assertNotFound();
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
})->with(['/admin/orders', '/admin/sync', '/admin/users', '/admin/products', '/admin/shipments']);

test('the admin menu names every page and the sync page is called Riwayat Sinkronisasi', function () {
    config(['app.api_mock' => false]);
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/orders')->assertSeeInOrder(['Pesanan', 'Manajemen Resi', 'Produk', 'Manajemen User', 'Manajemen Admin', 'Riwayat Sinkronisasi', 'Keluar']);
    $this->get('/admin/sync')->assertSee('Riwayat Sinkronisasi')->assertDontSee('Sinkron semua produk');
    $this->get('/admin/products')->assertSee('Sinkronisasi dengan Jubelio')->assertDontSee('Sinkron semua produk');
});

test('the return page from DANA opens for a guest, who is asked to log in from the page itself', function () {
    $this->get('/checkout/finish?order=WEB-000001')
        ->assertOk()
        ->assertSee('Masuk untuk melihat status pesanan')
        ->assertSee(route('login', ['next' => '/checkout/finish?order=WEB-000001']), false);
});

test('customer pages render for logged-in customers', function (string $uri) {
    config(['app.api_mock' => false]);
    $this->actingAs(User::factory()->create());

    $this->get($uri)->assertOk();
})->with(['/cart', '/checkout', '/checkout/finish?order=WEB-000001', '/account/orders', '/account/orders/WEB-000001', '/account/addresses']);
