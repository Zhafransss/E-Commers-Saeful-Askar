# PRD Website E-Commerce Saeful Askar

14 Oktober 2026 · Penyusun: Ataka · Revisi 15 Oktober 2026

## 1. Ringkasan produk

Website e-commerce Toko Sepeda Listrik Saeful Askar adalah channel penjualan baru di samping Shopee, dengan Jubelio sebagai pusat operasional untuk produk, stok, dan pengiriman.

**Latar belakang.** Toko menjual sepeda listrik dan baterai sepeda listrik. Saat ini penjualan online hanya lewat Shopee, yang sudah terhubung ke Jubelio. Website ditambahkan untuk memperluas jangkauan tanpa menambah pekerjaan operasional: order website masuk ke Jubelio dan diproses dengan alur gudang yang sama dengan order Shopee.

**Tujuan.**

- Konsumen bisa mencari, melihat, dan membeli produk langsung dari website toko.
- Data produk, harga, dan stok di website sama persis dengan Jubelio.
- Semua order website masuk otomatis ke Jubelio sebagai toko "Website Saeful Askar", terpisah dari Shopee.
- Pembayaran online lewat Xendit dengan batas waktu bayar; order yang tidak dibayar dibatalkan otomatis dan stok kembali.
- Backend dan frontend dikerjakan paralel berdasarkan API Contract yang disepakati di awal.

**Ruang lingkup versi 1.**

- Katalog produk (cari, filter kategori, detail varian) yang disinkron dari Jubelio.
- Registrasi, login, dan buku alamat customer.
- Keranjang, cek ongkir lewat Jubelio Shipment, checkout, dan pembayaran Xendit.
- Riwayat dan pelacakan pesanan (status dan nomor resi dari Jubelio), serta pembatalan pesanan yang belum dibayar.
- Panel admin sederhana: daftar order dan sinkron manual.

## 2. Pengguna dan user stories

Ada dua peran di website: customer yang membeli dan admin toko yang mengelola konten. Operasional gudang tetap dikerjakan staf di Jubelio.

| Peran | Siapa | Kebutuhan utama |
| --- | --- | --- |
| Pengunjung | Calon pembeli yang belum login | Menjelajah katalog tanpa akun |
| Customer | Pembeli yang sudah login | Membeli, menyimpan alamat, melacak pesanan |
| Admin toko | Pemilik atau staf toko | Memantau order website, memicu sinkron |
| Staf gudang | Pengguna Jubelio (bukan pengguna website) | Picking, packing, resi di Jubelio seperti order Shopee |

**User stories.**

1. Sebagai pengunjung, saya bisa mencari produk dengan kata kunci dan memfilter kategori Sepeda Listrik atau Baterai.
2. Sebagai pengunjung, saya bisa melihat detail produk: foto, deskripsi spesifikasi, varian, harga, dan stok tersedia.
3. Sebagai pengunjung, saya diminta login atau daftar saat menambahkan produk ke keranjang.
4. Sebagai customer, saya bisa menyimpan beberapa alamat dan memilih wilayah dari daftar provinsi, kota, kecamatan, kelurahan.
5. Sebagai customer, saya bisa melihat pilihan kurir beserta ongkir dan perkiraan waktu sebelum membayar.
6. Sebagai customer, saya diarahkan ke halaman bayar Xendit dan tahu batas waktu pembayaran.
7. Sebagai customer, saya melihat status pesanan dan nomor resi setelah barang dikirim.
8. Sebagai customer, saya diberi tahu bila pesanan dibatalkan karena batas bayar habis.
9. Sebagai customer, saya bisa membatalkan pesanan yang belum saya bayar, agar tagihan ditutup dan stok kembali untuk pembeli lain.
10. Sebagai admin, saya bisa melihat order website beserta status sinkron ke Jubelio dan menjalankan sinkron katalog manual.

## 3. Kebutuhan fungsional

