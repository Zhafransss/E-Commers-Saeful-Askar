<?php

/*
| WH-01, the Finish Notify from Gapura (DANA) (docs/api/webhooks.md). DANA posts the payment result
| to the path /v1.0/debit/notify (ASPI format) and signs it with that path, so the route sits at the
| root instead of under /webhooks. Loaded in bootstrap/app.php without session or CSRF. Owner: backend.
*/

use App\Http\Controllers\Webhook\GapuraWebhookController;
use App\Services\Gapura\GapuraClient;
use Illuminate\Support\Facades\Route;

Route::post(ltrim(GapuraClient::NOTIFY_PATH, '/'), [GapuraWebhookController::class, 'notify'])->name('gapura.notify'); // WH-01
