# Roadmap Backend — Website E-Commerce Saeful Askar

15 Oktober 2026 · Branch `backend` · Acuan: PRD §8 (tahapan), [`api/README.md`](api/README.md) (indeks endpoint), [`api/integrations.md`](api/integrations.md), [`api/webhooks.md`](api/webhooks.md), [`erd.md`](erd.md)

Roadmap ini memecah tahapan PRD §8 menjadi fase kerja backend. Urutannya mengikuti ketergantungan: lapisan integrasi dulu, lalu katalog, akun dan keranjang, checkout, siklus pembayaran, konten dan admin, terakhir uji terpadu. Tanggal belum ditetapkan (sama seperti PRD), jadi ukuran fase ditulis relatif.

Isi teknis setiap fase diringkas dari Acceptance Criteria di API Contract. Bila ada perbedaan, **kontrak yang berlaku**; roadmap ini hanya menentukan urutan dan pengelompokan.

## Ringkasan fase

| Fase | Nama | Tahap PRD | Endpoint v1 | Bergantung pada | Ukuran | Status |
| --- | --- | --- | --- | --- | --- | --- |
| 0 | Fondasi | 1 | AUTH-01 | — | — | Selesai |
| 1 | Infrastruktur integrasi | 1–2 | — (INT-HDL, INT-JB-01, INT-JB-14) | 0 | Sedang | Selesai |
| 2 | Katalog & sinkron Jubelio | 2 | CAT-01–03, WH-02–04 | 1 | Besar | Selesai (webhook asli pertama belum diterima) |
| 3 | Akun, alamat, keranjang | 3 | AUTH-02–09, REG-01–04, ADDR-01–06, CART-01–06 | 0 (REG: 1, CART: 2) | Besar | Selesai |
| 4 | Ongkir & checkout | 4 | SHIP-01, ORD-01 | 2, 3 | Besar | Selesai (ongkir masih driver `stub`) |
| 5 | Pembayaran & siklus pesanan | 4–5 | WH-01, WH-05, ORD-02–05 | 4 | Besar | Selesai kecuali WH-05 (resi/status dikirim, ditunda) |
| 6 | Panel admin | 5 | ADM-09–14 | ADM-09–12: 5, ADM-13–14: 2 | Sedang | API selesai (halaman admin belum) |
| 7 | Uji terpadu & rilis | 6 | — | 1–6 | Sedang | Belum mulai |

Total: 1 + 6 + 24 + 2 + 6 + 6 = **45 endpoint v1** (40 frontend + 5 webhook). Fitur artikel (ART-01–02, ADM-02–08) dihapus dari lingkup. ADM-01, ADM-15, ADM-16, ADM-17 ditunda ke v1.1 dan tidak ada di roadmap ini.

```
Fase 0 ─► Fase 1 ─► Fase 2 ─┬─► Fase 4 ─► Fase 5 ─┬─► Fase 7
   │                         │                     │
   └──► Fase 3 ──────────────┘                     │
   └──► Fase 6 (admin order menunggu Fase 5; sinkron manual menunggu Fase 2) ──┘
```

Fase 3 bagian auth tidak bergantung pada Jubelio, sehingga bisa dikerjakan lebih dulu bila kredensial Jubelio atau jawaban pertanyaan terbuka PRD §9 belum tersedia.

## Aturan yang berlaku di setiap fase

Sebuah endpoint dianggap selesai bila:

1. `throw new NotImplementedException(...)` di controller diganti implementasi sesuai `docs/api/*.md`.
2. Response memakai Resource turunan `ApiResource`, dan error memakai `ApiException` dengan `code` dari kontrak.
3. Setiap baris **Acceptance Criteria** endpoint di kontrak punya feature test (helper `assertApiError`).
4. Panggilan keluar hanya lewat `app/Services/*Client.php`, dan test memakai `Http::fake()` + fixture di `tests/Fixtures/`. Tidak ada test yang memanggil API asli.
5. `composer test` dan `npm run contract:check` lulus, dan Pint bersih untuk file yang diubah.

Nama kelas yang sudah disebut kontrak (mis. `CheckoutService`, `JubelioTokenManager`, `MarkSalesOrderPaidJob`) dipakai apa adanya.

Pesan commit diberi label fase: `<type> [fase N]: <ringkasan>`, mis. `feat [fase 2]: add catalog sync from Jubelio` atau `fix [fase 3]: keep cart after logout`.