Kebutuhan dibagi per modul; kode di kolom pertama dipakai sebagai acuan di API Contract dan tiket kerja.

| Kode | Modul | Kebutuhan | Prioritas |
| --- | --- | --- | --- |
| F-01 | Katalog | Daftar produk dengan pencarian nama/SKU, filter kategori, urutan harga/terbaru, paginasi | Wajib |
| F-02 | Katalog | Detail produk: galeri, deskripsi HTML dari Jubelio, pilihan varian, harga, stok, berat per varian | Wajib |
| F-03 | Katalog | Produk dan varian nonaktif atau dihapus di Jubelio tidak tampil | Wajib |
| F-04 | Katalog | Stok 0 ditampilkan "Habis" dan tombol beli dinonaktifkan | Wajib |
| F-05 | Akun | Registrasi (nama, email, no. HP, password), login, logout, lupa password | Wajib |
| F-06 | Akun | Buku alamat dengan wilayah dari endpoint Region Jubelio; satu alamat utama | Wajib |
| F-07 | Keranjang | Tambah, ubah jumlah, hapus item; jumlah dibatasi stok tersedia dan min/max pembelian | Wajib |
| F-08 | Ongkir | Tarif kurir dari Jubelio Shipment berdasarkan gudang Tambun Utara, alamat tujuan, dan total berat varian | Wajib |
| F-09 | Checkout | Cek ulang stok, buat sales order belum bayar di Jubelio, buat invoice Xendit dengan batas waktu | Wajib |
| F-10 | Pembayaran | Webhook PAID menandai order lunas di website dan Jubelio (set-as-paid) | Wajib |
| F-11 | Pembayaran | Webhook EXPIRED (atau job cadangan) membatalkan sales order Jubelio dan order website | Wajib |
| F-12 | Pesanan | Riwayat pesanan, detail, status, nomor resi, dan riwayat status | Wajib |
| F-13 | Sinkron | Sinkron kategori, produk, varian, gambar, harga, stok dari Jubelio (terjadwal + webhook) | Wajib |
| F-16 | Admin | Daftar order website dengan status pembayaran dan status sinkron Jubelio; kirim ulang order gagal | Wajib |
| F-17 | Admin | Tombol sinkron katalog manual dan log sinkron | Sebaiknya |
| F-18 | Notifikasi | Email konfirmasi order, pembayaran diterima, dan pembatalan | Sebaiknya |
| F-19 | Pesanan | Customer membatalkan pesanan yang belum dibayar: invoice Xendit ditutup, sales order Jubelio dibatalkan, stok kembali (ORD-05) | Wajib |

**Aturan bisnis.**

- Pembelian wajib login; tidak ada checkout tamu.
- Harga dan stok selalu dibaca dari data sinkron Jubelio; website tidak boleh mengubahnya.
- Toko tidak memakai PPN: pajak setiap item 0.
- Semua order diambil dari satu gudang: Jl. Raya Pisangan No.1, RT.02/RW.02, Satriajaya, Kec. Tambun Utara, Kab. Bekasi, Jawa Barat 17566.
- Nomor order website berformat WEB-xxxxxx dan dikirim sebagai `ref_no` ke Jubelio.

## 4. Integrasi dan alur bisnis

Website memakai tiga layanan luar; Jubelio adalah sumber kebenaran untuk katalog dan menerima semua order website.

