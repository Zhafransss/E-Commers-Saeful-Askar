<?php

/*
| Store contact details shown in the header, footer, and home page, taken from
| the previous saefulaskar.com site. Kept in one place so a change of number or
| hours is a one-line edit.
*/

$wa = fn (string $phone, string $text) => "https://api.whatsapp.com/send?phone={$phone}&text=".rawurlencode($text);

return [
    // Brand used in <title> ("… | Saeful Askar"), Open Graph, and structured data: APP_NAME.
    'site_name' => config('app.name'),
    'name' => 'Toko Sepeda Listrik Saeful Askar',

    // Local SEO (schema.org BikeStore). Keep identical to the Google Business Profile.
    'address_parts' => [
        'street' => 'Jl. Raya Pisangan No.1, RT.02/RW.02, Satriajaya',
        'locality' => 'Tambun Utara',
        'region' => 'Jawa Barat',
        'postal_code' => '17566',
        'country' => 'ID',
    ],
    // Pin of "TOKO SEPEDA LISTRIK SAEFUL ASKAR" from the Google Maps embed below.
    'geo' => ['lat' => -6.206788860801367, 'lng' => 107.0363217759731],
    'opening_hours' => ['opens' => '08:00', 'closes' => '21:00'],
    'phone_e164' => '+6282227222971',

    'address' => 'Jl. Raya Pisangan No.1, RT.02/RW.02, Satriajaya, Kec. Tambun Utara, Kabupaten Bekasi, Jawa Barat 17566',
    'address_short' => 'Jl. Raya Pisangan No.1, Tambun Utara, Bekasi',
    'hours' => 'Setiap hari, 08.00 – 21.00 WIB',
    'phone' => '+62 822-2722-2971',
    'maps_embed' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3966.430645192125!2d107.0363217759731!3d-6.206788860801367!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e698933b3c0896b%3A0xeb11509fdb438cca!2sTOKO%20SEPEDA%20LISTRIK%20SAEFUL%20ASKAR!5e0!3m2!1sid!2sid!4v1707741757773!5m2!1sid!2sid',
    'maps_url' => 'https://maps.google.com/?q=TOKO+SEPEDA+LISTRIK+SAEFUL+ASKAR',
    'wa' => [
        'shop' => $wa('6282227222971', 'Halo min, saya mau belanja Sepeda Listrik di toko Saeful Askar.'),
        'partner' => $wa('6287781041993', 'Halo min, saya mau bergabung dengan Saeful Askar sebagai partner.'),
        'service' => $wa('6282227222971', 'Halo min, saya mau tanya soal service sepeda listrik'),
        'wholesale' => $wa('6282227222971', 'Halo min, saya mau beli sparepart grosir'),
    ],
    // Figures from the previous site's "Tentang Kami" page (home preview and /tentang-kami).
    'stats' => [['10+', 'Tahun pengalaman'], ['18+', 'Varian produk'], ['1000+', 'Unit terjual']],

    // Service options: summary on the home page, full detail on /jasa-service.
    'services' => [
        [
            'title' => 'Service di Rumah',
            'text' => 'Teknisi kami datang langsung ke lokasi Anda. Tidak perlu repot membawa sepeda listrik ke toko.',
            'points' => ['Mudah dan praktis', 'Jadwal fleksibel', 'Kapan saja sesuai kebutuhan'],
            'wa' => $wa('6282227222971', 'Halo min, saya mau melakukan service sepeda listrik di rumah'),
            'icon' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z',
        ],
        [
            'title' => 'Service di Toko',
            'text' => 'Bawa sepeda listrik Anda ke toko kami dan langsung ditangani teknisi profesional.',
            'points' => ['Pengerjaan instan', 'Bisa ditinggal', 'Peralatan lengkap'],
            'wa' => $wa('6282227222971', 'Halo min, saya mau melakukan service sepeda listrik di toko'),
            'icon' => 'M3 9h18l-1.5-5h-15L3 9Zm0 0v11h18V9M9 20v-6h6v6',
        ],
    ],
    'marketplaces' => [
        ['name' => 'Shopee', 'logo' => 'images/shopee.png', 'url' => 'https://shopee.co.id/sepeda_listrik_bekasi'],
        ['name' => 'Tokopedia', 'logo' => 'images/tokopedia.png', 'url' => 'https://www.tokopedia.com/lampuledgrosir'],
        ['name' => 'Lazada', 'logo' => 'images/lazada.png', 'url' => 'https://www.lazada.co.id/shop/saeful-askar-bekasi/'],
    ],
    'socials' => [
        ['name' => 'Instagram', 'url' => 'https://www.instagram.com/saefulaskar_bikestore', 'icon' => 'M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm5 5a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6Zm5.5-3.5a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z'],
        ['name' => 'TikTok', 'url' => 'https://www.tiktok.com/@saefulaskar', 'icon' => 'M16.6 5.8A4.3 4.3 0 0 1 15.5 3h-3.1v12.4a2.6 2.6 0 1 1-2.6-2.6c.3 0 .5 0 .8.1V9.7a5.7 5.7 0 1 0 4.9 5.7V9.1a7.3 7.3 0 0 0 4.3 1.4V7.4a4.3 4.3 0 0 1-3.2-1.6Z'],
        ['name' => 'YouTube', 'url' => 'https://youtube.com/@saefulaskar9543', 'icon' => 'M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8ZM10 15V9l5.2 3L10 15Z'],
    ],
];