Merge ke `development` dilakukan per modul, setelah halaman frontend untuk modul yang sama juga siap (PRD §8). Bila kontrak perlu diubah, perubahannya dibuat di `development` lebih dulu, bukan di `backend`.

---

## Fase 0 — Fondasi (selesai)

Sudah ada di commit `80aca15`:

- Sanctum stateful (`/api/v1`) + CSRF; webhook di `/webhooks` tanpa session dan CSRF.
- Format error kontrak (`ApiExceptionRenderer`), paginasi `{data, meta}`, tanggal ISO 8601 +07:00.
- Middleware `admin`, `page.role`, `RejectMalformedJson`; rate limiter `api`, `auth` (per endpoint), `shipping`, `checkout` di driver `database`.
- 40 route + 5 webhook dengan controller stub (`501 NOT_IMPLEMENTED`); `ContractRoutesTest` dua arah.
- 13 migrasi, model, factory, enum; `AdminUserSeeder`, `DemoCatalogSeeder`.
- Config `jubelio`, `jubelio_shipment` (driver `stub`), `gapura`; kerangka client di `app/Services`.
- AUTH-01 (`GET /sanctum/csrf-cookie`) sudah berfungsi dari Sanctum.

---

## Fase 1 — Infrastruktur integrasi

**Tujuan.** Menyiapkan semua yang dipakai berulang oleh fase 2–5, supaya setiap modul tinggal memanggil method client dan tidak menulis ulang retry, log, atau verifikasi webhook.

**Cakupan.**

| Komponen | Isi | Acuan |
| --- | --- | --- |
| Aturan umum panggilan keluar | Timeout (Jubelio 15 dtk, Shipment 10 dtk, Gapura 8 dtk); `JubelioApiException` / `IntegrationException`; pemetaan ke `502 INTEGRATION_ERROR`; log method, path, status, dan durasi tanpa data pribadi atau kredensial | INT-HDL |
| Retry | Di queue: 5 percobaan, jeda 10 dtk, 30 dtk, 2 mnt, 5 mnt, 15 mnt. Di dalam request customer (checkout, ongkir, region): paling banyak sekali. Gapura 4xx tidak di-retry; 429 dan 5xx di-retry dengan data yang sama | INT-HDL |
| Token Jubelio | `JubelioTokenManager`: token di cache `database` dengan TTL **11 jam 30 menit**; lock agar hanya satu proses yang login; header `authorization: <token>`; bila 401, login ulang sekali lalu ulangi request | INT-JB-01, INT-HDL |
| Penahan laju | Job sinkron dijaga di bawah 600 request/menit; bila 429, tunggu 60 dtk | INT-HDL |
| Pencatat sinkron | Service penulis `sync_logs` (mulai, selesai, gagal, jumlah item, `reference_id`, pesan error Jubelio apa adanya) | ERD `sync_logs`, ADM-10 |
| Penerima webhook | Pola `webhooks.md`: verifikasi → simpan ke `webhook_events` (`idempotency_key` unik; key yang sudah ada → `200` tanpa diproses lagi) → balas `200` (< 1 dtk) → proses di `ProcessGapuraWebhookJob` / `ProcessJubelioWebhookJob` → status event `processed` / `ignored` / `failed` | `webhooks.md` |
| Verifikasi webhook | Jubelio: header `sign` = `hash_hmac('sha256', trim(raw_body) . secret, secret)` dibandingkan dengan `hash_equals` (contoh resmi di `api/dist.yaml`; nama header tetap bisa diubah lewat config). Gapura: `X-SIGNATURE` RSA SHA-256 diverifikasi SDK dengan public key DANA terhadap path `/v1.0/debit/notify`. Gagal → `401 INVALID_SIGNATURE`, tetapi event tetap dicatat dengan `signature_valid = false` | WH-01 – WH-05 |
| Setup awal | `php artisan jubelio:setup`: menampilkan `store_id`, `location_id`, `tax_id`; menampilkan nama toko dan gudang dari ID yang terpasang di `.env`; (opsional) mendaftarkan URL webhook | INT-JB-14 |
| Fixture | Contoh response Jubelio dan Gapura di `tests/Fixtures/jubelio`, `tests/Fixtures/gapura`; `GapuraClient` dites dengan Guzzle mock handler dan pasangan kunci RSA sementara (`gapuraKeys()`) | INT-HDL |

**Definisi selesai.**

