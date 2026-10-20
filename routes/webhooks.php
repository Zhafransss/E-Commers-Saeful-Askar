<?php

/*
| Incoming webhooks (docs/api/webhooks.md), served under /webhooks without
| session or CSRF. Owner: backend. WH-01 (Gapura) is in routes/gapura.php because
| DANA only posts to /v1.0/debit/notify.
|
| Each WH-xx controller hands the request to App\Services\Webhooks\WebhookReceiver,
| which verifies the token/signature, records the event (also when rejected,
| with signature_valid = false), and queues it. Event logic goes in a handler
| registered in config/webhooks.php.
*/

use App\Http\Controllers\Webhook\JubelioWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('jubelio')->name('jubelio.')->group(function () {
    Route::post('product', [JubelioWebhookController::class, 'product'])->name('product');                   // WH-02
    Route::post('price', [JubelioWebhookController::class, 'price'])->name('price');                         // WH-03
    Route::post('stock', [JubelioWebhookController::class, 'stock'])->name('stock');                         // WH-04
    Route::post('salesorder', [JubelioWebhookController::class, 'salesorder'])->name('salesorder');          // WH-05
});