| Layanan | Dipakai untuk | Endpoint / event utama | Catatan |
| --- | --- | --- | --- |
| Jubelio Omnichannel (`api2.jubelio.com`) | Katalog, stok, contact, sales order, region, status kirim | `POST /login`, `GET /inventory/items/`, `GET /inventory/items/group/{id}`, `GET /variations/`, `GET /inventory/categories/item-categories/`, `POST /contacts/`, `POST /sales/orders/`, `POST /sales/orders/set-as-paid`, `POST /sales/orders/cancel/`, `GET /region/*` | Token 12 jam; 600 request/menit; order masuk ke toko Internal "Website Saeful Askar" (`store_id`), `source` = 1 |
| Webhook Jubelio | Perubahan dari Jubelio ke website | `update-product`, `update-price`, `update-qty`, `update-salesorder` | Header `sign` = HMAC-SHA256(body + secret, kunci = secret); dicoba ulang hingga 3 kali |
| Jubelio Shipment | Tarif ongkir saat checkout | API rates (dokumentasi diminta ke Jubelio) | Client ID + Client Secret; ada mode staging |
| Xendit | Pembayaran | Buat invoice dengan durasi; webhook invoice PAID dan EXPIRED | Verifikasi callback token |

**Sinkron katalog.**

1. Sinkron penuh setiap malam dan inkremental setiap 30 menit memakai `last_modified`.
2. Webhook `update-product` dan `update-price` memicu pengambilan ulang item group; `update-qty` memicu pengambilan ulang stok varian.
3. Rekonsiliasi stok setiap 15 menit sebagai cadangan bila webhook terlewat.
4. Berat diambil per varian (`GET /variations/`), dengan cadangan berat item group. Berat per varian sudah dipastikan didukung di akun asli (15 Okt 2026).

**Checkout dan pembayaran.**

1. Customer memilih alamat dan kurir; website meminta tarif ke Jubelio Shipment.
2. Customer menekan Bayar; website mengecek ulang stok ke Jubelio.
3. Bila customer belum punya contact Jubelio, website membuatnya (`POST /contacts/`) dan menyimpan `jubelio_contact_id`.
4. Website membuat sales order belum bayar di Jubelio dengan `ref_no` WEB-xxxxxx, `store_id` toko website, `location_id` gudang Tambun, pajak 0, ongkir dan kurir.
5. Website membuat invoice Xendit dengan batas waktu bayar dan mengarahkan customer ke halaman bayar.
6. Dibayar: webhook PAID → order website `paid` → `set-as-paid` di Jubelio → gudang memproses.
7. Kedaluwarsa: webhook EXPIRED (atau job cadangan tiap 5 menit) → `cancel` sales order Jubelio → stok kembali → order website `expired`.
8. Webhook `update-salesorder` (hanya `store_id` website) memperbarui status dan nomor resi.

**Status order website.** `pending_payment` → `paid` → `processing` → `shipped` → `completed`; cabang `expired` (batas bayar habis) dan `cancelled` (dibatalkan customer sebelum bayar, atau dibatalkan di Jubelio).

## 5. Kebutuhan non-fungsional

Yang paling penting adalah keandalan integrasi: tidak ada order hilang, ganda, atau stok terjual dua kali.

| Area | Kebutuhan |
| --- | --- |
| Keandalan | Webhook diproses idempotent (`webhook_events.idempotency_key`); push order ke Jubelio dicocokkan lewat `ref_no` dan dicoba ulang otomatis bila gagal |
| Konsistensi stok | Cek ulang stok ke Jubelio sebelum membuat order; sales order dibuat saat checkout agar stok terkunci di semua channel |
| Kinerja | Halaman katalog dan API katalog dibaca dari database lokal, tidak memanggil Jubelio per request; respons API katalog di bawah 500 ms |
| Batas API | Panggilan Jubelio di bawah 600 request/menit; token Jubelio di-cache 12 jam |
| Keamanan | Session cookie + CSRF untuk API; verifikasi signature webhook; kredensial hanya di `.env`; customer hanya bisa melihat alamat dan order miliknya (Policy) |
| Responsif | Semua halaman toko nyaman di layar HP 360 px ke atas |
| SEO | Halaman produk dirender Blade dengan judul, deskripsi meta, dan slug yang tetap |
| Observabilitas | `sync_logs` untuk sinkron dan push order; `order_status_histories` untuk jejak status; log error integrasi |
| Pengujian | Feature test tiap endpoint terhadap API Contract; test webhook PAID, EXPIRED, update-qty, dan idempotensi memakai fixture |