- [x] Unit test client: token di-cache, login hanya sekali walau dua proses meminta bersamaan, login ulang sekali saat 401, timeout menjadi exception yang benar, data pribadi tidak masuk log.
- [x] Test webhook generik: event yang sama dikirim dua kali hanya diproses sekali; signature/token salah dibalas 401 dan event tercatat `signature_valid = false`.
- [x] `jubelio:setup` dijalankan di akun asli (15 Okt 2026), dan hasil ID-nya disalin ke `.env`: toko "Website" `-102`, gudang "Pusat" `-1`, pajak "No Tax" `1`.

**Cara memakai hasil Fase 1 di fase berikutnya.**

| Kebutuhan | Pakai | Catatan |
| --- | --- | --- |
| Panggil Jubelio | `JubelioClient::get()` / `post()`, atau tambah method INT-JB-xx di kelas itu | Token, login ulang saat 401, batas laju, dan log sudah otomatis |
| Panggil Gapura / Jubelio Shipment | Method INT-GP-01 – 03 sudah ada di `GapuraClient` (SDK `danaid/dana-php`); INT-JS-01 ditambah di `JubelioShipmentClient` (memakai `get()`/`post()` protected) | Error 4xx Gapura otomatis tidak di-retry |
| Panggilan saat customer menunggu | `$client->withinCustomerRequest()->…` | Retry paling banyak sekali |
| Pesan 502 khusus endpoint | `catch (IntegrationException $e) { throw $e->withMessage('…'); }` | Teks dari kontrak endpoint |
| Job yang memanggil layanan luar | `use Queueable, RetriesIntegrationCalls;` + method `failed()` | 6 percobaan, jeda 10 dtk – 15 mnt; 4xx langsung gagal; 429 menunggu 60 dtk |
| Catat sinkron / push order | `SyncLog::track(SyncType::…, SyncTrigger::…, fn ($log) => $jumlahItem, referenceId: $order->id)` | Pesan error Jubelio tersimpan apa adanya |
| Endpoint webhook WH-xx | Controller: `return $receiver->jubelio($request, 'update-qty', [aturan validasi]);` atau `$receiver->gapura($request, $gapura)`; logika di kelas `WebhookHandler` yang didaftarkan di `config/webhooks.php` | Lempar `WebhookEventIgnored` untuk `ignored`, `WebhookEventRejected` untuk `failed` tanpa retry |
| Fixture test | `fixture('jubelio/….json')`, `fixtureContent()` untuk body mentah webhook | `Http::preventStrayRequests()` aktif di semua test |

WH-02 – WH-04 (Jubelio) dan WH-01 (Gapura) sudah aktif penuh: WH-01 memverifikasi tanda tangan, menyimpan, membalas `2005600`, lalu `GapuraPaymentNotified` (terdaftar di `config/webhooks.php`) menandai order lunas atau kedaluwarsa. WH-05 (Jubelio, nomor resi dan status dikirim) **ditunda** dan tetap `501`, sampai diputuskan apakah pengiriman memakai Jubelio Shipment.

**Blocker / pertanyaan terbuka.**

- ~~Nama header signature webhook Jubelio~~ Terjawab dari `api/dist.yaml`: header `sign`, HMAC-SHA256. Tetap dipastikan dengan webhook asli pertama di Fase 2.
- ~~Toko internal~~ Sudah dibuat: "Website", `store_id` -102. Toko internal hanya muncul di `GET /locations/store/`, bukan di `/marketplace/store/`.

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

**Keputusan teknis di fase ini.**

- Sanitasi HTML memakai `ezyang/htmlpurifier` (`App\Support\HtmlSanitizer`); Deskripsi asli Jubelio sudah berupa HTML (`<p>`, `<strong>`, `<br>`), jadi tidak perlu konversi teks biasa.
- Bundle tidak ada di `/inventory/items/`; sinkron penuh dan inkremental juga membaca `/inventory/item-bundles/` (bentuk data sama). Stok bundle dibaca lewat INT-JB-06 seperti SKU biasa.
- Nama, berat, dan penanda bundle per SKU diambil dari INT-JB-05 (`/variations/`), karena `product_skus` di INT-JB-04 tidak memuatnya.
- Handler WH-02 – WH-04 (`App\Services\Webhooks\Handlers`) memanggil `ProductSyncService` / `StockSyncService` langsung, agar status `webhook_events` mencerminkan hasil sinkron. `SyncProductGroupJob` dan `SyncStockJob` (nama dari kontrak) adalah job tipis di atas service yang sama, untuk sinkron di latar belakang seperti ADM-13.
- Batas waktu sinkron inkremental disimpan di cache (`jubelio:products:listed-at`), bukan dibaca dari `sync_logs`, karena sinkron satu produk dari webhook juga menulis `sync_logs` dan akan menggeser batasnya. Bila cache kosong, sinkron inkremental berjalan penuh.

