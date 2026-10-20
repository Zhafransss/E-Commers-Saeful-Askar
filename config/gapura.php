<?php

/*
| Gapura (DANA payment gateway), Hosted Checkout (docs/api/integrations.md INT-GP-01 – INT-GP-03,
| docs/api/webhooks.md WH-01). Requests are signed with our RSA private key; DANA's Finish Notify
| is verified with DANA's public key. Credentials come from https://dashboard.dana.id (sandbox first).
*/

return [

    // `sandbox` (api.sandbox.dana.id) or `production` (api.saas.dana.id).
    'env' => env('GAPURA_ENV', 'sandbox'),

    // X-PARTNER-ID, also called the Client ID.
    'client_id' => env('GAPURA_CLIENT_ID'),

    'merchant_id' => env('GAPURA_MERCHANT_ID'),

    // Our private key (PKCS#1 or PKCS#8 PEM file). Keep it outside the web root and out of git;
    // a relative path is resolved from the project root.
    'private_key_path' => env('GAPURA_PRIVATE_KEY_PATH'),

    // DANA's public key, used to verify Finish Notify. The SDK ships DANA's sandbox key,
    // so this is only needed in production (ask DANA for it, see docs/api/integrations.md).
    'dana_public_key_path' => env('GAPURA_DANA_PUBLIC_KEY_PATH'),

    // Sent as the ORIGIN header: the domain the requests come from.
    'origin' => env('GAPURA_ORIGIN', env('APP_URL')),

    // Merchant category code (additionalInfo.mcc), required by Create Order.
    'mcc' => env('GAPURA_MCC'),

    // Public URL DANA posts the payment result to. DANA requires the path /v1.0/debit/notify.
    // Behind a tunnel (ngrok) or a different domain, set the full URL; empty means APP_URL + path.
    'notify_url' => env('GAPURA_NOTIFY_URL'),

    // Payment deadline in seconds; 2 hours pending a final business decision.
    // The sandbox refuses anything beyond 30 minutes, so it is capped there.
    'payment_duration' => (int) env('GAPURA_PAYMENT_DURATION', 7200),

    // DANA's expected timeout is 8 seconds per call.
    'timeout' => (int) env('GAPURA_TIMEOUT', 8),

];
