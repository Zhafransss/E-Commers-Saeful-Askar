<?php

/*
| Jubelio Shipment rates (docs/api/integrations.md INT-JS-01, docs/api/shipping.md).
| The rates API is not documented publicly yet; while JUBELIO_SHIPMENT_DRIVER=stub
| the backend returns sample rates in the SHIP-01 shape.
*/

return [

    'driver' => env('JUBELIO_SHIPMENT_DRIVER', 'stub'),

    'base_url' => env('JUBELIO_SHIPMENT_BASE_URL'),

    'client_id' => env('JUBELIO_SHIPMENT_CLIENT_ID'),

    'client_secret' => env('JUBELIO_SHIPMENT_CLIENT_SECRET'),

    'environment' => env('JUBELIO_SHIPMENT_ENV', 'staging'),

    'timeout' => (int) env('JUBELIO_SHIPMENT_TIMEOUT', 10),

    // A rate_id stays valid for checkout this long (SHIP-01).
    'rate_ttl' => 30 * 60,

    // Identical address + weight requests reuse the cached quote (SHIP-01).
    'quote_cache_ttl' => 5 * 60,

    // Single origin warehouse; never taken from frontend input.
    'origin' => [
        'name' => 'Gudang Tambun Utara',
        'address' => 'Jl. Raya Pisangan No.1, RT.02/RW.02',
        'subdistrict' => 'Satriajaya',
        'subdistrict_id' => env('JUBELIO_SHIPMENT_ORIGIN_SUBDISTRICT_ID'),
        'district' => 'TAMBUN UTARA',
        'city_id' => '3216',
        'city' => 'KAB. BEKASI',
        'province' => 'JAWA BARAT',
        'post_code' => '17566',
    ],

];
