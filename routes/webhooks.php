<?php

/*
| Incoming webhooks (docs/api/webhooks.md), served under /webhooks without
| session or CSRF. Owner: backend.
|
| Token/signature verification (x-callback-token for Xendit, SHA256 signature
| for Jubelio) belongs to each WH-xx implementation: an invalid request must
| still be recorded in webhook_events with signature_valid = false before the
| 401 response, so it cannot be a plain reject-only middleware.
*/

use App\Http\Controllers\Webhook\JubelioWebhookController;
use App\Http\Controllers\Webhook\XenditWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('xendit/invoice', [XenditWebhookController::class, 'invoice'])->name('xendit.invoice');           // WH-01

Route::prefix('jubelio')->name('jubelio.')->group(function () {
    Route::post('product', [JubelioWebhookController::class, 'product'])->name('product');                   // WH-02
    Route::post('price', [JubelioWebhookController::class, 'price'])->name('price');                         // WH-03
    Route::post('stock', [JubelioWebhookController::class, 'stock'])->name('stock');                         // WH-04
    Route::post('salesorder', [JubelioWebhookController::class, 'salesorder'])->name('salesorder');          // WH-05
});