## 6. Arsitektur, tech stack, dan model data

Satu aplikasi Laravel monolit: halaman Blade memanggil REST API `/api/v1`, dan semua panggilan ke layanan luar melewati satu lapisan client.

Diagram arsitektur dan alur checkout (Mermaid) ada di [`arsitektur-sistem.md`](arsitektur-sistem.md).

Webhook dari Xendit dan Jubelio dicatat dulu lalu diproses di antrean, sehingga event yang dikirim ulang tidak diproses dua kali.

| Lapisan | Teknologi |
| --- | --- |
| Backend + halaman | Laravel 12, PHP 8.3, Blade |
| Interaksi frontend | Alpine.js, Tailwind CSS (tanpa Bootstrap), Vite |
| Autentikasi | Session cookie Laravel (Sanctum stateful) + CSRF |
| Database | MySQL 8 (lokal: MariaDB 10.4 bawaan XAMPP) |
| Cache dan antrean | Tanpa Redis: driver `database` Laravel untuk cache (token Jubelio 12 jam, tarif ongkir, region), queue job, dan lock sinkron |
| Integrasi | Jubelio API, Jubelio Shipment API, Xendit Invoice API |
| Kontrak dan mock | Markdown per modul (`docs/api/*.md`) + mock JSON di frontend |
| Pengujian | PHPUnit/Pest feature + unit test dengan fixture |

**Model data (ERD v2, 13 tabel).** Rincian kolom, relasi, dan index ada di [`erd.md`](erd.md).

| Kelompok | Tabel | Catatan |
| --- | --- | --- |
| Akun | `users`, `addresses` | `users.jubelio_contact_id`; alamat memakai ID Region Jubelio |
| Katalog (salinan Jubelio) | `categories`, `products`, `product_variants`, `product_images` | product = item group, variant = SKU; `package_weight` per varian |
| Transaksi | `cart_items`, `orders`, `order_items`, `payments`, `order_status_histories` | order menyimpan salinan alamat, nama produk, harga; `jubelio_salesorder_id`, `ref_no` WEB-xxxxxx |
| Integrasi | `webhook_events`, `sync_logs` | idempotensi webhook dan riwayat sinkron |

Konfigurasi integrasi (`store_id` toko website, `location_id` gudang, tax No Tax, kredensial) disimpan di `.env`, bukan di tabel.

## 7. Struktur folder

Struktur Laravel 12 yang sama dengan dokumen arsitektur. Pemilik folder: BE (backend), FE (frontend), BERSAMA (diubah lewat kesepakatan). Titik temu kedua tim adalah folder `docs/api/`, berisi API Contract dalam satu file Markdown per modul.

