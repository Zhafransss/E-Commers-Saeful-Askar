# Roadmap Backend — Website E-Commerce Saeful Askar

15 Oktober 2026 · Branch `backend` · Acuan: PRD §8 (tahapan), [`api/README.md`](api/README.md) (indeks endpoint), [`api/integrations.md`](api/integrations.md), [`api/webhooks.md`](api/webhooks.md), [`erd.md`](erd.md)

Roadmap ini memecah tahapan PRD §8 menjadi fase kerja backend. Urutannya mengikuti ketergantungan: lapisan integrasi dulu, lalu katalog, akun dan keranjang, checkout, siklus pembayaran, konten dan admin, terakhir uji terpadu. Tanggal belum ditetapkan (sama seperti PRD), jadi ukuran fase ditulis relatif.

Isi teknis setiap fase diringkas dari Acceptance Criteria di API Contract. Bila ada perbedaan, **kontrak yang berlaku**; roadmap ini hanya menentukan urutan dan pengelompokan.

## Ringkasan fase

| Fase | Nama | Tahap PRD | Endpoint v1 | Bergantung pada | Ukuran | Status |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | Fondasi | 1 | AUTH-01 | — | — | Selesai |
| 1 | Infrastruktur integrasi | 1–2 | — (INT-HDL, INT-JB-01, INT-JB-14) | 0 | Sedang | Belum mulai |
| 2 | Katalog & sinkron Jubelio | 2 | CAT-01–03, WH-02–04 | 1 | Besar | Belum mulai |
| 3 | Akun, alamat, keranjang | 3 | AUTH-02–09, REG-01–04, ADDR-01–06, CART-01–06 | 0 (REG: 1, CART: 2) | Besar | Belum mulai |
| 4 | Ongkir & checkout | 4 | SHIP-01, ORD-01 | 2, 3 | Besar | Belum mulai |
| 5 | Pembayaran & siklus pesanan | 4–5 | WH-01, WH-05, ORD-02–05 | 4 | Besar | Belum mulai |
| 6 | Artikel & panel admin | 5 | ART-01–02, ADM-02–14 | 0 (ADM-09–12: 5, ADM-13–14: 2) | Sedang | Belum mulai |
| 7 | Uji terpadu & rilis | 6 | — | 1–6 | Sedang | Belum mulai |

Total: 1 + 6 + 24 + 2 + 6 + 15 = **54 endpoint v1** (49 frontend + 5 webhook). ADM-01, ADM-15, ADM-16, ADM-17 ditunda ke v1.1 dan tidak ada di roadmap ini.

```
Fase 0 ─► Fase 1 ─► Fase 2 ─┬─► Fase 4 ─► Fase 5 ─┬─► Fase 7
   │                         │                     │
   └──► Fase 3 ──────────────┘                     │
   └──► Fase 6 (artikel bisa kapan saja; admin order menunggu Fase 5) ──┘
```

Fase 3 bagian auth dan Fase 6 bagian artikel tidak bergantung pada Jubelio. Keduanya bisa dikerjakan lebih dulu bila kredensial Jubelio atau jawaban pertanyaan terbuka PRD §9 belum tersedia.

## Aturan yang berlaku di setiap fase

Sebuah endpoint dianggap selesai bila:

1. `throw new NotImplementedException(...)` di controller diganti implementasi sesuai `docs/api/*.md`.
2. Response memakai Resource turunan `ApiResource`, dan error memakai `ApiException` dengan `code` dari kontrak.
3. Setiap baris **Acceptance Criteria** endpoint di kontrak punya feature test (helper `assertApiError`).
4. Panggilan keluar hanya lewat `app/Services/*Client.php`, dan test memakai `Http::fake()` + fixture di `tests/Fixtures/`. Tidak ada test yang memanggil API asli.
5. `composer test` dan `npm run contract:check` lulus, dan Pint bersih untuk file yang diubah.

Nama kelas yang sudah disebut kontrak (mis. `CheckoutService`, `JubelioTokenManager`, `MarkSalesOrderPaidJob`) dipakai apa adanya.

Merge ke `development` dilakukan per modul, setelah halaman frontend untuk modul yang sama juga siap (PRD §8). Bila kontrak perlu diubah, perubahannya dibuat di `development` lebih dulu, bukan di `backend`.

