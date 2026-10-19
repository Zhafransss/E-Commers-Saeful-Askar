<?php

/*
| Temporary sample data for the static store pages. Field names follow the
| API Contract (CAT-01/02/03, CART-01) so each page can later swap
| this array for an Alpine fetch without changing its markup.
| Delete this file once the pages read from /api/v1.
*/

$img = fn (string $id, int $w = 800) => "https://images.unsplash.com/{$id}?w={$w}&q=70&auto=format&fit=crop";

$categories = [
    ['id' => 3, 'name' => 'Sepeda Listrik', 'slug' => 'sepeda-listrik', 'parent_id' => null, 'product_count' => 18],
    ['id' => 5, 'name' => 'Sparepart', 'slug' => 'sparepart', 'parent_id' => null, 'product_count' => 24],
    ['id' => 4, 'name' => 'Baterai', 'slug' => 'baterai', 'parent_id' => 5, 'product_count' => 7],
    ['id' => 6, 'name' => 'Charger', 'slug' => 'charger', 'parent_id' => 5, 'product_count' => 5],
];

$bike = ['id' => 3, 'name' => 'Sepeda Listrik', 'slug' => 'sepeda-listrik'];
$battery = ['id' => 4, 'name' => 'Baterai', 'slug' => 'baterai'];
$charger = ['id' => 6, 'name' => 'Charger', 'slug' => 'charger'];
$sparepart = ['id' => 5, 'name' => 'Sparepart', 'slug' => 'sparepart'];

/*
| One row per product: [name, category, image, option label, [option value => [price, stock]], weight g, brand].
| Bikes (PCSY - 08 … 15) and their photos come from the previous saefulaskar.com catalog.
*/
$rows = [
    ['PCSY - 08', $bike, 'images/products/1.webp', 'Warna', ['Merah' => [5500000, 8], 'Biru' => [5500000, 5], 'Kuning' => [5500000, 0], 'Hijau' => [5500000, 4], 'Oranye' => [5500000, 6], 'Hitam' => [5500000, 10]], 28000, 'Saeful Askar'],
    ['PCSY - 09', $bike, 'images/products/2.webp', 'Warna', ['Hitam' => [5800000, 7], 'Merah' => [5800000, 5], 'Kuning' => [5800000, 0]], 30000, 'Saeful Askar'],
    ['PCSY - 10', $bike, 'images/products/3.webp', 'Warna', ['Biru' => [6000000, 0], 'Oranye' => [6000000, 0], 'Hitam' => [6000000, 0]], 32000, 'Saeful Askar'],
    ['PCSY - 11', $bike, 'images/products/4.webp', 'Warna', ['Kuning' => [6200000, 1], 'Hijau' => [6200000, 1], 'Hitam' => [6200000, 0]], 32000, 'Saeful Askar'],
    ['PCSY - 12', $bike, 'images/products/5.webp', 'Warna', ['Hitam' => [6500000, 9], 'Merah' => [6500000, 6]], 34000, 'Saeful Askar'],
    ['PCSY - 13', $bike, 'images/products/6.webp', 'Warna', ['Biru' => [6500000, 5], 'Kuning' => [6500000, 4]], 34000, 'Saeful Askar'],
    ['PCSY - 14', $bike, 'images/products/7.webp', 'Warna', ['Hijau' => [7000000, 3], 'Hitam' => [7000000, 0], 'Merah' => [7250000, 0]], 45000, 'Saeful Askar'],
    ['PCSY - 15', $bike, 'images/products/8.webp', 'Warna', ['Oranye' => [7000000, 6], 'Biru' => [7000000, 5]], 40000, 'Saeful Askar'],
    ['Baterai Lithium 48V', $battery, null, 'Kapasitas', ['12Ah' => [2100000, 15], '20Ah' => [3400000, 8]], 6500, 'Tianeng'],
    ['Baterai Lithium 36V 10Ah', $battery, null, null, ['' => [1650000, 0]], 4800, 'Chilwee'],
    ['Charger Baterai 48V 2A', $charger, null, null, ['' => [350000, 12]], 900, 'Saeful Askar'],
    ['Ban Luar 14 x 2.50', $sparepart, null, null, ['' => [95000, 20]], 1200, 'Saeful Askar'],
];

