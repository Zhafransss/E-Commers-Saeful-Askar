<?php

/*
| Jubelio Omnichannel (docs/api/integrations.md INT-JB-01 – INT-JB-14).
| IDs below are found with `php artisan jubelio:setup` (INT-JB-14), never guessed.
*/

return [

    'base_url' => env('JUBELIO_BASE_URL', 'https://api2.jubelio.com'),

    'email' => env('JUBELIO_EMAIL'),

    'password' => env('JUBELIO_PASSWORD'),

    // The website's internal store (channel_id 1; named "Website" in the store's account),
    // listed by GET /locations/store/. Sent as sales order store_id.
    'store_id' => env('JUBELIO_STORE_ID'),

    // Warehouse Tambun Utara; the only stock location used by the website.
    'location_id' => (int) env('JUBELIO_LOCATION_ID', 0) ?: null,

    // Tax "No Tax": the store does not charge VAT.
    'tax_id' => (int) env('JUBELIO_TAX_ID', 0) ?: null,

    // Same value as the Webhook Secret Key in Jubelio (Pengaturan → Developer → Webhook).
    // Signature = HMAC-SHA256(trim(body) + secret, key = secret) (WH-02 – WH-05).
    'webhook_secret' => env('JUBELIO_WEBHOOK_SECRET'),

    // Header carrying the signature: `sign` in Jubelio's official example
    // (docs/api/dist.yaml, "Webhook Signature"). A rejected delivery lists the
    // header names it arrived with in webhook_events.error, in case it changes.
    'webhook_signature_header' => env('JUBELIO_WEBHOOK_SIGNATURE_HEADER') ?: 'sign',

    // false: Jubelio numbers the sales order itself (`SO-000981`, sent as "[auto]"). true: the website's own order
    // number (`WS-000123`, see ORDER_NUMBER_PREFIX) is sent as the sales order number, since the prefix cannot be
    // changed in the Jubelio account. Jubelio may refuse a number of its own; try it with one order first.
    'use_order_number' => (bool) env('JUBELIO_USE_ORDER_NUMBER', false),

    'timeout' => (int) env('JUBELIO_TIMEOUT', 15),

    // Token lives 12 hours; cached slightly shorter (INT-JB-01).
    'token_ttl' => (int) env('JUBELIO_TOKEN_TTL', 41400),

    // After Jubelio rejects the credentials, pause further logins this long (seconds).
    'login_failure_cooldown' => (int) env('JUBELIO_LOGIN_FAILURE_COOLDOWN', 300),

    // Keep sync below Jubelio's limit of 600 requests/minute.
    'rate_limit_per_minute' => (int) env('JUBELIO_RATE_LIMIT', 500),

];