---

## Fase 0 — Fondasi (selesai)

Sudah ada di commit `80aca15`:

- Sanctum stateful (`/api/v1`) + CSRF; webhook di `/webhooks` tanpa session dan CSRF.
- Format error kontrak (`ApiExceptionRenderer`), paginasi `{data, meta}`, tanggal ISO 8601 +07:00.
- Middleware `admin`, `page.role`, `RejectMalformedJson`; rate limiter `api`, `auth` (per endpoint), `shipping`, `checkout` di driver `database`.
- 49 route + 5 webhook dengan controller stub (`501 NOT_IMPLEMENTED`); `ContractRoutesTest` dua arah.
- 14 migrasi, model, factory, enum; `AdminUserSeeder`, `DemoCatalogSeeder`, `DemoArticleSeeder`.
- Config `jubelio`, `jubelio_shipment` (driver `stub`), `xendit`; kerangka client di `app/Services`.
- AUTH-01 (`GET /sanctum/csrf-cookie`) sudah berfungsi dari Sanctum.

---

## Fase 1 — Infrastruktur integrasi

**Tujuan.** Menyiapkan semua yang dipakai berulang oleh fase 2–5, supaya setiap modul tinggal memanggil method client dan tidak menulis ulang retry, log, atau verifikasi webhook.

**Cakupan.**

| Komponen | Isi | Acuan |
| --- | --- | --- |
| Aturan umum panggilan keluar | Timeout (Jubelio 15 dtk, Shipment 10 dtk, Xendit 15 dtk); `JubelioApiException` / `IntegrationException`; pemetaan ke `502 INTEGRATION_ERROR`; log method, path, status, dan durasi tanpa data pribadi atau kredensial | INT-HDL |
| Retry | Di queue: 5 percobaan, jeda 10 dtk, 30 dtk, 2 mnt, 5 mnt, 15 mnt. Di dalam request customer (checkout, ongkir, region): paling banyak sekali. Xendit 4xx tidak di-retry | INT-HDL |
| Token Jubelio | `JubelioTokenManager`: token di cache `database` dengan TTL **11 jam 30 menit**; lock agar hanya satu proses yang login; header `authorization: <token>`; bila 401, login ulang sekali lalu ulangi request | INT-JB-01, INT-HDL |
| Penahan laju | Job sinkron dijaga di bawah 600 request/menit; bila 429, tunggu 60 dtk | INT-HDL |
| Pencatat sinkron | Service penulis `sync_logs` (mulai, selesai, gagal, jumlah item, `reference_id`, pesan error Jubelio apa adanya) | ERD `sync_logs`, ADM-10 |
| Penerima webhook | Pola `webhooks.md`: verifikasi → simpan ke `webhook_events` (`idempotency_key` unik; key yang sudah ada → `200` tanpa diproses lagi) → balas `200` (< 1 dtk) → proses di `ProcessXenditWebhookJob` / `ProcessJubelioWebhookJob` → status event `processed` / `ignored` / `failed` | `webhooks.md` |
| Verifikasi webhook | Jubelio: `sha256(raw_body + JUBELIO_WEBHOOK_SECRET)` dibandingkan dengan `hash_equals`, **nama header diatur lewat config**. Xendit: header `x-callback-token`. Gagal → `401 INVALID_SIGNATURE` / `INVALID_CALLBACK_TOKEN`, tetapi event tetap dicatat dengan `signature_valid = false` | WH-01 – WH-05 |
| Setup awal | `php artisan jubelio:setup`: menampilkan `store_id`, `location_id`, `tax_id`; menampilkan nama toko dan gudang dari ID yang terpasang di `.env`; (opsional) mendaftarkan URL webhook | INT-JB-14 |
| Fixture | Contoh response Jubelio dan Xendit di `tests/Fixtures/jubelio`, `tests/Fixtures/xendit` | INT-HDL |

**Definisi selesai.**

- [ ] Unit test client: token di-cache, login hanya sekali walau dua proses meminta bersamaan, login ulang sekali saat 401, timeout menjadi exception yang benar, data pribadi tidak masuk log.
- [ ] Test webhook generik: event yang sama dikirim dua kali hanya diproses sekali; signature/token salah dibalas 401 dan event tercatat `signature_valid = false`.
- [ ] `jubelio:setup` dijalankan di akun asli, dan hasil ID-nya disalin ke `.env`.

