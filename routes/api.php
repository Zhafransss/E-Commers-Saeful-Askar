<?php

/*
| API Contract v1 (docs/api/README.md "Indeks endpoint"). Prefix /api/v1 is set
| in bootstrap/app.php. Owner: backend. Each route is labelled with its contract
| code; unfinished endpoints answer 501 NOT_IMPLEMENTED.
|
| Deferred to v1.1 and intentionally absent: ADM-01, ADM-15, ADM-16, ADM-17.
*/

use App\Http\Controllers\Admin;
use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

Route::pattern('id', '[0-9]+');

// --- Public -----------------------------------------------------------------

Route::middleware('throttle:auth')->prefix('auth')->group(function () {
    Route::post('register', [V1\AuthController::class, 'register']);              // AUTH-02
    Route::post('login', [V1\AuthController::class, 'login']);                    // AUTH-03
    Route::post('forgot-password', [V1\AuthController::class, 'forgotPassword']); // AUTH-08
});
Route::post('auth/reset-password', [V1\AuthController::class, 'resetPassword']);  // AUTH-09

Route::get('categories', [V1\CategoryController::class, 'index']);                // CAT-01
Route::get('products', [V1\ProductController::class, 'index']);                   // CAT-02
Route::get('products/{slug}', [V1\ProductController::class, 'show']);             // CAT-03

Route::get('cart/count', [V1\CartController::class, 'count']);                    // CART-06 (guests get 0)

Route::get('articles', [V1\ArticleController::class, 'index']);                   // ART-01
Route::get('articles/{slug}', [V1\ArticleController::class, 'show']);             // ART-02

// --- Customer (any logged-in user) ------------------------------------------

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout', [V1\AuthController::class, 'logout']);              // AUTH-04
        Route::get('me', [V1\AuthController::class, 'me']);                       // AUTH-05
        Route::put('profile', [V1\AuthController::class, 'updateProfile']);       // AUTH-06
        Route::put('password', [V1\AuthController::class, 'updatePassword']);     // AUTH-07
    });

    Route::prefix('cart')->group(function () {
        Route::get('/', [V1\CartController::class, 'show']);                      // CART-01
        Route::post('items', [V1\CartController::class, 'storeItem']);           // CART-02
        Route::patch('items/{id}', [V1\CartController::class, 'updateItem']);     // CART-03
        Route::delete('items/{id}', [V1\CartController::class, 'destroyItem']);   // CART-04
        Route::delete('/', [V1\CartController::class, 'clear']);                 // CART-05
    });

    Route::prefix('regions')->group(function () {
        Route::get('provinces', [V1\RegionController::class, 'provinces']);       // REG-01
        Route::get('cities', [V1\RegionController::class, 'cities']);             // REG-02
        Route::get('districts', [V1\RegionController::class, 'districts']);       // REG-03
        Route::get('subdistricts', [V1\RegionController::class, 'subdistricts']); // REG-04
    });

    Route::get('addresses', [V1\AddressController::class, 'index']);              // ADDR-01
    Route::post('addresses', [V1\AddressController::class, 'store']);             // ADDR-02
    Route::get('addresses/{id}', [V1\AddressController::class, 'show']);          // ADDR-03
    Route::put('addresses/{id}', [V1\AddressController::class, 'update']);        // ADDR-04
    Route::delete('addresses/{id}', [V1\AddressController::class, 'destroy']);    // ADDR-05
    Route::patch('addresses/{id}/default', [V1\AddressController::class, 'setDefault']); // ADDR-06

    Route::post('shipping/rates', [V1\ShippingRateController::class, 'store'])
        ->middleware('throttle:shipping');                                        // SHIP-01

    Route::post('checkout', [V1\CheckoutController::class, 'store'])
        ->middleware('throttle:checkout');                                        // ORD-01

    Route::get('orders', [V1\OrderController::class, 'index']);                   // ORD-02
    Route::get('orders/{orderNumber}', [V1\OrderController::class, 'show']);      // ORD-03
    Route::get('orders/{orderNumber}/payment', [V1\OrderController::class, 'payment']); // ORD-04
    Route::post('orders/{orderNumber}/cancel', [V1\OrderController::class, 'cancel']);  // ORD-05

    // --- Admin --------------------------------------------------------------

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('articles', [Admin\ArticleController::class, 'index']);                 // ADM-02
        Route::post('articles', [Admin\ArticleController::class, 'store']);                // ADM-03
        Route::get('articles/{id}', [Admin\ArticleController::class, 'show']);             // ADM-04
        Route::put('articles/{id}', [Admin\ArticleController::class, 'update']);           // ADM-05 (POST + _method=PUT)
        Route::delete('articles/{id}', [Admin\ArticleController::class, 'destroy']);       // ADM-06
        Route::patch('articles/{id}/status', [Admin\ArticleController::class, 'updateStatus']); // ADM-07
        Route::post('uploads/images', [Admin\ArticleController::class, 'uploadImage']);    // ADM-08

        Route::get('orders', [Admin\OrderController::class, 'index']);                     // ADM-09
        Route::get('orders/{orderNumber}', [Admin\OrderController::class, 'show']);        // ADM-10
        Route::post('orders/{orderNumber}/push-jubelio', [Admin\OrderController::class, 'pushJubelio']);       // ADM-11
        Route::post('orders/{orderNumber}/refresh-payment', [Admin\OrderController::class, 'refreshPayment']); // ADM-12

        Route::post('sync', [Admin\SyncController::class, 'store']);                       // ADM-13
        Route::get('sync-logs', [Admin\SyncController::class, 'logs']);                    // ADM-14
    });
});