**Definisi selesai.**

- [x] Sinkron penuh dengan fixture mengisi `categories`, `products`, `product_variants`, `product_images` sesuai pemetaan di `integrations.md`.
- [x] Produk dan varian yang hilang atau nonaktif di Jubelio menjadi `is_active = false`, tidak dihapus, dan hilang dari CAT-02/CAT-03.
- [x] Feature test CAT-01–03 untuk setiap Acceptance Criteria, termasuk respons CAT-02 < 500 ms untuk 48 item.
- [x] Test WH-02–04, termasuk event ganda yang tidak diproses dua kali dan stok dari gudang lain yang diabaikan.
- [x] Sinkron dijalankan sekali terhadap akun Jubelio asli, dan jumlah produk aktif cocok dengan dashboard Jubelio. 15 Okt 2026: 4 kategori, 9 produk (8 item + 1 bundle), 30 varian, 30 gambar; sama dengan `/inventory/items/` + `/inventory/item-bundles/` dan `/variations/`.

**Blocker / risiko.**

- ~~Berat per varian mungkin tidak didukung Jubelio~~ Terjawab 15 Okt 2026: didukung.
- Harga per toko memakai `JUBELIO_STORE_ID` (-102). Di akun asli `prices[]` masih kosong, jadi harga jatuh ke `sell_price`.
- **Idempotency key WH-02 – WH-04 = SHA256 body.** Body `update-qty` hanya berisi `item_ids` dan `location_id`, sehingga dua perubahan stok berbeda untuk item yang sama menghasilkan key yang sama, dan yang kedua dianggap duplikat. Stok tetap terkoreksi oleh rekonsiliasi 15 menit (kriteria rilis PRD terpenuhi), tetapi target "1 menit" WH-04 tidak terjamin untuk perubahan berulang. Perlu dicek dengan webhook asli pertama (apakah body memuat penanda waktu); bila tidak, kontrak `webhooks.md` perlu direvisi di `development`.

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

**Selesai di Fase 4.** AUTH-06 mengantrekan `UpdateJubelioContactJob` (INT-JB-09) bila nama atau nomor berubah dan user sudah punya `jubelio_contact_id`. `jubelio_contact_id` baru terisi saat checkout pertama, jadi bagian ini dibangun bersama INT-JB-09.

**Definisi selesai.**

- [x] Feature test setiap Acceptance Criteria, termasuk akses ke alamat atau item keranjang milik user lain (dibalas 404, bukan 403, agar ID tidak bocor).
- [x] Rate limit `auth` tetap per endpoint (test yang sudah ada tetap lulus).
- [x] Field yang dibaca store frontend (`data.role`, `data.total_qty`, `data.has_issue`, `data.summary.*`) muncul di response asli, bukan hanya di mock. Diuji lewat server HTTP dengan alur `client.js` (CSRF asli, cookie session, remember-me) dan dibandingkan dengan `contract.json`, 15 Okt 2026.
- [x] REG-01 – REG-04 dan ADDR-01 – ADDR-06 diuji sekali terhadap Jubelio asli lewat server HTTP (15 Okt 2026): rantai Jawa Barat → Kab. Bekasi → Tambun Utara → Satriajaya, nama wilayah terisi dari Jubelio. Panggilan pertama REG-01 ±4 detik (login + ambil data Jubelio), berikutnya dari cache.

**Catatan implementasi.**

- Feature test Laravel melewati pemeriksaan CSRF; alur CSRF + session hanya terbukti lewat uji HTTP di atas. Helper test `fromFrontend()` mengirim `Referer` dan cookie (`withCredentials()`) seperti browser; tanpa itu Sanctum tidak memulai session dan cookie tidak terkirim.
- Login memakai guard `web` (Sanctum stateful). `remember = true` = cookie 30 hari (`config/auth.php` guard `web.remember`).
- AUTH-07 / AUTH-09 mengeluarkan session lain dengan menghapus baris tabel `sessions` (`SESSION_DRIVER=database`) dan merotasi `remember_token`. Driver session lain tidak bisa mendaftar session per user, jadi wajib `database` di semua lingkungan.
- Email reset (AUTH-08) memakai `ResetPasswordNotification` (Bahasa Indonesia, lewat queue); butuh queue worker yang berjalan.
- Daftar wilayah di-cache "selamanya" dengan cap waktu: segar 30 hari, lalu diambil ulang; bila Jubelio gagal, daftar lama tetap dipakai (REG-01). Kelurahan Jubelio tidak punya kode pos, jadi `post_code` REG-04 selalu `null`.
- `summary.subtotal` dan `summary.total_weight` CART-01 tidak menghitung item `PRODUCT_INACTIVE` (berat ini dipakai SHIP-01).