**Blocker / pertanyaan terbuka.**

- Nama header signature webhook Jubelio (PRD §9). Dibuat bisa diatur lewat config, lalu dipastikan di akun asli.
- Toko internal "Website Saeful Askar" harus sudah dibuat di Jubelio agar `store_id` bisa diambil.

---

## Fase 2 — Katalog & sinkron Jubelio

**Tujuan.** Produk, varian, gambar, harga, stok, dan berat di database lokal sama dengan Jubelio, dan API katalog dibaca dari database lokal dengan respons di bawah 500 ms (PRD §5).

**Cakupan.**

| Kelompok | Isi | Acuan |
| --- | --- | --- |
| Kategori | `jubelio:sync-categories`. Slug dibuat sekali dan tidak berubah. Kategori yang hilang di Jubelio tidak dihapus bila masih dipakai produk, hanya ditandai tidak dipakai | INT-JB-02 |
| Produk | `jubelio:sync-products {--full}`. Penuh: baca semua halaman sampai `totalCount`. Inkremental: berhenti saat `last_modified` ≤ sinkron terakhir. Item berubah → detail INT-JB-04. Item bundle ikut disinkron | INT-JB-03 |
| Detail produk | `is_active = false` atau `sell_this = false` → tidak tampil. Harga dari `prices[]` dengan `store_id = JUBELIO_STORE_ID`, cadangan `sell_price`. Deskripsi disanitasi sebelum disimpan. Angka string dikonversi ke integer | INT-JB-04 |
| Berat | `package_weight` per SKU (dicocokkan lewat `item_id`), cadangan berat item group | INT-JB-05 |
| Stok | `location_stocks[].available` untuk `JUBELIO_LOCATION_ID`; cocok persis berdasarkan `item_code`. `jubelio:reconcile-stock` tiap 15 menit sebagai cadangan webhook | INT-JB-06 |
| Jadwal | Aktifkan entri di `routes/console.php`: sinkron penuh tiap malam, inkremental tiap 30 menit, rekonsiliasi stok tiap 15 menit, `withoutOverlapping()`. Jam malam (02:00 / 02:30) adalah usulan, belum ditetapkan kontrak | PRD §4 |
| Webhook katalog | WH-02 `update-product` → `SyncProductGroupJob` (INT-JB-04 + INT-JB-05; varian yang hilang → `is_active = false`; produk baru dibuat dengan slug baru). WH-03 `update-price` → harga diambil ulang (item group baru diperlakukan seperti WH-02). WH-04 `update-qty` → `SyncStockJob`; `location_id` lain → `ignored`. Idempotency key `jubelio:<action>:` + SHA256 body. Perubahan terlihat di API paling lambat 1 menit | WH-02 – WH-04 |
| API publik | CAT-01: `product_count` hanya produk aktif dengan varian aktif; kategori kosong disembunyikan kecuali `with_empty=1`; di-cache maks 10 menit dan dihapus setelah sinkron. CAT-02: pencarian nama dan SKU persis, filter kategori dan rentang harga varian, `in_stock=1`, `sort=latest` berdasarkan `jubelio_last_modified`. CAT-03: varian aktif saja (stok 0 tetap tampil), gambar urut `sort_order`, `min_qty`/`max_qty` boleh `null`, slug tidak berubah walau nama berubah, 404 `PRODUCT_NOT_FOUND` | CAT-01 – CAT-03 |
| Halaman Blade | `products/show` memakai data yang sama untuk judul dan meta deskripsi (SEO) | CAT-03 |

**Keputusan teknis di fase ini.** Sanitasi HTML (deskripsi produk, lalu dipakai lagi untuk isi artikel di Fase 6) butuh satu library sanitizer yang dipilih bersama; daftar tag yang diizinkan mengikuti ART-02.

**Definisi selesai.**

