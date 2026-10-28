{{--
    schema.org BikeStore for local SEO (Google Maps / "toko sepeda listrik dekat saya").
    Name, address, phone, and hours must match the Google Business Profile exactly.
    No rating/review markup: Google ignores self-hosted reviews for a business's own
    LocalBusiness, and the testimonials on the site are still samples.
--}}
@php
    $store ??= require resource_path('static/store.php');
    $a = $store['address_parts'];
@endphp
<x-json-ld :data="[
    '@type' => 'BikeStore',
    '@id' => route('products.index').'#store',
    'name' => $store['name'],
    'alternateName' => $store['site_name'],
    'description' => 'Toko sepeda listrik dan motor listrik di Tambun Utara, Bekasi. Jual sepeda listrik, baterai, sparepart, dan jasa service di rumah maupun di toko.',
    'url' => route('products.index'),
    'image' => asset('images/banner.webp'),
    'logo' => asset('favicon.png'),
    'telephone' => $store['phone_e164'],
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => $a['street'],
        'addressLocality' => $a['locality'],
        'addressRegion' => $a['region'],
        'postalCode' => $a['postal_code'],
        'addressCountry' => $a['country'],
    ],
    'geo' => ['@type' => 'GeoCoordinates', 'latitude' => $store['geo']['lat'], 'longitude' => $store['geo']['lng']],
    'hasMap' => $store['maps_url'],
    'openingHoursSpecification' => [[
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
        'opens' => $store['opening_hours']['opens'],
        'closes' => $store['opening_hours']['closes'],
    ]],
    'areaServed' => ['Bekasi', 'Jabodetabek', 'Indonesia'],
    'currenciesAccepted' => 'IDR',
    'sameAs' => collect($store['socials'])->pluck('url')->push($store['maps_url'])->all(),
]" />