```
saeful-askar/
├── docs/                                   BERSAMA · sumber kebenaran kontrak
│   ├── api/                                API Contract v1, satu file per modul
│   │   ├── README.md                       konvensi, objek bersama, kode error, indeks endpoint
│   │   ├── auth.md
│   │   ├── catalog.md                      kategori & produk
│   │   ├── cart.md
│   │   ├── addresses-regions.md
│   │   ├── shipping.md                     tarif Jubelio Shipment
│   │   ├── checkout-orders.md
│   │   ├── admin.md                        panel admin: pesanan, sinkron
│   │   ├── webhooks.md                     webhook masuk Xendit & Jubelio
│   │   └── integrations.md                 panggilan keluar ke Jubelio, Jubelio Shipment, Xendit
│   ├── PRD-Website-ECommerce-Saeful-Askar.md   kebutuhan produk (acuan utama)
│   ├── arsitektur-sistem.md                diagram arsitektur, alur checkout, struktur folder
│   └── erd.md                              ERD v2: kolom, relasi, index 13 tabel (pemetaan field Jubelio ada di api/integrations.md)
│
├── app/                                    BE
│   ├── Console/Commands/
│   │   ├── JubelioSyncCategories.php       php artisan jubelio:sync-categories
│   │   ├── JubelioSyncProducts.php         jubelio:sync-products {--full}
│   │   ├── JubelioReconcileStock.php
│   │   └── ExpireUnpaidOrders.php
│   ├── Enums/
│   │   ├── OrderStatus.php                 pending_payment, paid, processing, shipped, completed, cancelled, expired
│   │   ├── PaymentStatus.php
│   │   ├── UserRole.php
│   │   ├── WebhookProvider.php
│   │   └── …                           JubelioSyncStatus, OrderStatusSource, SyncType, SyncStatus, …
│   ├── Exceptions/
│   │   ├── ApiException.php                basis error format kontrak {message, code, errors}
│   │   ├── ApiExceptionRenderer.php        error Laravel (401/403/404/419/422/429/500) → kode kontrak
│   │   ├── NotImplementedException.php     501 untuk endpoint stub selama pengembangan
│   │   ├── JubelioApiException.php
│   │   └── InsufficientStockException.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Web/                        hanya me-return view Blade
│   │   │   │   ├── HomeController.php
│   │   │   │   ├── ProductPageController.php
│   │   │   │   ├── CartPageController.php
│   │   │   │   ├── CheckoutPageController.php
│   │   │   │   ├── AccountPageController.php
│   │   │   │   ├── AuthPageController.php      login, daftar, lupa/reset password
│   │   │   │   └── AdminPageController.php     halaman panel admin
│   │   │   ├── Api/V1/                     endpoint JSON sesuai docs/api/*.md
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── CategoryController.php
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── CartController.php
│   │   │   │   ├── AddressController.php
│   │   │   │   ├── RegionController.php        proxy + cache Region Jubelio
│   │   │   │   ├── ShippingRateController.php  tarif Jubelio Shipment
│   │   │   │   ├── CheckoutController.php
│   │   │   │   └── OrderController.php
│   │   │   ├── Admin/
│   │   │   │   ├── OrderController.php
│   │   │   │   └── SyncController.php          tombol sinkron manual + log
│   │   │   └── Webhook/
│   │   │       ├── XenditWebhookController.php
│   │   │       └── JubelioWebhookController.php
│   │   ├── Middleware/
│   │   │   ├── EnsureUserIsAdmin.php
│   │   │   ├── RequirePageRole.php         guard halaman Blade (dilewati saat VITE_API_MOCK=true)
│   │   │   └── RejectMalformedJson.php     body JSON rusak → 400 BAD_REQUEST
│   │   ├── Requests/Api/V1/                StoreAddressRequest, AddCartItemRequest, CheckoutRequest, …
│   │   └── Resources/Api/V1/               ProductResource, ProductDetailResource, VariantResource, OrderResource, …
│   ├── Jobs/
│   │   ├── Jubelio/
│   │   │   ├── SyncProductGroupJob.php
│   │   │   ├── SyncStockJob.php
│   │   │   ├── PushSalesOrderJob.php
│   │   │   ├── MarkSalesOrderPaidJob.php
│   │   │   └── CancelSalesOrderJob.php
│   │   └── Webhooks/
│   │       ├── ProcessJubelioWebhookJob.php
│   │       └── ProcessXenditWebhookJob.php
│   ├── Models/                             User, Address, Category, Product, ProductVariant, ProductImage, CartItem,
│   │                                       Order, OrderItem, Payment, OrderStatusHistory, WebhookEvent, SyncLog
│   ├── Policies/
│   │   ├── AddressPolicy.php
│   │   └── OrderPolicy.php
│   ├── Services/
│   │   ├── IntegrationClient.php           timeout, log, retry bersama semua client (INT-HDL)
│   │   ├── Jubelio/
│   │   │   ├── JubelioClient.php           satu-satunya pemanggil api2.jubelio.com
│   │   │   ├── JubelioTokenManager.php     token 11,5 jam di cache database
│   │   │   ├── ProductSyncService.php
│   │   │   ├── StockSyncService.php
│   │   │   ├── ContactService.php
│   │   │   ├── SalesOrderService.php
│   │   │   ├── RegionService.php
│   │   │   └── Mappers/
│   │   │       ├── ProductMapper.php
│   │   │       └── SalesOrderPayloadBuilder.php
│   │   ├── JubelioShipment/
│   │   │   ├── JubelioShipmentClient.php
│   │   │   └── ShippingRateService.php
│   │   ├── Xendit/
│   │   │   ├── XenditClient.php
│   │   │   └── InvoiceService.php
│   │   ├── Webhooks/
│   │   │   └── WebhookReceiver.php         verifikasi token/signature → webhook_events → queue
│   │   └── Shop/
│   │       ├── CartService.php
│   │       ├── CheckoutService.php         orkestrasi stok → SO → invoice dalam satu alur
│   │       └── OrderNumberGenerator.php    WEB-000123
│   └── Providers/AppServiceProvider.php
│
├── bootstrap/app.php                       BE · daftar route web, api, webhooks; middleware; CSRF exception untuk webhook
├── config/                                 BE
│   ├── jubelio.php                         base_url, email, password, store_id, location_id, tax_id, webhook_secret
│   ├── jubelio_shipment.php                client_id, client_secret, origin, environment
│   └── xendit.php                          secret_key, callback_token, invoice_duration
│
├── database/                               BE
│   ├── migrations/                         13 tabel sesuai docs/erd.md
│   ├── factories/
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── AdminUserSeeder.php
│       └── DemoCatalogSeeder.php           data contoh untuk FE saat Jubelio belum terhubung
│
├── resources/                              FE
│   ├── css/app.css                         Tailwind 4 (@import 'tailwindcss')
│   ├── js/
│   │   ├── app.js                          inisialisasi Alpine, registrasi komponen
│   │   ├── api/
│   │   │   ├── client.js                   fetch wrapper: CSRF, JSON, error format kontrak
│   │   │   ├── endpoints.js                semua path /api/v1 di satu tempat
│   │   │   └── mocks/                      contract.json (hasil npm run mocks:generate) + overrides.js, aktif bila VITE_API_MOCK=true
│   │   ├── stores/
│   │   │   ├── cart.js                     Alpine.store('cart'): badge, total
│   │   │   └── auth.js
│   │   ├── utils/format.js                 rupiah, berat, tanggal (juga $format di Alpine)
│   │   └── components/
│   │       ├── productList.js              cari, filter kategori, paginasi
│   │       ├── productDetail.js            pilih varian, cek stok, tambah ke keranjang
│   │       ├── cartPage.js
│   │       ├── addressForm.js
│   │       ├── regionPicker.js             provinsi → kota → kecamatan → kelurahan
│   │       ├── shippingOptions.js
│   │       ├── checkout.js
│   │       └── orderTracking.js
│   └── views/
│       ├── layouts/
│       │   ├── app.blade.php
│       │   └── admin.blade.php
│       ├── partials/mock-banner.blade.php  penanda mode mock
│       ├── components/                     x-product-card, x-price, x-stock-badge, x-variant-picker, x-pagination, …
│       ├── pages/
│       │   ├── home.blade.php
│       │   ├── products/index.blade.php
│       │   ├── products/show.blade.php
│       │   ├── cart.blade.php
│       │   ├── checkout/index.blade.php
│       │   ├── checkout/finish.blade.php   halaman kembali dari Xendit
│       │   ├── account/orders/index.blade.php
│       │   ├── account/orders/show.blade.php
│       │   ├── account/addresses.blade.php
│       │   └── auth/login.blade.php · register.blade.php
│       └── admin/
│           ├── orders/index.blade.php
│           └── sync/index.blade.php
│
├── routes/                                 BERSAMA · web.php milik FE, api.php & webhooks.php milik BE
│   ├── web.php                             halaman Blade
│   ├── api.php                             prefix /api/v1
│   ├── webhooks.php                        /webhooks/xendit, /webhooks/jubelio
│   └── console.php                         jadwal sinkron & pembatalan
│
├── tests/                                  BE (wajib)
│   ├── Feature/
│   │   ├── Api/V1/                         tes tiap endpoint sesuai docs/api/*.md
│   │   ├── Checkout/CheckoutFlowTest.php
│   │   └── Webhooks/                       PAID, EXPIRED, update-qty, idempotensi
│   ├── Unit/Services/Jubelio/              mapper & payload builder
│   └── Fixtures/
│       ├── jubelio/                        contoh response & webhook dari spec
│       └── xendit/
│
├── lang/id/                                BERSAMA · pesan validasi & auth Bahasa Indonesia
├── scripts/generate-api-mocks.mjs          BERSAMA · docs/api → resources/js/api/mocks/contract.json
├── public/  storage/  vendor/
├── .env.example
├── README.md                               cara setup lokal & alur branch
├── composer.json
├── package.json                            alpinejs, tailwindcss, vite
└── vite.config.js
```