**Merge.** Auth bisa di-merge lebih dulu bersama halaman masuk/daftar; keranjang dan alamat menyusul bersama halamannya.

---

## Fase 4 — Ongkir & checkout

> **Status: selesai (20 Okt 2026).** Ongkir masih driver `stub` (tarif contoh per zona dan berat, `StubRates`); ganti driver bila Jubelio Shipment atau penyedia lain sudah dipastikan. Checkout nyata ke Jubelio dan Gapura sandbox sudah dicoba (order `WEB-000001`–`000003`).
> **Penyimpangan dari rencana:** `INT-JB-07` sengaja tidak dipakai; sales order memakai snapshot `order_items` (unit, harga, berat) dan `config('jubelio.tax_id')`. Jubelio asli meminta semua id wilayah kontak (`s_province_id`, `s_city_id`, `s_subdistrict_id`) berupa teks.

**Tujuan.** Customer melihat tarif kurir, lalu menekan Bayar. Website mengecek ulang stok ke Jubelio, membuat sales order belum bayar di toko "Website Saeful Askar", membuat order Gapura, dan mengarahkan customer ke halaman bayar.

**Cakupan.**

| Kelompok | Isi | Acuan |
| --- | --- | --- |
| Ongkir | SHIP-01 lewat INT-JS-01 (driver `stub` mengembalikan 2–3 layanan contoh sampai dokumentasi ada). Berat = Σ `qty × package_weight`, tanpa item `PRODUCT_INACTIVE`. Asal selalu gudang dari config. Tiap rate disimpan di cache dengan `rate_id` selama 30 menit (user, alamat, berat, kurir, layanan, ongkir). Alamat + berat sama dalam 5 menit diambil dari cache. Error: `CART_EMPTY`, `NO_SHIPPING_SERVICE`, `ADDRESS_NOT_FOUND`, `502 INTEGRATION_ERROR` | SHIP-01, INT-JS-01 |
| Pencatat status | Satu service yang mencatat setiap perubahan status ke `order_status_histories` beserta sumbernya. Dipakai ORD-01 dan diperluas di Fase 5 | `api/README.md` |
| Contact Jubelio | Cari contact berdasarkan email dulu, baru buat bila tidak ada; simpan `jubelio_contact_id` sekali. Sekaligus: job update contact dari AUTH-06 | INT-JB-09 |
| Sales order | Data item dari INT-JB-07 (di-cache 1 jam per `item_id`). SO dengan `ref_no` = `order_number`, `store_id`, `location_id`, `tax_id` No Tax, ongkir, dan `shipper`. Tidak pernah ada dua SO dengan `ref_no` sama (dicek lewat INT-JB-13, cocok persis) | INT-JB-07, INT-JB-10, INT-JB-13 |
| Order Gapura | `partnerReferenceNo = order_number`, `amount = grand_total` (string dua desimal), `validUpTo` dari `GAPURA_PAYMENT_DURATION` (sandbox maks 30 menit dan Rp10.000.000), `urlParams` NOTIFICATION dan PAY_RETURN; `referenceNo`, `webRedirectUrl`, dan `validUpTo` disimpan ke `payments` | INT-GP-01 |
| Checkout | ORD-01 lewat `CheckoutService`, 9 langkah sesuai kontrak: validasi (`CART_EMPTY`, `CART_HAS_ISSUES`, `PROFILE_INCOMPLETE`, `ADDRESS_NOT_FOUND`) → rate dari cache (`RATE_EXPIRED`) → hitung ulang total (`PRICE_CHANGED`) → cek stok langsung ke Jubelio (`OUT_OF_STOCK`) → contact → simpan order dalam satu transaksi (`jubelio_sync_status = pending`) → SO → order Gapura → kosongkan keranjang, balas 201 | ORD-01 |
| Gagal di tengah checkout | SO gagal → order `cancelled` ("Gagal membuat pesanan di Jubelio"), keranjang **tidak** dikosongkan, balas 502. Order Gapura gagal → batalkan SO (INT-JB-12), order `cancelled`, keranjang tidak dikosongkan, balas 502 | ORD-01, INT-JB-12 |
| Request ganda | `Idempotency-Key`: request kedua setelah sukses mengembalikan response 201 yang sama persis (disimpan 24 jam). Key yang sama saat request pertama masih diproses → `409 DUPLICATE_REQUEST`. Dua klik Bayar bersamaan tidak pernah menghasilkan dua SO | ORD-01 |