- [ ] Sinkron penuh dengan fixture mengisi `categories`, `products`, `product_variants`, `product_images` sesuai pemetaan di `integrations.md`.
- [ ] Produk dan varian yang hilang atau nonaktif di Jubelio menjadi `is_active = false`, tidak dihapus, dan hilang dari CAT-02/CAT-03.
- [ ] Feature test CAT-01–03 untuk setiap Acceptance Criteria, termasuk respons CAT-02 < 500 ms untuk 48 item.
- [ ] Test WH-02–04, termasuk event ganda yang tidak diproses dua kali dan stok dari gudang lain yang diabaikan.
- [ ] Sinkron dijalankan sekali terhadap akun Jubelio asli, dan jumlah produk aktif cocok dengan dashboard Jubelio.

**Blocker / risiko.**

- Berat per varian mungkin tidak didukung Jubelio (INT-JB-05, PRD §9). Perlu dicek di akun asli pada fase ini.
- Harga per toko memakai `JUBELIO_STORE_ID`. Selama ID belum ada, harga jatuh ke `sell_price`.

**Merge.** Bersama halaman daftar dan detail produk dari frontend.

---

## Fase 3 — Akun, alamat, keranjang

**Tujuan.** Customer bisa mendaftar, login, menyimpan alamat dengan wilayah Jubelio, dan mengisi keranjang yang dibatasi stok serta min/max pembelian.

**Cakupan.**

| Kelompok | Endpoint | Catatan implementasi |
| --- | --- | --- |
| Auth | AUTH-02 – AUTH-09 | Email disimpan huruf kecil; nomor `62…` dinormalisasi ke `08…`; contact Jubelio **tidak** dibuat saat registrasi. Login: pesan sama untuk email tak terdaftar dan password salah (`INVALID_CREDENTIALS`), session ID berganti. Logout: session dihapus, keranjang tetap tersimpan. AUTH-05 tidak pernah memuat `password`, `remember_token`, `jubelio_contact_id`. Ganti password (`INVALID_CURRENT_PASSWORD`) dan reset (`INVALID_RESET_TOKEN`) mengeluarkan session lain (`SESSION_DRIVER=database`). Email reset (tautan 60 menit) dikirim lewat queue |
| Region | REG-01 – REG-04 | Lewat INT-JB-08, di-cache **30 hari** per parent ID. Bila Jubelio gagal tetapi cache ada, tetap 200. ID induk tak dikenal → `"data": []`. REG-04 selalu punya `post_code` (boleh `null`) |
| Buku alamat | ADDR-01 – ADDR-06 | `AddressPolicy` (milik user lain → 404 `ADDRESS_NOT_FOUND`). Nama wilayah diisi backend dari data region. Rantai wilayah divalidasi (`REGION_NOT_FOUND`). Alamat pertama otomatis utama; tepat satu alamat utama, diubah dalam satu transaksi; hapus alamat utama → alamat terbaru menjadi utama. Alamat ke-11 ditolak (`ADDRESS_LIMIT_REACHED`) |
| Keranjang | CART-01 – CART-06 | Harga dan stok dibaca dari `product_variants` saat request. `issue` per item (`OUT_OF_STOCK`, `QTY_EXCEEDS_STOCK`, `PRODUCT_INACTIVE`) dan `summary.has_issue`; item bermasalah tidak dihapus otomatis. Varian sama digabung satu baris. `max_qty` dihitung dari semua varian produk yang sama. `OUT_OF_STOCK` menyertakan `available_qty`. Error lain: `VARIANT_NOT_FOUND`, `VARIANT_INACTIVE`, `QTY_LIMIT_EXCEEDED`, `CART_ITEM_NOT_FOUND` (milik user lain → 404). CART-06 tidak pernah 401 (tamu → 0) dan < 100 ms |

**Urutan di dalam fase.** Auth dulu (tidak butuh Jubelio, dan store `auth` di frontend bergantung pada AUTH-05), lalu keranjang (cukup dengan `DemoCatalogSeeder` bila Fase 2 belum selesai), lalu region dan alamat (butuh client Fase 1).

**Ditunda ke Fase 4.** AUTH-06 mengantrekan update contact Jubelio (INT-JB-09) bila user sudah punya `jubelio_contact_id`. Karena `jubelio_contact_id` baru terisi saat checkout pertama, bagian ini dibangun bersama INT-JB-09 di Fase 4. Di Fase 3, AUTH-06 cukup menyimpan nama dan nomor.