## 8. Cara kerja paralel, tahapan, dan kriteria rilis

Backend dan frontend mulai bersamaan setelah API Contract disepakati; frontend memakai mock server dari kontrak sampai endpoint asli siap.

**Aturan kerja paralel.**

- Semua endpoint sudah disiapkan sejak awal di folder `docs/api/` (API Contract v1, satu file Markdown per modul: `README.md` untuk konvensi dan indeks, lalu `auth.md`, `catalog.md`, `cart.md`, `addresses-regions.md`, `shipping.md`, `checkout-orders.md`, `admin.md`, `webhooks.md`, `integrations.md`) sebelum pengerjaan dimulai; bila kontrak perlu diubah di tengah jalan, perubahan disepakati berdua dan di-commit terpisah.
- Setiap endpoint di file modul memuat Endpoint, User Story, Request Body, Response Success, Response Failed, dan Acceptance Criteria. Frontend menyalin contoh response ke `resources/js/api/mocks/` dan menyalakannya dengan `VITE_API_MOCK=true`; setelah backend siap cukup mematikan mock.
- Backend menulis feature test yang memastikan response sesuai struktur JSON di dokumen kontrak, dengan fixture Jubelio dan Xendit agar tes tidak memanggil API asli.
- `DemoCatalogSeeder` mengisi katalog contoh agar halaman bisa diuji sebelum Jubelio tersambung.

