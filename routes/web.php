<?php

/*
| Blade pages. Owner: frontend. Pages only render a shell; data is fetched by
| Alpine from /api/v1. Paths /reset-password and /checkout/finish are fixed by
| the contract (AUTH-08 email link, Xendit redirect URL).
*/

use App\Http\Controllers\Web;
use App\Models\Article;
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
            [route('service'), '0.8', 'monthly'],
            [route('about'), '0.6', 'monthly'],
            [route('contact'), '0.6', 'monthly'],
            [route('articles.index'), '0.6', 'weekly'],
        ],
        'products' => Product::visible()->get(['slug', 'updated_at']),
        'articles' => Article::published()->get(['slug', 'updated_at']),
    ])
    ->header('Content-Type', 'application/xml; charset=UTF-8'))->name('sitemap');

Route::get('/robots.txt', fn () => response()
    ->view('seo.robots')
    ->header('Content-Type', 'text/plain; charset=UTF-8'))->name('robots');

// Static company pages; the home page shows a short preview of each.
Route::view('/tentang-kami', 'pages.about')->name('about');
Route::view('/jasa-service', 'pages.service')->name('service');
Route::view('/testimoni', 'pages.testimonials')->name('testimonials');
Route::view('/kontak', 'pages.contact')->name('contact');

Route::get('/articles', [Web\ArticlePageController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [Web\ArticlePageController::class, 'show'])->name('articles.show');

Route::get('/login', [Web\AuthPageController::class, 'login'])->name('login');
Route::get('/register', [Web\AuthPageController::class, 'register'])->name('register');
Route::get('/forgot-password', [Web\AuthPageController::class, 'forgotPassword'])->name('password.request');
Route::get('/reset-password', [Web\AuthPageController::class, 'resetPassword'])->name('password.reset');

Route::middleware('page.role:customer')->group(function () {
    Route::get('/cart', Web\CartPageController::class)->name('cart');
    Route::get('/checkout', [Web\CheckoutPageController::class, 'index'])->name('checkout');
    Route::get('/checkout/finish', [Web\CheckoutPageController::class, 'finish'])->name('checkout.finish');

    Route::get('/account/orders', [Web\AccountPageController::class, 'orders'])->name('account.orders');
    Route::get('/account/orders/{orderNumber}', [Web\AccountPageController::class, 'orderShow'])->name('account.orders.show');
    Route::get('/account/addresses', [Web\AccountPageController::class, 'addresses'])->name('account.addresses');
});

Route::middleware('page.role:admin')->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/orders');
    Route::get('/articles', [Web\AdminPageController::class, 'articles'])->name('articles.index');
    Route::get('/articles/create', [Web\AdminPageController::class, 'articleCreate'])->name('articles.create');
    Route::get('/articles/{id}/edit', [Web\AdminPageController::class, 'articleEdit'])->whereNumber('id')->name('articles.edit');
    Route::get('/orders', [Web\AdminPageController::class, 'orders'])->name('orders.index');
    Route::get('/sync', [Web\AdminPageController::class, 'sync'])->name('sync.index');
});