$items = collect($rows)->map(function (array $row, int $i) {
    [$name, $category, $image, $optionLabel, $options, $weight, $brand] = $row;
    $id = 12 + $i;
    $slug = \Illuminate\Support\Str::slug(($category['slug'] === 'sepeda-listrik' ? 'sepeda listrik ' : '').$name);
    $sku = strtoupper(\Illuminate\Support\Str::slug($name));

    $variants = collect($options)->map(fn ($value, $optionValue) => [
        'id' => $id * 10 + array_search($optionValue, array_keys($options)),
        'sku' => $optionValue === '' ? $sku : $sku.'-'.strtoupper(\Illuminate\Support\Str::slug($optionValue)),
        'name' => $optionValue === '' ? $name : "{$name} - {$optionValue}",
        'variation_values' => $optionLabel ? [['label' => $optionLabel, 'value' => $optionValue]] : [],
        'price' => $value[0],
        'available_qty' => $value[1],
        'in_stock' => $value[1] > 0,
        'package_weight' => $weight,
        'image_url' => $image ? asset(str_replace('.webp', '-640.webp', $image)) : null,
    ])->values();

    return compact('id', 'slug', 'name', 'category', 'image', 'optionLabel', 'variants', 'brand');
});

// CAT-02 list shape.
$products = $items->map(fn ($p) => [
    'id' => $p['id'],
    'slug' => $p['slug'],
    'name' => $p['name'],
    'category' => $p['category'],
    // 640px copies for cards; in production Jubelio supplies thumbnail_url.
    'thumbnail_url' => $p['image'] ? asset(str_replace('.webp', '-640.webp', $p['image'])) : null,
    'price_min' => $p['variants']->min('price'),
    'price_max' => $p['variants']->max('price'),
    'total_available_qty' => $p['variants']->sum('available_qty'),
    'in_stock' => $p['variants']->contains('in_stock', true),
    'variant_count' => $p['variants']->count(),
])->all();

// CAT-03 detail shape for any slug in the list above (null when unknown).
$productDetail = function (string $slug) use ($items) {
    $p = $items->firstWhere('slug', $slug);

    if (! $p) {
        return null;
    }

    $isBike = $p['category']['slug'] === 'sepeda-listrik';
    $description = $isBike
        ? '<p>'.$p['name'].' adalah sepeda listrik harian yang nyaman untuk ke sekolah, ke pasar, maupun jalan santai sore. Rangka kokoh, jok empuk, dan dilengkapi keranjang depan.</p>'
            .'<h3>Spesifikasi</h3><ul><li>Motor: hub belakang 350W</li><li>Baterai: 48V 12Ah, bisa dilepas</li><li>Kecepatan maksimal: 25–35 km/jam</li><li>Jarak tempuh: 30–40 km sekali isi</li><li>Waktu isi daya: 6–8 jam</li><li>Rem: cakram depan, tromol belakang</li></ul>'
            .'<h3>Isi paket</h3><p>Unit sepeda (sudah dirakit dan dicek teknisi), charger, kunci kontak 2 buah, buku manual.</p>'
        : '<p>'.$p['name'].' original, cocok untuk sebagian besar sepeda listrik dan motor listrik harian. Tanyakan kecocokan dengan unit Anda lewat WhatsApp sebelum membeli.</p>'
            .'<h3>Garansi</h3><p>Garansi toko untuk cacat produksi. Pemasangan bisa dilakukan di toko kami.</p>';

    return [
        'id' => $p['id'],
        'slug' => $p['slug'],
        'name' => $p['name'],
        'category' => $p['category'],
        'brand_name' => $p['brand'],
        'description_html' => $description,
        'sell_unit' => $isBike ? 'Unit' : 'Pcs',
        // null when Jubelio leaves min/max empty (CAT-03).
        'min_qty' => $isBike ? 1 : null,
        'max_qty' => null,
        'package_dimensions' => $isBike ? ['length' => 150, 'width' => 40, 'height' => 85] : ['length' => 30, 'width' => 20, 'height' => 15],
        'images' => $p['image'] ? [['id' => $p['id'] * 10, 'url' => asset($p['image']), 'thumbnail_url' => asset(str_replace('.webp', '-640.webp', $p['image'])), 'variant_id' => null, 'sort_order' => 0]] : [],
        'variations' => $p['optionLabel'] ? [['label' => $p['optionLabel'], 'values' => $p['variants']->pluck('variation_values.0.value')->all()]] : [],
        'variants' => $p['variants']->all(),
        'price_min' => $p['variants']->min('price'),
        'price_max' => $p['variants']->max('price'),
        'in_stock' => $p['variants']->contains('in_stock', true),
        'updated_at' => '2026-10-14T20:15:00+07:00',
    ];
};