**Alur branch Git.**

| Branch | Isi | Aturan |
| --- | --- | --- |
| `main` | Versi stabil yang siap rilis | Hanya menerima merge dari `development` setelah uji terpadu |
| `development` | Integrasi backend + frontend | Menerima merge dari `backend` dan `frontend`; perubahan BERSAMA (kontrak `docs/api/`, `routes/web.php`, fondasi) di-commit di sini |
| `backend` | Pekerjaan tim backend | Hanya mengubah folder milik BE (lihat §7) |
| `frontend` | Pekerjaan tim frontend | Hanya mengubah folder milik FE (lihat §7) |

- Perubahan BERSAMA dibuat di `development`, lalu `development` di-merge ke `backend` dan `frontend` agar kedua tim memakai kontrak yang sama.
- Setelah sebuah modul selesai di kedua sisi, `backend` dan `frontend` di-merge ke `development`; feature test wajib lulus sebelum merge.

**Tahapan.** Tanggal belum ditetapkan; urutannya:

1. Fondasi: API Contract v1, migrasi 13 tabel, autentikasi, kerangka layout Blade dan API client.
2. Katalog: sinkron Jubelio (kategori, produk, varian, gambar, stok, berat), webhook produk dan stok, halaman daftar dan detail produk.
3. Akun dan keranjang: registrasi, login, buku alamat dengan Region Jubelio, keranjang.
4. Checkout: tarif Jubelio Shipment, contact dan sales order Jubelio, invoice Xendit, webhook PAID dan EXPIRED, job pembatalan.
5. Pesanan dan panel admin: riwayat dan pelacakan pesanan, panel admin.
6. Uji terpadu dan rilis: uji end-to-end dengan akun Jubelio dan Xendit mode test, lalu produksi.