**Definisi selesai.**

- [ ] Feature test setiap Acceptance Criteria, termasuk akses ke alamat atau item keranjang milik user lain (dibalas 404, bukan 403, agar ID tidak bocor).
- [ ] Rate limit `auth` tetap per endpoint (test yang sudah ada tetap lulus).
- [ ] Field yang dibaca store frontend (`data.role`, `data.total_qty`, `data.has_issue`, `data.summary.*`) muncul di response asli, bukan hanya di mock.

**Merge.** Auth bisa di-merge lebih dulu bersama halaman masuk/daftar; keranjang dan alamat menyusul bersama halamannya.

---

## Fase 4 — Ongkir & checkout

**Tujuan.** Customer melihat tarif kurir, lalu menekan Bayar. Website mengecek ulang stok ke Jubelio, membuat sales order belum bayar di toko "Website Saeful Askar", membuat invoice Xendit, dan mengarahkan customer ke halaman bayar.

**Cakupan.**

| Kelompok | Isi | Acuan |
| --- | --- | --- |
| Ongkir | SHIP-01 lewat INT-JS-01 (driver `stub` mengembalikan 2–3 layanan contoh sampai dokumentasi ada). Berat = Σ `qty × package_weight`, tanpa item `PRODUCT_INACTIVE`. Asal selalu gudang dari config. Tiap rate disimpan di cache dengan `rate_id` selama 30 menit (user, alamat, berat, kurir, layanan, ongkir). Alamat + berat sama dalam 5 menit diambil dari cache. Error: `CART_EMPTY`, `NO_SHIPPING_SERVICE`, `ADDRESS_NOT_FOUND`, `502 INTEGRATION_ERROR` | SHIP-01, INT-JS-01 |
| Pencatat status | Satu service yang mencatat setiap perubahan status ke `order_status_histories` beserta sumbernya. Dipakai ORD-01 dan diperluas di Fase 5 | `api/README.md` |
| Contact Jubelio | Cari contact berdasarkan email dulu, baru buat bila tidak ada; simpan `jubelio_contact_id` sekali. Sekaligus: job update contact dari AUTH-06 | INT-JB-09 |
| Sales order | Data item dari INT-JB-07 (di-cache 1 jam per `item_id`). SO dengan `ref_no` = `order_number`, `store_id`, `location_id`, `tax_id` No Tax, ongkir, dan `shipper`. Tidak pernah ada dua SO dengan `ref_no` sama (dicek lewat INT-JB-13, cocok persis) | INT-JB-07, INT-JB-10, INT-JB-13 |
| Invoice | `external_id = order_number`, `amount = grand_total`, durasi `XENDIT_INVOICE_DURATION`, nomor HP format E.164, jumlah `items` = `amount`; `expiry_date` disimpan +07:00 | INT-XD-01 |
| Checkout | ORD-01 lewat `CheckoutService`, 9 langkah sesuai kontrak: validasi (`CART_EMPTY`, `CART_HAS_ISSUES`, `PROFILE_INCOMPLETE`, `ADDRESS_NOT_FOUND`) → rate dari cache (`RATE_EXPIRED`) → hitung ulang total (`PRICE_CHANGED`) → cek stok langsung ke Jubelio (`OUT_OF_STOCK`) → contact → simpan order dalam satu transaksi (`jubelio_sync_status = pending`) → SO → invoice → kosongkan keranjang, balas 201 | ORD-01 |
| Gagal di tengah checkout | SO gagal → order `cancelled` ("Gagal membuat pesanan di Jubelio"), keranjang **tidak** dikosongkan, balas 502. Invoice gagal → batalkan SO (INT-JB-12), order `cancelled`, keranjang tidak dikosongkan, balas 502 | ORD-01, INT-JB-12 |
| Request ganda | `Idempotency-Key`: request kedua setelah sukses mengembalikan response 201 yang sama persis (disimpan 24 jam). Key yang sama saat request pertama masih diproses → `409 DUPLICATE_REQUEST`. Dua klik Bayar bersamaan tidak pernah menghasilkan dua SO | ORD-01 |

**Definisi selesai.**

