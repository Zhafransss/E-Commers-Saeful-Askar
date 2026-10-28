<?php

/*
| Blade pages. Owner: frontend. Pages only render a shell; data is fetched by
| Alpine from /api/v1. Paths /reset-password and /checkout/finish are fixed by
| the contract (AUTH-08 email link, DANA redirect URL).
*/

use App\Http\Controllers\Web;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

Route::get('/', Web\HomeController::class)->name('home');

Route::get('/products', [Web\ProductPageController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [Web\ProductPageController::class, 'show'])->name('products.show');

// SEO: generated so the URLs follow APP_URL, whatever domain the site ends up on.
Route::get('/sitemap.xml', fn () => response()
    ->view('seo.sitemap', [
        'pages' => [
            [route('home'), '1.0', 'daily'],
            [route('products.index'), '0.9', 'daily'],
            ...Category::where('is_active', true)
                ->whereHas('products', fn ($products) => $products->visible())
                ->pluck('slug')
                ->map(fn ($slug) => [route('products.index', ['category' => $slug]), '0.8', 'daily']),
        ],
        'products' => Product::visible()->get(['slug', 'updated_at']),
    ])
    ->header('Content-Type', 'application/xml; charset=UTF-8'))->name('sitemap');

Route::get('/robots.txt', fn () => response()
    ->view('seo.robots')
    ->header('Content-Type', 'text/plain; charset=UTF-8'))->name('robots');

// Jasa service lives on the main company site; every link to route('service') lands there.
Route::redirect('/jasa-service', 'https://www.saefulaskar.com/jasa-service')->name('service');

Route::get('/login', [Web\AuthPageController::class, 'login'])->name('login');
Route::get('/register', [Web\AuthPageController::class, 'register'])->name('register');
Route::get('/forgot-password', [Web\AuthPageController::class, 'forgotPassword'])->name('password.request');
Route::get('/reset-password', [Web\AuthPageController::class, 'resetPassword'])->name('password.reset');

// Where DANA brings the customer back. Outside the login guard on purpose: that first request comes
// from another site and may reach us without the session cookie, and a customer who has just paid must
// not be thrown to the login page. The page shows nothing private by itself; it asks ORD-03, which
// needs the login, and tells a guest to log in (finishPage.js).
Route::get('/checkout/finish', [Web\CheckoutPageController::class, 'finish'])->name('checkout.finish');

Route::middleware('page.role:customer')->group(function () {
    Route::get('/cart', Web\CartPageController::class)->name('cart');
    Route::get('/checkout', [Web\CheckoutPageController::class, 'index'])->name('checkout');

    Route::get('/account/orders', [Web\AccountPageController::class, 'orders'])->name('account.orders');
    Route::get('/account/orders/{orderNumber}', [Web\AccountPageController::class, 'orderShow'])->name('account.orders.show');
    Route::get('/account/addresses', [Web\AccountPageController::class, 'addresses'])->name('account.addresses');
});

Route::middleware('page.role:admin')->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/orders');
    Route::get('/orders', [Web\AdminPageController::class, 'orders'])->name('orders.index');
    Route::get('/orders/{orderNumber}/invoice', [Web\AdminPageController::class, 'invoice'])->name('orders.invoice');
    Route::get('/shipments', [Web\AdminPageController::class, 'shipments'])->name('shipments.index');
    Route::get('/products', [Web\AdminPageController::class, 'products'])->name('products.index');
    Route::get('/users', [Web\AdminPageController::class, 'users'])->name('users.index');
    Route::get('/admins', [Web\AdminPageController::class, 'admins'])->name('admins.index');
    Route::get('/sync', [Web\AdminPageController::class, 'sync'])->name('sync.index');
});