**Kriteria rilis.**

- [ ] Seluruh produk aktif di Jubelio tampil di website dengan harga, stok, gambar, dan berat yang sama.
- [ ] Perubahan stok di Jubelio (termasuk dari order Shopee) tampil di website dalam 15 menit.
- [ ] Order test website muncul di Jubelio pada toko "Website Saeful Askar" dengan item, alamat, kurir, dan ongkir yang benar.
- [ ] Pembayaran test Xendit menandai order lunas di website dan Jubelio.
- [ ] Invoice yang kedaluwarsa membatalkan sales order Jubelio dan stok kembali.
- [ ] Nomor resi dari Jubelio tampil di halaman pesanan customer.
- [ ] Webhook yang dikirim ulang tidak membuat order atau perubahan ganda.
- [ ] Semua feature test lulus.

## 9. Risiko, pertanyaan terbuka, dan di luar lingkup

Risiko terbesar adalah dokumentasi API rates Jubelio Shipment yang belum tersedia; checkout tidak bisa selesai tanpanya.

| Risiko | Dampak | Penanganan |
| --- | --- | --- |
| Dokumentasi API rates Jubelio Shipment tidak publik | Ongkir di checkout tertunda | Minta dokumentasi + Client ID/Secret ke Jubelio; kontrak `POST /api/v1/shipping/rates` tetap dipakai FE dengan mock |
| Sales order belum bayar mungkin tidak mengurangi stok tersedia | Oversell dengan Shopee | Uji di akun; bila tidak, tambah reservasi stok (`POST /inventory/reserved/`) |
| ~~Berat per varian mungkin tidak didukung Jubelio~~ | — | Terjawab 15 Okt 2026: didukung, berat varian baterai 3.000–27.500 g |
| Webhook terlewat atau terlambat | Stok atau status tidak segar | Rekonsiliasi stok 15 menit dan job pembatalan 5 menit |
| Error Jubelio hanya berupa kode SQL | Order gagal tanpa pesan jelas | Push idempotent via `ref_no`, `sync_logs`, retry otomatis, tombol kirim ulang di admin |

**Pertanyaan terbuka.**

- [ ] Dokumentasi API rates dan kredensial Jubelio Shipment (termasuk staging).
- [ ] Durasi batas bayar invoice Xendit (usulan 1–2 jam).
- [x] Nama header signature webhook Jubelio: `sign`, berisi HMAC-SHA256 (contoh resmi Jubelio di `api/dist.yaml`, bagian Webhook Signature).
- [x] Toko internal dibuat di Jubelio dengan nama "Website" (`store_id` -102, dari `GET /locations/store/`).
- [ ] Metode pembayaran Xendit yang diaktifkan (VA, e-wallet, QRIS, kartu).

**Di luar lingkup versi 1.** Checkout tanpa login, voucher dan promo website, COD, multi-gudang, ulasan produk, wishlist, aplikasi mobile, chat penjual, dan pengelolaan produk atau stok dari website. Endpoint admin berikut juga ditunda ke versi 1.1: ADM-01 (dashboard ringkasan), ADM-15 (daftar webhook masuk), ADM-16 (proses ulang webhook), dan ADM-17 (status konfigurasi integrasi); pemeriksaan order dan pemulihan tetap memakai ADM-09 – ADM-12, rekonsiliasi stok terjadwal, dan perintah `php artisan jubelio:setup`.