- [ ] Test ORD-01 untuk setiap kode error dan setiap langkah gagal di kontrak.
- [ ] Test dua request bersamaan dan dua request dengan `Idempotency-Key` sama: hanya satu order, satu SO, satu invoice.
- [ ] `grand_total` order = `amount` invoice Xendit = `grand_total` sales order Jubelio; `order_number` berurutan dan unik.
- [ ] Satu checkout uji ke akun Jubelio asli: SO muncul di toko "Website Saeful Askar" dengan status belum bayar, item, alamat, kurir, ongkir, dan `ref_no` yang benar.

**Blocker / risiko.**

| Hal | Dampak ke fase ini | Penanganan sementara |
| --- | --- | --- |
| Dokumentasi rates + kredensial Jubelio Shipment | Ongkir asli belum bisa dipakai | Driver `stub`; cukup ganti driver saat dokumen ada |
| Durasi batas bayar Xendit | Nilai default invoice | `XENDIT_INVOICE_DURATION=7200` (2 jam) sampai diputuskan |
| Metode pembayaran Xendit | Isi `payment_methods` invoice | Diatur lewat config |
| `store_id` toko internal | SO tidak bisa dibuat | Diambil lewat `jubelio:setup` (Fase 1) |
| SO belum bayar mungkin tidak mengurangi stok tersedia | Oversell dengan Shopee | Diuji di akun asli (INT-JB-06); bila terjadi, tambah reservasi stok (`POST /inventory/reserved/`) |

**Merge.** Bersama halaman checkout. Halaman "lanjut bayar" menunggu ORD-04 di Fase 5.

---

## Fase 5 — Pembayaran & siklus pesanan

**Tujuan.** Status order selalu mengikuti Xendit dan Jubelio. Order lunas diteruskan ke gudang; order yang kedaluwarsa atau dibatalkan mengembalikan stok; customer bisa melihat riwayat, status, dan nomor resi.

**Cakupan.**

| Kelompok | Isi | Acuan |
| --- | --- | --- |
| Transisi status | Service Fase 4 diperluas agar menolak transisi yang tidak sah (README "Transisi yang sah"); status Jubelio hanya bergerak maju | `api/README.md`, WH-05 |
| Alur bayar | `PAID`/`SETTLED` pada `pending_payment`: simpan data pembayaran, order → `paid` (`source = xendit`), `MarkSalesOrderPaidJob` → INT-JB-11 (retry 5 kali, lalu `jubelio_sync_status = failed`), email "Pembayaran diterima". `paid_amount` ≠ `amount` → order tidak diubah, event `failed` + log `warning`. Dibayar setelah `expired`/`cancelled` → order tidak diubah, event `failed` "perlu refund/proses manual" | WH-01, INT-JB-11 |
| Alur kedaluwarsa | `EXPIRED` pada `pending_payment`: order → `expired` ("Batas waktu pembayaran habis"), `CancelSalesOrderJob` → INT-JB-12, email "Pesanan dibatalkan". Idempotency key `xendit:{id}:{status}`; `external_id` tak dikenal → `ignored` | WH-01, INT-JB-12 |
| Job cadangan | `ExpireUnpaidOrders` (`orders:expire-unpaid`, tiap 5 menit): untuk order yang lewat `payment_expires_at`, pastikan status invoice ke Xendit (INT-XD-02) lalu jalankan alur yang sesuai | WH-01, INT-XD-02 |
| Webhook sales order | Hanya toko website (lainnya `ignored`); order dicari lewat `salesorder_id`, cadangan `ref_no` (tidak ketemu → `failed`); `last_modified` lebih lama → `ignored`. Pemetaan `is_canceled` / `completed` / `shipped` / `processing` sesuai tabel kontrak; simpan `tracking_no`, kurir, `salesorder_no`; email "Pesanan dikirim" saat pertama kali `shipped` | WH-05 |
| Riwayat pesanan | ORD-02: filter `status` (dipisah koma), paginasi default 10; order yang batal karena gagal dibuat di Jubelio tidak ditampilkan. ORD-03: OrderDetail lewat `OrderPolicy` (milik user lain → 404 `ORDER_NOT_FOUND`); `invoice_url` hanya saat `pending_payment` dan belum lewat batas | ORD-02, ORD-03 |
| Lanjut bayar | ORD-04: mengembalikan invoice yang ada, tidak membuat baru. Bila batas lewat, cek ke Xendit (INT-XD-02) lalu jalankan alur kedaluwarsa atau alur bayar dan balas 409 (`ORDER_NOT_PAYABLE`) | ORD-04 |
| Pembatalan customer | ORD-05: kedaluwarsakan invoice (INT-XD-03) **sebelum** membatalkan SO (INT-JB-12) → `cancelled`. Invoice sudah dibayar → tidak dibatalkan, jalankan alur bayar, balas 409. Pembatalan Jubelio gagal → order tetap `cancelled`, `jubelio_sync_status = failed`, dicatat di `sync_logs`, customer tetap dapat 200. Selain `pending_payment` → `ORDER_NOT_CANCELLABLE`. Callback `EXPIRED` yang datang kemudian diabaikan dengan aman | ORD-05, INT-XD-03 |