**Definisi selesai.**

- [ ] Test ORD-01 untuk setiap kode error dan setiap langkah gagal di kontrak.
- [ ] Test dua request bersamaan dan dua request dengan `Idempotency-Key` sama: hanya satu order, satu SO, satu order Gapura.
- [ ] `grand_total` order = `amount` order Gapura = `grand_total` sales order Jubelio; `order_number` berurutan dan unik.
- [ ] Satu checkout uji ke akun Jubelio asli: SO muncul di toko "Website Saeful Askar" dengan status belum bayar, item, alamat, kurir, ongkir, dan `ref_no` yang benar.

**Blocker / risiko.**

| Hal | Dampak ke fase ini | Penanganan sementara |
| --- | --- | --- |
| Dokumentasi rates + kredensial Jubelio Shipment | Ongkir asli belum bisa dipakai | Driver `stub`; cukup ganti driver saat dokumen ada |
| Durasi batas bayar Gapura | `validUpTo` order | `GAPURA_PAYMENT_DURATION=600` (10 menit, diputuskan 20 Okt 2026) |
| Metode pembayaran Gapura | Metode yang aktif di akun DANA | Diatur di Merchant Portal DANA |
| ~~`store_id` toko internal~~ | — | Selesai: "Website" `-102` (Fase 1) |
| SO belum bayar mungkin tidak mengurangi stok tersedia | Oversell dengan Shopee | Diuji di akun asli (INT-JB-06); bila terjadi, tambah reservasi stok (`POST /inventory/reserved/`) |

**Merge.** Bersama halaman checkout. Halaman "lanjut bayar" menunggu ORD-04 di Fase 5.

---

## Fase 5 — Pembayaran & siklus pesanan

> **Status: selesai kecuali WH-05 (20 Okt 2026).** WH-01, `orders:expire-unpaid`, ORD-02–05, `OrderPaymentService`, `OrderCancellationService`, dan email "Pembayaran diterima" / "Pesanan dibatalkan" sudah jalan; pembayaran sandbox (BRI VA) menandai order lunas di website dan Jubelio. **Ditunda:** WH-05 (nomor resi, status dikirim/selesai) dan email "Pesanan dikirim" (template `OrderShippedMail` sudah ada, belum dipanggil).
> **Catatan sandbox DANA:** batas bayar dipotong jadi 30 menit; metode "DANA" (dompet) butuh akun uji, jadi uji dengan Virtual Account. Pembayaran VA disimulasikan lewat menu uji di dashboard DANA sandbox.

**Tujuan.** Status order selalu mengikuti Gapura dan Jubelio. Order lunas diteruskan ke gudang; order yang kedaluwarsa atau dibatalkan mengembalikan stok; customer bisa melihat riwayat, status, dan nomor resi.

**Cakupan.**