/*
| Variant lookup for the static localStorage cart (resources/js/static/cart.js).
| Like cart_items in the ERD, the cart stores only variant id + qty; price and stock
| are read from here on every load. `product` is the CART-01 product block; `unit`
| (sell_unit) is copied into order items at checkout, as order_items.unit.
*/
$variantIndex = [];
foreach ($products as $listed) {
    $detail = $productDetail($listed['slug']);
    foreach ($detail['variants'] as $variant) {
        $variantIndex[$variant['id']] = [
            'variant' => $variant,
            'product' => collect($detail)->only('id', 'slug', 'name', 'min_qty', 'max_qty')->all(),
            'unit' => $detail['sell_unit'] ?? 'Unit',
        ];
    }
}

$cartBike = $productDetail('sepeda-listrik-pcsy-09')['variants'][1];
$cartBattery = $productDetail('baterai-lithium-48v')['variants'][1];

// Initial cart rows (cart_items: id, product_variant_id, qty).
$cartLines = [
    ['id' => 5, 'variant_id' => $cartBike['id'], 'qty' => 1],
    ['id' => 6, 'variant_id' => $cartBattery['id'], 'qty' => 1],
];

$cart = [
    'items' => [
        ['id' => 5, 'variant' => $cartBike, 'product' => ['id' => 13, 'slug' => 'sepeda-listrik-pcsy-09', 'name' => 'PCSY - 09', 'min_qty' => 1, 'max_qty' => null], 'qty' => 1, 'subtotal' => 5800000, 'weight_total' => 30000, 'issue' => null],
        ['id' => 6, 'variant' => $cartBattery, 'product' => ['id' => 20, 'slug' => 'baterai-lithium-48v', 'name' => 'Baterai Lithium 48V', 'min_qty' => null, 'max_qty' => null], 'qty' => 1, 'subtotal' => 3400000, 'weight_total' => 6500, 'issue' => null],
    ],
    'summary' => ['item_count' => 2, 'total_qty' => 2, 'subtotal' => 9200000, 'total_weight' => 36500, 'has_issue' => false],
];

// SAMPLE ONLY: replace with real customer reviews (the old site embedded Google
// reviews through an Elfsight widget) before going live.
$testimonials = collect([
    ['Tambun, Bekasi', 'PCSY - 09', 'pelayanan ramah, sepeda dirakit rapi dan langsung bisa dipakai. Dijelaskan juga cara merawat baterainya.'],
    ['Cikarang', 'Service di Rumah', 'teknisi datang sesuai jadwal, masalah rem dan lampu beres dalam satu kunjungan.'],
    ['Bekasi Kota', 'PCSY - 12', 'beli dua unit dapat potongan harga, pengiriman ke rumah cepat dan aman.'],
    ['Cibitung', 'Baterai Lithium 48V', 'ganti baterai di toko, ditunggu kurang dari satu jam. Jarak tempuh kembali seperti baru.'],
    ['Jakarta Timur', 'PCSY - 15', 'admin responsif di WhatsApp, dibantu pilih model yang pas untuk anak.'],
    ['Tambun Selatan', 'Service di Toko', 'motor listrik mati total, dicek dan diperbaiki hari itu juga dengan harga jujur.'],
])->map(fn ($row, $i) => [
    'name' => 'Contoh Pelanggan '.($i + 1),
    'city' => $row[0],
    'rating' => 5,
    'product' => $row[1],
    'text' => 'Contoh testimoni: '.$row[2],
])->all();

return compact('categories', 'products', 'productDetail', 'variantIndex', 'cartLines', 'cart', 'testimonials');