**Catatan email.** Kontrak mewajibkan tiga email di fase ini (pembayaran diterima, pesanan dibatalkan, pesanan dikirim), semuanya lewat queue. Email "konfirmasi order" saat checkout ada di PRD F-18 (Sebaiknya) tetapi belum ada di kontrak, jadi tidak termasuk definisi selesai.

**Definisi selesai.**

- [ ] Test webhook PAID, EXPIRED, update-salesorder, dan idempotensi memakai fixture (PRD §5 "Pengujian").
- [ ] Test urutan event yang tidak wajar: dibayar setelah dibatalkan, jumlah bayar berbeda, event Jubelio yang lebih lama, event yang akan memundurkan status. Semuanya tidak mengubah order, dan webhook tetap dibalas `200`.
- [ ] Test ORD-04 dan ORD-05 saat webhook terlambat (Xendit sudah `PAID` atau `EXPIRED`).
- [ ] Pembayaran uji Xendit (mode test) menandai order lunas di website dan Jubelio; invoice yang kedaluwarsa membatalkan SO dan stok kembali.

**Merge.** Bersama halaman riwayat, detail pesanan, dan lanjut bayar.

---

## Fase 6 — Artikel & panel admin

**Tujuan.** Admin bisa mengelola artikel, memantau order website beserta status sinkron Jubelio, mengirim ulang order yang gagal, dan menjalankan sinkron manual.

**Cakupan.**

| Kelompok | Endpoint | Catatan |
| --- | --- | --- |
| Artikel publik | ART-01, ART-02 | Hanya `published` dengan `published_at` ≤ sekarang (draft/terjadwal → 404 `ARTICLE_NOT_FOUND`). `excerpt` 160 karakter dari body tanpa tag, dipotong di batas kata + "…". `image_url` URL lengkap atau `null`. Halaman Blade `articles/show` memakai `title`, `excerpt`, `image_url` untuk meta SEO dan Open Graph |
| Artikel admin | ADM-02 – ADM-07 | Draft dan terjadwal ikut tampil (`status=scheduled`). Multipart (`_method=PUT`, `remove_image=1/0`); gambar di `storage/app/public/articles/` dengan nama acak, gambar lama dihapus saat diganti. Body disanitasi (library dari Fase 2). Slug otomatis dari judul, bentrok → `-2`, `-3`; slug manual bentrok → `SLUG_TAKEN`. `author_id` = admin pembuat dan tidak berubah. `draft` mengosongkan `published_at`. Hapus = permanen beserta gambarnya |
| Upload gambar | ADM-08 | `storage/app/public/articles/content/`, nama acak; lebar > 1600 px diperkecil ke 1600 px (ekstensi GD sudah aktif) |
| Order admin | ADM-09 – ADM-12 | ADM-09 termasuk order yang batal karena gagal dibuat di Jubelio, urut terbaru. ADM-10 memuat `sync_logs` order itu, tanpa kredensial. ADM-11 mencari SO lewat `ref_no` dulu, lalu memilih otomatis `create` / `mark_paid` / `cancel` (`ALREADY_SYNCED` bila aksi yang diminta sudah tercermin di Jubelio). ADM-12 memanggil INT-XD-02 dan menjalankan alur WH-01; aman dipanggil berkali-kali |
| Sinkron manual | ADM-13, ADM-14 | Satu sinkron per jenis lewat atomic lock (`SYNC_ALREADY_RUNNING`); `full` = kategori → produk → stok, masing-masing satu baris `sync_logs`. ADM-14 urut `started_at` terbaru; `sync-logs:prune` menghapus log > 90 hari |

