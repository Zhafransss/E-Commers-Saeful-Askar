<?php

/*
| Shipping rates (docs/api/shipping.md SHIP-01, docs/api/integrations.md INT-RO-01, INT-RO-02).
|
| SHIPPING_DRIVER=rajaongkir asks RajaOngkir (Komerce) for real rates; `stub` returns sample rates in
| the same shape, for local work and tests without an API key.
*/

return [

    'driver' => env('SHIPPING_DRIVER', 'stub'),

    // A rate_id stays valid for checkout this long (SHIP-01).
    'rate_ttl' => 30 * 60,

    // Identical address + weight requests reuse the cached quote (SHIP-01). RajaOngkir's free plan
    // allows about 100 cost checks a day, so this also protects the quota.
    'quote_cache_ttl' => (int) env('SHIPPING_QUOTE_CACHE_SECONDS', 30 * 60),

    // Single origin warehouse; never taken from frontend input.
    'origin' => [
        'name' => 'Gudang Tambun Utara',
        'address' => 'Jl. Raya Pisangan No.1, RT.02/RW.02',
        'subdistrict' => 'Satriajaya',
        'district' => 'TAMBUN UTARA',
        'city_id' => '3216',
        'city' => 'KAB. BEKASI',
        'province' => 'JAWA BARAT',
        'post_code' => '17566',
    ],

    'rajaongkir' => [
        'base_url' => env('RAJAONGKIR_BASE_URL', 'https://rajaongkir.komerce.id/api/v1'),

        'api_key' => env('RAJAONGKIR_API_KEY'),

        'timeout' => (int) env('RAJAONGKIR_TIMEOUT', 10),

        // The warehouse's RajaOngkir destination id. Left empty, it is looked up once from the origin address above.
        'origin_id' => env('RAJAONGKIR_ORIGIN_ID'),

        // Codes from RajaOngkir's courier list that the shop offers; all are asked in one request.
        'couriers' => array_values(array_filter(array_map('trim', explode(',', (string) env('RAJAONGKIR_COURIERS', 'jne,sicepat,jnt,ninja,tiki,pos,sap,lion,spx,ide'))))),

        // How long a found destination id is remembered (ids do not change).
        'destination_cache_ttl' => 60 * 60 * 24 * 30,
    ],

];