| Kelompok | Isi | Acuan |
| --- | --- | --- |
| Transisi status | Service Fase 4 diperluas agar menolak transisi yang tidak sah (README "Transisi yang sah"); status Jubelio hanya bergerak maju | `api/README.md`, WH-05 |
| Alur bayar | `00`/`02` pada `pending_payment`: simpan data pembayaran, order → `paid` (`source = gapura`), `MarkSalesOrderPaidJob` → INT-JB-11 (retry 5 kali, lalu `jubelio_sync_status = failed`), email "Pembayaran diterima". `amount` ≠ `payments.amount` → order tidak diubah, event `failed` + log `warning`. Dibayar setelah `expired`/`cancelled` → order tidak diubah, event `failed` "perlu refund/proses manual" | WH-01, INT-JB-11 |
| Alur kedaluwarsa | `05` pada `pending_payment`: order → `expired` ("Batas waktu pembayaran habis"), `CancelSalesOrderJob` → INT-JB-12, email "Pesanan dibatalkan". Idempotency key `gapura:{order_number}:{status}`; `originalPartnerReferenceNo` tak dikenal → `ignored` | WH-01, INT-JB-12 |
| Job cadangan | `ExpireUnpaidOrders` (`orders:expire-unpaid`, tiap 5 menit): untuk order yang lewat `payment_expires_at`, pastikan status ke DANA (INT-GP-02) lalu jalankan alur yang sesuai | WH-01, INT-GP-02 |
| Webhook sales order | Hanya toko website (lainnya `ignored`); order dicari lewat `salesorder_id`, cadangan `ref_no` (tidak ketemu → `failed`); `last_modified` lebih lama → `ignored`. Pemetaan `is_canceled` / `completed` / `shipped` / `processing` sesuai tabel kontrak; simpan `tracking_no`, kurir, `salesorder_no`; email "Pesanan dikirim" saat pertama kali `shipped` | WH-05 |
| Riwayat pesanan | ORD-02: filter `status` (dipisah koma), paginasi default 10; order yang batal karena gagal dibuat di Jubelio tidak ditampilkan. ORD-03: OrderDetail lewat `OrderPolicy` (milik user lain → 404 `ORDER_NOT_FOUND`); `checkout_url` hanya saat `pending_payment` dan belum lewat batas | ORD-02, ORD-03 |
| Lanjut bayar | ORD-04: mengembalikan halaman bayar yang ada, tidak membuat order baru. Bila batas lewat, cek ke DANA (INT-GP-02) lalu jalankan alur kedaluwarsa atau alur bayar dan balas 409 (`ORDER_NOT_PAYABLE`) | ORD-04 |
| Pembatalan customer | ORD-05: batalkan order Gapura (INT-GP-03) **sebelum** membatalkan SO (INT-JB-12) → `cancelled`. Order sudah dibayar → tidak dibatalkan, jalankan alur bayar, balas 409. Pembatalan Jubelio gagal → order tetap `cancelled`, `jubelio_sync_status = failed`, dicatat di `sync_logs`, customer tetap dapat 200. Selain `pending_payment` → `ORDER_NOT_CANCELLABLE`. Notifikasi `05` yang datang kemudian diabaikan dengan aman | ORD-05, INT-XD-03 |

**Catatan email.** Kontrak mewajibkan tiga email di fase ini (pembayaran diterima, pesanan dibatalkan, pesanan dikirim), semuanya lewat queue. Email "konfirmasi order" saat checkout ada di PRD F-18 (Sebaiknya) tetapi belum ada di kontrak, jadi tidak termasuk definisi selesai.

**Definisi selesai.**

- [ ] Test webhook PAID, EXPIRED, update-salesorder, dan idempotensi memakai fixture (PRD §5 "Pengujian").
- [ ] Test urutan event yang tidak wajar: dibayar setelah dibatalkan, jumlah bayar berbeda, event Jubelio yang lebih lama, event yang akan memundurkan status. Semuanya tidak mengubah order, dan webhook tetap dibalas `200`.
- [ ] Test ORD-04 dan ORD-05 saat webhook terlambat (DANA sudah `PAID` atau `EXPIRED`).
- [ ] Pembayaran uji Gapura (sandbox) menandai order lunas di website dan Jubelio; order Gapura yang kedaluwarsa membatalkan SO dan stok kembali.

**Merge.** Bersama halaman riwayat, detail pesanan, dan lanjut bayar.

---

## Fase 6 — Panel admin

> **Status API: selesai (20 Okt 2026).** ADM-09 – ADM-14, job `PushOrderToJubelioJob` / `RunManualSyncJob`, dan `sync-logs:prune` (harian) sudah jalan dan teruji. Tambahan di luar kontrak awal: `ORDER_HAS_NO_PAYMENT` (409) di ADM-12. ADM-11 `create` menolak order yang dibatalkan atau checkout-nya gagal, dan untuk order yang sudah lunas langsung disusul `mark_paid`. **Belum:** halaman admin di frontend (`/admin/orders`, dll).

**Tujuan.** Admin bisa memantau order website beserta status sinkron Jubelio, mengirim ulang order yang gagal, dan menjalankan sinkron manual.

**Cakupan.**