**Urutan.** Artikel (ART dan ADM-02–08) tidak bergantung pada fase lain, jadi bisa dikerjakan kapan saja sebagai pengisi saat menunggu kredensial atau jawaban dari Jubelio. ADM-13–14 menunggu Fase 2; ADM-09–12 menunggu Fase 5.

**Definisi selesai.**

- [ ] Feature test setiap Acceptance Criteria, termasuk customer biasa yang mendapat `403 FORBIDDEN`.
- [ ] Order yang SO-nya gagal bisa dikirim ulang dari ADM-11 tanpa membuat SO ganda.

**Blocker.** Persetujuan frontend atas nama kolom artikel `published_at` dan `title` (PRD §9).

**Merge.** Bersama halaman artikel dan halaman admin.

---

## Fase 7 — Uji terpadu & rilis

**Tujuan.** Memastikan kriteria rilis PRD §8 terpenuhi dengan akun Jubelio dan Xendit mode test, lalu menyiapkan produksi.

**Cakupan.**

- Test penjaga: tidak ada lagi `NotImplementedException` di controller v1 (kode `501` tidak boleh ada saat rilis).
- Uji end-to-end dengan akun asli (mode test): katalog, checkout, bayar, kedaluwarsa, batal, resi. Nilai pemetaan WH-05 dipastikan dengan contoh webhook asli.
- Ukur kinerja API katalog (< 500 ms) dengan data asli; tambah index atau eager loading bila perlu.
- Produksi: cron `php artisan schedule:run` tiap menit, queue worker yang selalu hidup, `APP_DEBUG=false`, cookie session `Secure` (AUTH-01), Xendit mode live (`xnd_production_…`), kredensial hanya di `.env`, `ADMIN_EMAIL`/`ADMIN_PASSWORD` diisi, URL webhook didaftarkan ke domain produksi (`jubelio:setup`), backup database.

**Definisi selesai = kriteria rilis PRD §8.**

- [ ] Seluruh produk aktif di Jubelio tampil di website dengan harga, stok, gambar, dan berat yang sama.
- [ ] Perubahan stok di Jubelio (termasuk dari order Shopee) tampil di website dalam 15 menit.
- [ ] Order test muncul di Jubelio pada toko "Website Saeful Askar" dengan item, alamat, kurir, dan ongkir yang benar.
- [ ] Pembayaran test Xendit menandai order lunas di website dan Jubelio.
- [ ] Invoice yang kedaluwarsa membatalkan sales order Jubelio dan stok kembali.
- [ ] Nomor resi dari Jubelio tampil di halaman pesanan customer.
- [ ] Webhook yang dikirim ulang tidak membuat order atau perubahan ganda.
- [ ] Semua feature test lulus.

---

## Pertanyaan terbuka per fase

Diambil dari PRD §9 dan `integrations.md`. Kolom kedua menunjukkan fase yang paling cepat terhambat.

| Pertanyaan | Dibutuhkan di | Bisa jalan dulu dengan |
| --- | --- | --- |
| Toko internal "Website Saeful Askar" + `store_id` | Fase 1 (setup), Fase 2 (harga per toko), Fase 4 (SO) | Harga jatuh ke `sell_price`; SO belum bisa dibuat |
| Nama header signature webhook Jubelio | Fase 1–2 | Header diatur lewat config |
| Dokumentasi rates + kredensial Jubelio Shipment | Fase 4 | Driver `stub` |
| Durasi batas bayar Xendit | Fase 4 | Default 2 jam |
| Metode pembayaran Xendit | Fase 4 | Diatur lewat config |
| Harga promo Jubelio (`promotion_price`) ikut dipakai atau tidak | Fase 4 | Versi 1 memakai harga INT-JB-04 (INT-JB-07) |
| Nama kolom artikel `published_at`, `title` | Fase 6 | Kontrak saat ini |
