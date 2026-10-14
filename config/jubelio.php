<?php

/*
| Jubelio Omnichannel (docs/api/integrations.md INT-JB-01 – INT-JB-14).
| IDs below are found with `php artisan jubelio:setup` (INT-JB-14), never guessed.
*/

return [

    'base_url' => env('JUBELIO_BASE_URL', 'https://api2.jubelio.com'),

    'email' => env('JUBELIO_EMAIL'),

    'password' => env('JUBELIO_PASSWORD'),

    // Internal store "Website Saeful Askar" (channel_id 1); sent as sales order store_id.
    'store_id' => env('JUBELIO_STORE_ID'),

    // Warehouse Tambun Utara; the only stock location used by the website.
    'location_id' => (int) env('JUBELIO_LOCATION_ID', 0) ?: null,

    // Tax "No Tax": the store does not charge VAT.
    'tax_id' => (int) env('JUBELIO_TAX_ID', 0) ?: null,

    // Webhook signature = sha256(raw body + secret) (WH-02 – WH-05).
    'webhook_secret' => env('JUBELIO_WEBHOOK_SECRET'),

    'timeout' => (int) env('JUBELIO_TIMEOUT', 15),

    // Token lives 12 hours; cached slightly shorter (INT-JB-01).
    'token_ttl' => (int) env('JUBELIO_TOKEN_TTL', 41400),

    // Keep sync below Jubelio's limit of 600 requests/minute.
    'rate_limit_per_minute' => (int) env('JUBELIO_RATE_LIMIT', 500),

];