| Kelompok | Endpoint | Catatan |
| --- | --- | --- |
| Order admin | ADM-09 – ADM-12 | ADM-09 termasuk order yang batal karena gagal dibuat di Jubelio, urut terbaru. ADM-10 memuat `sync_logs` order itu, tanpa kredensial. ADM-11 mencari SO lewat `ref_no` dulu, lalu memilih otomatis `create` / `mark_paid` / `cancel` (`ALREADY_SYNCED` bila aksi yang diminta sudah tercermin di Jubelio). ADM-12 memanggil INT-GP-02 dan menjalankan alur WH-01; aman dipanggil berkali-kali |
| Sinkron manual | ADM-13, ADM-14 | Satu sinkron per jenis lewat atomic lock (`SYNC_ALREADY_RUNNING`); `full` = kategori → produk → stok, masing-masing satu baris `sync_logs`. ADM-14 urut `started_at` terbaru; `sync-logs:prune` menghapus log > 90 hari |

**Urutan.** ADM-13–14 menunggu Fase 2; ADM-09–12 menunggu Fase 5.

**Definisi selesai.**

- [ ] Feature test setiap Acceptance Criteria, termasuk customer biasa yang mendapat `403 FORBIDDEN`.
- [ ] Order yang SO-nya gagal bisa dikirim ulang dari ADM-11 tanpa membuat SO ganda.


**Merge.** Bersama halaman admin.

---

## Fase 7 — Uji terpadu & rilis

**Tujuan.** Memastikan kriteria rilis PRD §8 terpenuhi dengan akun Jubelio dan Gapura mode sandbox, lalu menyiapkan produksi.

**Cakupan.**

- Test penjaga: tidak ada lagi `NotImplementedException` di controller v1 (kode `501` tidak boleh ada saat rilis).
- Uji end-to-end dengan akun asli (mode test): katalog, checkout, bayar, kedaluwarsa, batal, resi. Nilai pemetaan WH-05 dipastikan dengan contoh webhook asli.
- Ukur kinerja API katalog (< 500 ms) dengan data asli; tambah index atau eager loading bila perlu.
- Produksi: OPcache aktif dan `php artisan optimize` (config/route/view cache) — tanpa keduanya, memuat Laravel saja makan ±240 ms per request di lokal Windows (diukur 15 Okt 2026), cron `php artisan schedule:run` tiap menit, queue worker yang selalu hidup, `APP_DEBUG=false`, cookie session `Secure` (AUTH-01), Gapura mode `production` (kunci produksi, public key produksi DANA, `GAPURA_NOTIFY_URL` ke domain produksi), kredensial hanya di `.env`, `ADMIN_EMAIL`/`ADMIN_PASSWORD` diisi, URL webhook didaftarkan ke domain produksi (`jubelio:setup`), backup database.

**Definisi selesai = kriteria rilis PRD §8.**

- [ ] Seluruh produk aktif di Jubelio tampil di website dengan harga, stok, gambar, dan berat yang sama.
- [ ] Perubahan stok di Jubelio (termasuk dari order Shopee) tampil di website dalam 15 menit.
- [ ] Order test muncul di Jubelio pada toko "Website Saeful Askar" dengan item, alamat, kurir, dan ongkir yang benar.
- [ ] Pembayaran uji Gapura menandai order lunas di website dan Jubelio.
- [ ] Order Gapura yang kedaluwarsa membatalkan sales order Jubelio dan stok kembali.
- [ ] Nomor resi dari Jubelio tampil di halaman pesanan customer.
- [ ] Webhook yang dikirim ulang tidak membuat order atau perubahan ganda.
- [ ] Semua feature test lulus.

---

## Pertanyaan terbuka per fase

Diambil dari PRD §9 dan `integrations.md`. Kolom kedua menunjukkan fase yang paling cepat terhambat.

| Pertanyaan | Dibutuhkan di | Bisa jalan dulu dengan |
| --- | --- | --- |
| ~~Toko internal + `store_id`~~ | — | Terjawab: "Website" `-102` |
| ~~Nama header signature webhook Jubelio~~ | — | Terjawab: `sign`, HMAC-SHA256 (`api/dist.yaml`); dipastikan dengan webhook asli pertama |
| Dokumentasi rates + kredensial Jubelio Shipment | Fase 4 | Driver `stub` |
| ~~Durasi batas bayar Gapura~~ | Fase 4 | Selesai: 10 menit (`GAPURA_PAYMENT_DURATION=600`) |
| Metode pembayaran Gapura | Fase 4 | Diatur di Merchant Portal DANA |
| Harga promo Jubelio (`promotion_price`) ikut dipakai atau tidak | Fase 4 | Versi 1 memakai harga INT-JB-04 (INT-JB-07) |
