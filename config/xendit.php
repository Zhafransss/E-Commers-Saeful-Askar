<?php

/*
| Xendit Invoice API (docs/api/integrations.md INT-XD-01 – INT-XD-03, docs/api/webhooks.md WH-01).
| Use an `xnd_development_` key locally/staging and `xnd_production_` in production.
*/

return [

    'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),

    'secret_key' => env('XENDIT_SECRET_KEY'),

    // Compared with the `x-callback-token` header on WH-01.
    'callback_token' => env('XENDIT_CALLBACK_TOKEN'),

    // Payment deadline in seconds; 2 hours pending a final business decision.
    'invoice_duration' => (int) env('XENDIT_INVOICE_DURATION', 7200),

    'timeout' => (int) env('XENDIT_TIMEOUT', 15),

];
