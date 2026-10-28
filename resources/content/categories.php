<?php

/*
| SEO copy for the product list and its category views (/products?category=…).
| Permanent content, not sample data: keep it when the static catalog is removed.
| Keys are category slugs from Jubelio; '' is the unfiltered list.
|
| title  → <title> (≤ ~45 characters; " | Saeful Askar" is appended)
| meta   → meta description (≤ ~155 characters)
| h1     → page heading
| intro  → one sentence under the heading
| body   → longer text at the bottom of the list (HTML, rendered with .rich-text)
*/

return [
    '' => [
        'title' => 'Jual Sepeda Listrik, Baterai & Sparepart',
        'meta' => 'Katalog sepeda listrik, baterai lithium, charger, dan sparepart di Saeful Askar Bekasi. Harga dan stok sesuai gudang, kirim ke seluruh Indonesia.',
        'h1' => 'Katalog Produk',
        'intro' => 'Sepeda listrik, baterai, dan sparepart. Harga dan stok selalu sama dengan gudang kami di Tambun Utara, Bekasi.',
        'body' => '<h2>Belanja sepeda listrik dan sparepart di Saeful Askar</h2>'
            .'<p>Saeful Askar adalah toko sepeda listrik di Tambun Utara, Bekasi, yang sudah lebih dari 10 tahun melayani pelanggan di Jabodetabek. Di katalog ini Anda bisa membeli sepeda listrik, baterai lithium, charger, dan sparepart secara online, dengan harga dan stok yang selalu sama dengan gudang kami.</p>'
            .'<p>Pesanan dibayar aman lewat DANA dan dikirim dari gudang Tambun Utara ke seluruh Indonesia. Untuk area Bekasi dan sekitarnya, Anda juga bisa datang langsung ke toko atau memesan service di rumah.</p>',
    ],

    'unit' => [
        'title' => 'Jual Sepeda Listrik Bekasi – Harga Terbaru',
        'meta' => 'Jual sepeda listrik di Bekasi dengan harga terbaru. Pilihan model untuk anak hingga dewasa, stok sesuai gudang, bisa kirim ke seluruh Indonesia.',
        'h1' => 'Jual Sepeda Listrik',
        'intro' => 'Pilihan sepeda listrik untuk anak-anak hingga dewasa, siap kirim dari gudang kami di Bekasi.',
        'body' => '<h2>Memilih sepeda listrik yang tepat</h2>'
            .'<p>Saat membeli sepeda listrik, perhatikan tiga hal: jarak tempuh harian, berat pengendara, dan medan jalan. Untuk pemakaian harian di dalam kota, model dengan baterai 48V sudah cukup untuk perjalanan ke sekolah, pasar, atau kantor. Untuk anak-anak, pilih model yang ringan dengan kecepatan yang bisa dibatasi.</p>'
            .'<h2>Beli sepeda listrik di Bekasi</h2>'
            .'<p>Semua sepeda listrik di katalog ini tersedia di toko kami di Jl. Raya Pisangan No.1, Tambun Utara, Bekasi. Anda bisa membeli online dan dikirim, atau datang langsung untuk mencoba. Bingung memilih model? Tanyakan ke admin kami lewat WhatsApp.</p>',
    ],

    'sparepart' => [
        'title' => 'Sparepart Sepeda Listrik – Baterai & Charger',
        'meta' => 'Sparepart sepeda listrik lengkap: baterai lithium, charger, ban, dan suku cadang lain. Grosir dan eceran, toko di Bekasi, kirim ke seluruh Indonesia.',
        'h1' => 'Sparepart Sepeda Listrik',
        'intro' => 'Baterai, charger, ban, dan suku cadang lain untuk sepeda listrik dan motor listrik. Tersedia grosir dan eceran.',
        'body' => '<h2>Sparepart sepeda listrik grosir dan eceran</h2>'
            .'<p>Kami menyediakan sparepart untuk sepeda listrik dan motor listrik, mulai dari baterai lithium dan charger sampai ban dan suku cadang lainnya. Pastikan spesifikasi seperti tegangan baterai (36V atau 48V) dan ukuran ban sesuai dengan unit Anda; bila ragu, kirim foto unit Anda ke admin lewat WhatsApp.</p>'
            .'<p>Butuh pemasangan? Bawa sepeda Anda ke toko kami di Tambun Utara, Bekasi, atau pesan jasa service di rumah.</p>',
    ],

    'baterai' => [
        'title' => 'Baterai Sepeda Listrik 48V & 36V Lithium',
        'meta' => 'Jual baterai sepeda listrik lithium 48V dan 36V berbagai kapasitas. Ganti baterai di toko kami di Bekasi atau kirim ke seluruh Indonesia.',
        'h1' => 'Baterai Sepeda Listrik',
        'intro' => 'Baterai lithium 36V dan 48V berbagai kapasitas untuk sepeda listrik dan motor listrik.',
        'body' => '<h2>Cara memilih baterai sepeda listrik</h2>'
            .'<p>Sesuaikan tegangan baterai (V) dengan controller dan dinamo sepeda Anda; tegangan yang berbeda bisa merusak komponen. Kapasitas (Ah) menentukan jarak tempuh: makin besar kapasitas, makin jauh jarak yang bisa ditempuh sekali isi daya.</p>'
            .'<p>Agar baterai lithium awet, isi ulang sebelum di bawah 20%, gunakan charger yang sesuai, dan hindari panas berlebih. Penggantian baterai bisa dilakukan di toko kami di Tambun Utara, Bekasi.</p>',
    ],

    'charger' => [
        'title' => 'Charger Sepeda Listrik 48V & 36V',
        'meta' => 'Jual charger sepeda listrik untuk baterai 48V dan 36V. Pilih charger yang sesuai agar baterai awet. Toko di Bekasi, kirim ke seluruh Indonesia.',
        'h1' => 'Charger Sepeda Listrik',
        'intro' => 'Charger untuk baterai sepeda listrik dan motor listrik 36V maupun 48V.',
        'body' => '<h2>Pilih charger yang sesuai baterai</h2>'
            .'<p>Charger harus cocok dengan tegangan dan jenis baterai (lithium atau aki). Charger yang tidak sesuai bisa membuat baterai cepat panas dan menurunkan umurnya. Bila ragu, tanyakan ke admin kami dengan menyebutkan tipe baterai Anda.</p>',
    ],
];
