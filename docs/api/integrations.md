# Integrasi Keluar (Backend → Jubelio, Jubelio Shipment, Gapura)

File ini mendokumentasikan panggilan yang dilakukan **backend** ke layanan luar. Frontend tidak pernah memanggil layanan ini langsung. Semua panggilan hanya boleh lewat kelas client:

| Layanan | Client | Base URL |
| --- | --- | --- |
| Jubelio Omnichannel | `App\Services\Jubelio\JubelioClient` | `https://api2.jubelio.com` |
| Jubelio Shipment | `App\Services\JubelioShipment\JubelioShipmentClient` | Menunggu dokumentasi dari Jubelio |
| Gapura (DANA) | `App\Services\Gapura\GapuraClient` (membungkus SDK `danaid/dana-php`) | Sandbox `https://api.sandbox.dana.id`, produksi `https://api.saas.dana.id` |

Field Jubelio di bawah diambil dari spec OpenAPI resmi Jubelio (`dist.yaml`, "Jubelio API Reference"). Tanda **\*** = wajib menurut spec. Field Gapura diambil dari dokumentasi API DANA dan SDK resmi `danaid/dana-php`.

---

## INT-HDL · Aturan umum semua panggilan keluar

### Endpoint

Berlaku untuk semua INT-JB, INT-JS, dan INT-GP.

### User Story

Sebagai backend, saya ingin semua panggilan ke layanan luar punya timeout, retry, dan log yang seragam, agar gangguan sementara tidak merusak data dan masalah mudah ditelusuri.

### Request Body

Tidak berlaku (aturan umum).

### Response Success

HTTP 2xx dari layanan luar → hasil dipetakan ke model/DTO internal.

### Response Failed

| Kondisi | Penanganan |
| --- | --- |
| Timeout (Jubelio 15 detik, Jubelio Shipment 10 detik, Gapura 8 detik) | Lempar `JubelioApiException` / `IntegrationException`; job di-retry |
| Jubelio 401 / token kedaluwarsa | Login ulang sekali (INT-JB-01), ulangi request sekali |
| Jubelio 429 | Tunggu 60 detik, ulangi; job sinkron menahan laju < 600 request/menit |
| Jubelio 500 dengan `code` SQLSTATE (mis. `23505`) | Simpan pesan apa adanya di `sync_logs.message`; untuk sales order, cek dulu apakah SO dengan `ref_no` sama sudah ada sebelum retry |
| Gapura 4xx | Tidak di-retry; catat `responseCode` dan `responseMessage` dari DANA. Gapura 429 dan 5xx di-retry dengan data yang sama (idempoten lewat `partnerReferenceNo`) |
| 5xx lain | Retry di queue: 5 kali, jeda 10 dtk, 30 dtk, 2 mnt, 5 mnt, 15 mnt |

### Acceptance Criteria

- [ ] Hanya kelas client yang memakai HTTP client ke domain luar (dicek di code review).
- [ ] Setiap panggilan dicatat (method, path, status, durasi) di log aplikasi; body yang berisi data pribadi/kredensial tidak dicatat.
- [ ] Panggilan di dalam request customer (checkout, ongkir, region) tidak di-retry lebih dari sekali agar customer tidak menunggu lama; sisanya lewat queue.
- [ ] Semua client punya fake/fixture untuk test (`tests/Fixtures/jubelio`, `tests/Fixtures/gapura`); SDK DANA dites dengan Guzzle mock handler.

---

## INT-JB-01 · Login Jubelio

### Endpoint

`POST https://api2.jubelio.com/login`

### User Story

Sebagai backend, saya ingin mendapat token Jubelio dan menyimpannya selama masa berlaku, agar semua panggilan Jubelio terautentikasi tanpa login berulang.

### Request Body

| Field | Tipe | Wajib | Sumber |
| --- | --- | --- | --- |
| `email`\* | string | Ya | `JUBELIO_EMAIL` |
| `password`\* | string | Ya | `JUBELIO_PASSWORD` |

```json
{
  "email": "<JUBELIO_EMAIL>",
  "password": "<JUBELIO_PASSWORD>"
}
```

### Response Success

`200 OK` (dipotong ke field yang dipakai)

```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9…",
  "userName": "owner@saefulaskar.id",
  "currentCompany": 10
}
```

### Response Failed

| HTTP | Isi | Penanganan |
| --- | --- | --- |
| 500 | `{"statusCode":500,"error":"Internal Server Error","message":"…","code":"Invalid email or password"}` | Log error dengan level `critical`, jangan retry terus-menerus (indikator `login_ok` di ADM-17 menyusul di v1.1) |

### Acceptance Criteria

- [ ] Token disimpan di cache Laravel (driver `database`) dengan TTL 11 jam 30 menit (token berlaku 12 jam) oleh `JubelioTokenManager`.
- [ ] Token dikirim di header `authorization: <token>` pada semua panggilan Jubelio lain.
- [ ] Hanya satu proses yang login bersamaan (lock), proses lain menunggu token baru.

---

## INT-JB-02 · Ambil kategori

### Endpoint

`GET https://api2.jubelio.com/inventory/categories/item-categories/`

### User Story

Sebagai backend, saya ingin menyalin pohon kategori Jubelio, agar filter kategori di website sama dengan Jubelio.

### Request Body

Tidak ada. Header `authorization`\*.

### Response Success

`200 OK`

```json
{
  "data": [
    { "category_id": 3, "category_name": "Sepeda Listrik", "parent_id": 0, "has_children": true },
    { "category_id": 9, "category_name": "Sepeda Listrik Lipat", "parent_id": 3, "has_children": false }
  ]
}
```

Pemetaan: `category_id` → `categories.jubelio_category_id`, `category_name` → `name`, `parent_id` (0 = akar) → `parent_id` lokal.

### Response Failed

Lihat INT-HDL.

### Acceptance Criteria

- [ ] Dijalankan oleh `jubelio:sync-categories` (terjadwal tiap malam + ADM-13).
- [ ] Kategori yang hilang di Jubelio tidak dihapus bila masih dipakai produk; ditandai tidak dipakai.
- [ ] Slug dibuat sekali dan tidak berubah saat nama berubah.

---

## INT-JB-03 · Daftar produk (item group + varian)

### Endpoint

`GET https://api2.jubelio.com/inventory/items/?page={n}&pageSize=200`

| Query | Wajib | Nilai |
| --- | --- | --- |
| `page`\* | Ya | 1, 2, … sampai `totalCount` habis |
| `pageSize`\* | Ya | 200 (maks) |
| `sortBy`, `sortDirection` | Tidak | `last_modified`, `DESC` untuk sinkron inkremental |

### User Story

Sebagai backend, saya ingin mengambil semua produk beserta varian, harga, dan stok tersedia, agar katalog website sama persis dengan Jubelio.

### Request Body

Tidak ada. Header `authorization`\*.

### Response Success

`200 OK` (dipotong)

```json
{
  "data": [
    {
      "item_group_id": 1931,
      "item_name": "Sepeda Listrik Uwinfly C1 Pro",
      "item_category_id": 3,
      "last_modified": "2026-10-14T13:15:00.000Z",
      "thumbnail": "https://jubelio.blob.core.windows.net/images/abc/thumb.jpg",
      "variations": [{ "label": "Warna", "values": ["Hitam", "Putih"] }],
      "variants": [
        {
          "item_id": 2976,
          "item_code": "UWF-C1PRO-HTM",
          "item_name": "Sepeda Listrik Uwinfly C1 Pro - Hitam",
          "sell_price": 6750000,
          "end_qty": 5,
          "order_qty": 1,
          "available_qty": 4,
          "variation_values": [{ "label": "Warna", "value": "Hitam" }],
          "is_bundle": false,
          "thumbnail": "https://jubelio.blob.core.windows.net/images/abc/hitam.jpg",
          "barcode": null
        }
      ]
    }
  ],
  "totalCount": 25
}
```

Pemetaan utama: `item_group_id` → `products.jubelio_item_group_id`; `variants[].item_id` → `product_variants.jubelio_item_id`; `item_code` → `sku`; `sell_price` → `price`; `available_qty` → `available_qty`.

### Response Failed

Lihat INT-HDL.

### Acceptance Criteria

- [ ] Sinkron penuh membaca semua halaman sampai jumlah item = `totalCount`.
- [ ] Sinkron inkremental berhenti saat bertemu `last_modified` ≤ waktu sinkron terakhir.
- [ ] Setiap item group yang berubah diteruskan ke INT-JB-04 untuk detail lengkap.
- [ ] Item `is_bundle = true` ikut disinkron seperti produk biasa (stoknya dihitung Jubelio).

---

## INT-JB-04 · Detail item group

### Endpoint

`GET https://api2.jubelio.com/inventory/items/group/{item_group_id}`

### User Story

Sebagai backend, saya ingin mengambil deskripsi, gambar, kategori, brand, dimensi paket, min/max pembelian, dan harga per toko, agar halaman detail produk lengkap dan sama dengan Jubelio.

### Request Body

Tidak ada. Header `authorization`\*.

### Response Success

`200 OK` (dipotong)

```json
{
  "item_group_id": 1931,
  "item_group_name": "Sepeda Listrik Uwinfly C1 Pro",
  "description": "<p>Motor 500W, baterai 48V 12Ah, jarak tempuh hingga 45 km.</p>",
  "item_category_id": 3,
  "brand_name": "Uwinfly",
  "sell_unit": "Unit",
  "is_active": true,
  "sell_this": true,
  "package_weight": "32000",
  "package_length": "150",
  "package_width": "30",
  "package_height": "80",
  "min": 1,
  "max": 2,
  "last_modified": "2026-10-14T13:15:00.000Z",
  "variations": [{ "label": "Warna", "values": ["Hitam", "Putih"] }],
  "product_skus": [
    {
      "item_id": 2976,
      "item_code": "UWF-C1PRO-HTM",
      "sell_price": 6750000,
      "end_qty": 5,
      "barcode": null,
      "variation_values": [{ "label": "Warna", "value": "Hitam" }],
      "prices": [{ "store_id": "-1043", "channel_id": 1, "sell_price": 6750000 }],
      "images": [
        { "image_id": 102, "cloud_key": "https://jubelio.blob.core.windows.net/images/abc/hitam.jpg", "thumbnail": "https://jubelio.blob.core.windows.net/images/abc/hitam-thumb.jpg", "sequence_number": 0 }
      ]
    }
  ]
}
```

### Response Failed

| HTTP | Penanganan |
| --- | --- |
| 404 / data kosong | Produk dihapus di Jubelio → `products.is_active = false` |
| Lainnya | Lihat INT-HDL |

### Acceptance Criteria

- [ ] `is_active = false` atau `sell_this = false` → produk tidak tampil di website.
- [ ] Harga: `prices[]` dengan `store_id = JUBELIO_STORE_ID` bila ada, selain itu `sell_price` SKU.
- [ ] `description` disanitasi lalu disimpan.
- [ ] Angka string (`package_weight`, dimensi) dikonversi ke integer. Nilainya berupa desimal (`"200.0000"`), jadi dibulatkan dulu, bukan langsung di-cast.
- [ ] `min` / `max` bernilai `0` berarti tanpa batas → disimpan `null`. Bila tidak, `max_qty = 0` memblokir semua pembelian.

**Hasil uji akun asli (15 Okt 2026).** `prices[]` kosong di seluruh 8 item group, jadi harga jatuh ke `sell_price`. Ini tetap benar sampai toko memasang harga khusus "Website" di Jubelio. Pada 8 item group itu `min = 0` dan `max = 0`, `brand_name = null`, dan `package_weight` berupa string desimal.

---

## INT-JB-05 · Berat per varian

### Endpoint

`GET https://api2.jubelio.com/variations/?page={n}&pageSize=200`

Garis miring di akhir `/variations/` **wajib**: tanpa garis miring Jubelio membalas 404, walau `dist.yaml` menulis `/variations` (diuji 15 Okt 2026).

### User Story

Sebagai backend, saya ingin mengambil berat paket per SKU, agar ongkir baterai dengan kapasitas berbeda dihitung dari berat varian yang benar.

### Request Body

Tidak ada. Header `authorization`\*.

### Response Success

`200 OK` (dipotong)

```json
{
  "data": [
    { "item_group_id": 1940, "item_id": 3001, "item_code": "BAT-48V-12AH", "package_weight": "6500.0000", "is_variant": true },
    { "item_group_id": 1940, "item_id": 3002, "item_code": "BAT-48V-20AH", "package_weight": "9500.0000", "is_variant": true },
    { "item_group_id": 1941, "item_id": 3010, "item_code": "SOKET-M23", "package_weight": null, "is_variant": false }
  ],
  "totalCount": 52
}
```

### Response Failed

Lihat INT-HDL.

### Acceptance Criteria

- [ ] `package_weight` → `product_variants.package_weight` (gram) dicocokkan lewat `item_id`. Nilainya string desimal (`"6500.0000"`) atau `null`; dibulatkan ke integer.
- [ ] Bila `package_weight` per SKU `null`/0, pakai `package_weight` item group (INT-JB-04).
- [x] **Diuji di akun Jubelio (15 Okt 2026)**: berat per varian didukung dan dipakai. Contoh: Baterai Lithium Phylion punya 10 varian 3.000–27.500 g. `weight_in_gram` di INT-JB-07 sama dengan `package_weight` di sini untuk 29 dari 30 SKU; satu SKU dengan `package_weight = null` sudah diisi cadangan oleh Jubelio di INT-JB-07.

---

## INT-JB-06 · Stok per gudang

### Endpoint

`GET https://api2.jubelio.com/inventory/?page=1&pageSize=200&q={item_code}`

### User Story

Sebagai backend, saya ingin mengecek stok tersedia di gudang Tambun Utara untuk SKU tertentu, agar stok website akurat dan checkout tidak menjual barang yang sudah terjual di Shopee.

### Request Body

Tidak ada. Header `authorization`\*. Untuk rekonsiliasi semua stok, `q` dikosongkan dan semua halaman dibaca.

### Response Success

`200 OK` (dipotong)

```json
{
  "data": [
    {
      "item_id": 2976,
      "item_code": "UWF-C1PRO-HTM",
      "item_group_id": 1931,
      "location_stocks": [
        { "item_id": 2976, "location_id": 0, "on_hand": 0, "on_order": 0, "reserved": 0, "available": 0 },
        { "item_id": 2976, "location_id": -1, "on_hand": 5, "on_order": 0, "reserved": 1, "available": 4 }
      ],
      "total_stocks": { "on_hand": 5, "on_order": 0, "reserved": 1, "available": 4 }
    }
  ]
}
```

### Response Failed

Lihat INT-HDL. Saat checkout (ORD-01) kegagalan dibalas `502 INTEGRATION_ERROR` ke customer.

### Acceptance Criteria

- [ ] Stok yang dipakai = `location_stocks[].available` dengan `location_id = JUBELIO_LOCATION_ID` (-1). Baris `location_id = 0` juga muncul di akun asli dan diabaikan.
- [ ] `item_id` dan `location_id` dibandingkan setelah dikonversi ke integer: di akun asli keduanya integer, sedangkan contoh lama di spec menulis string.
- [ ] Hasil cocok persis berdasarkan `item_code` (pencarian `q` bisa mengembalikan SKU mirip).
- [ ] **Diuji di akun Jubelio**: sales order belum bayar (INT-JB-10) mengurangi `available`. Bila tidak, tambah reservasi stok (`POST /inventory/reserved/`).

---

## INT-JB-07 · Data item untuk sales order

> **Tidak dipakai di v1 (20 Okt 2026).** Sales order dibuat dari snapshot `order_items` (unit, harga, berat) dan `config('jubelio.tax_id')`, jadi panggilan ini tidak diperlukan. Harga promo Jubelio tidak ikut; harga yang dipakai adalah harga INT-JB-04.

### Endpoint

`GET https://api2.jubelio.com/inventory/items/to-sell/{location_id}?type={type}&page=1&pageSize=200`

| Parameter | Wajib | Nilai |
| --- | --- | --- |
| `location_id`\* | Ya | `JUBELIO_LOCATION_ID` |
| `type`\* | Ya | `0`. Nilai `0`, `1`, dan `2` memberi hasil yang sama di akun asli (diuji 15 Okt 2026) |
| `page`\*, `pageSize`\* | Ya | 1, 200 |

### User Story

Sebagai backend, saya ingin tahu `tax_id`, satuan, dan harga jual setiap item seperti yang Jubelio harapkan, agar sales order yang saya kirim diterima tanpa error.

### Request Body

Tidak ada. Header `authorization`\*.

### Response Success

`200 OK` (dipotong)

```json
[
  {
    "item_id": 2976,
    "item_code": "UWF-C1PRO-HTM",
    "item_name": "Sepeda Listrik Uwinfly C1 Pro - Hitam",
    "sell_price": 6750000,
    "promotion_price": null,
    "sell_unit": "Unit",
    "sell_tax_id": 1,
    "rate": 0,
    "tax_name": "No Tax",
    "uom_id": -1,
    "available_qty": 4,
    "weight_in_gram": "32000.0000"
  }
]
```

`weight_in_gram` berisi berat varian dengan cadangan berat item group yang sudah diterapkan Jubelio; nilainya sama dengan INT-JB-05.

### Response Failed

Lihat INT-HDL.

### Acceptance Criteria

- [ ] `sell_unit` → `items[].unit` sales order; `sell_tax_id` dibandingkan dengan `JUBELIO_TAX_ID` (toko tanpa PPN).
- [ ] Hasil di-cache 1 jam per `item_id`, dihapus saat produk disinkron ulang.
- [ ] Bila `promotion_price` aktif (antara `start_date` dan `end_date`), keputusan apakah website ikut memakai harga promo dicatat sebagai pertanyaan terbuka; versi 1 memakai harga dari INT-JB-04.

---

## INT-JB-08 · Region (provinsi, kota, kecamatan, kelurahan)

### Endpoint

| Panggilan | Path |
| --- | --- |
| Provinsi | `GET https://api2.jubelio.com/region/provinces` |
| Kota | `GET https://api2.jubelio.com/region/cities?province_id={id}` |
| Kecamatan | `GET https://api2.jubelio.com/region/districts?city_id={id}` |
| Kelurahan | `GET https://api2.jubelio.com/region/subdistricts?district_id={id}` |

### User Story

Sebagai backend, saya ingin menyediakan daftar wilayah dengan ID yang sama dengan Jubelio, agar alamat di sales order (`shipping_*_id`) dikenali Jubelio dan kurir.

### Request Body

Tidak ada. Header `authorization`\*.

Query string **tanpa** garis miring sebelum `?`: `/region/cities/?province_id=32` dibalas 404, `/region/cities?province_id=32` dibalas 200, walau `dist.yaml` menulis versi bergaris miring (diuji 15 Okt 2026). Kebalikan dari INT-JB-05.

### Response Success

`200 OK` — contoh kelurahan:

```json
[
  { "subdistrict_id": "3216052001", "district_id": "321605", "name": "Satriajaya" }
]
```

Provinsi: `province_id`, `name`. Kota: `city_id`, `province_id`, `name`. Kecamatan: `district_id`, `city_id`, `name`.

Kelurahan Jubelio **tidak memuat kode pos**, jadi `post_code` di REG-04 selalu `null` dan customer mengisinya sendiri. Penulisan nama mengikuti Jubelio apa adanya: kota huruf besar ("KAB. BEKASI"), kecamatan dan kelurahan huruf kapital di awal kata ("Tambun Utara", "Satriajaya").

### Response Failed

Lihat INT-HDL. REG-01 – REG-04 membalas dari cache bila Jubelio gagal.

### Acceptance Criteria

- [ ] Di-cache 30 hari per parent ID.
- [ ] Response dipetakan ke objek Region `{ "id", "name" }` untuk frontend.

---

## INT-JB-09 · Buat/ubah contact customer

### Endpoint

`POST https://api2.jubelio.com/contacts/`

### User Story

Sebagai backend, saya ingin memastikan setiap customer website punya contact di Jubelio, karena `contact_id` wajib saat membuat sales order.

### Request Body

| Field Jubelio | Wajib | Nilai dari website |
| --- | --- | --- |
| `contact_id` | — | `0` untuk baru, `users.jubelio_contact_id` untuk ubah |
| `contact_name`\* | Ya | `users.name` |
| `contact_type`\* | Ya | `0` (Customer) |
| `email` | Tidak | `users.email` |
| `phone`, `mobile` | Tidak | `users.phone` |
| `s_address`, `s_province`, `s_province_id`, `s_city`, `s_city_id`, `s_district_id`, `s_subdistrict`, `s_subdistrict_id`, `s_post_code` | Tidak | Alamat pengiriman pesanan saat itu. **Diuji di akun asli:** semua id wilayah (`s_province_id`, `s_city_id`, `s_district_id`, `s_subdistrict_id`) harus berupa teks; angka ditolak `HTTP 400` |
| `payment_term` | Tidak | `-1` (Cash) |
| `source_detail` | Tidak | `"Website Saeful Askar"` |

```json
{
  "contact_id": 0,
  "contact_name": "Budi Santoso",
  "contact_type": 0,
  "is_company": false,
  "email": "budi@example.com",
  "phone": "081234567890",
  "mobile": "081234567890",
  "s_address": "Jl. Melati No. 12, RT 03/RW 05",
  "s_province": "JAWA BARAT",
  "s_province_id": 32,
  "s_city": "KAB. BEKASI",
  "s_city_id": 3216,
  "s_district_id": "321605",
  "s_subdistrict": "Satriajaya",
  "s_subdistrict_id": 3216052001,
  "s_post_code": "17565",
  "payment_term": -1,
  "source_detail": "Website Saeful Askar"
}
```

### Response Success

`200 OK`

```json
{
  "status": "ok",
  "contact_id": 5512
}
```

### Response Failed

Lihat INT-HDL. Saat checkout, kegagalan → `502 INTEGRATION_ERROR` dan order tidak dibuat.

### Acceptance Criteria

- [ ] `contact_id` disimpan ke `users.jubelio_contact_id` sekali; checkout berikutnya tidak membuat contact baru.
- [ ] Sebelum membuat baru, cari contact dengan email yang sama (`GET /contacts/customers/?q=email`) agar tidak duplikat.
- [ ] Ubah profil (AUTH-06) memanggil endpoint ini dengan `contact_id` yang ada.
- [ ] Tipe ID wilayah mengikuti spec (sebagian number, sebagian string).

---

## INT-JB-10 · Buat sales order (belum bayar)

### Endpoint

`POST https://api2.jubelio.com/sales/orders/`

### User Story

Sebagai backend, saya ingin membuat sales order di Jubelio saat checkout, agar stok terkunci di semua channel dan gudang bisa memproses pesanan website seperti pesanan Shopee.

### Request Body

Header (nilai pada sales order):

| Field Jubelio | Wajib | Nilai dari website |
| --- | --- | --- |
| `salesorder_id`\* | Ya | `0` |
| `salesorder_no`\* | Ya | `"[auto]"` |
| `contact_id`\* | Ya | `users.jubelio_contact_id` |
| `customer_name`\* | Ya | `users.name` |
| `transaction_date`\* | Ya | Waktu checkout (ISO 8601 UTC) |
| `is_tax_included` | Tidak | `false` |
| `note` | Tidak | `orders.note` |
| `sub_total`\* | Ya | Jumlah `items[].amount` |
| `total_disc`\* | Ya | `0` |
| `total_tax`\* | Ya | `0` (tanpa PPN) |
| `grand_total`\* | Ya | `sub_total + shipping_cost + insurance_cost` |
| `ref_no` | Tidak | `orders.order_number` (kunci anti-duplikat) |
| `location_id`\* | Ya | `JUBELIO_LOCATION_ID` |
| `source`\* | Ya | `1` (Internal) |
| `store_id` | Tidak | `JUBELIO_STORE_ID` (toko "Website Saeful Askar") |
| `is_paid` | Tidak | `false` |
| `payment_method` | Tidak | `"DANA Gapura"` |
| `shipping_cost` | Tidak | Ongkir dari rate yang dipilih |
| `insurance_cost` | Tidak | Asuransi dari rate (0 bila tidak ada) |
| `shipping_full_name`, `shipping_phone`, `shipping_address`, `shipping_area`, `shipping_city`, `shipping_subdistrict`, `shipping_province`, `shipping_post_code`, `shipping_country` | Tidak | Salinan alamat order; `shipping_country = "Indonesia"` |
| `shipping_province_id` (maks 2 karakter), `shipping_city_id`, `shipping_district_id`, `shipping_subdistrict_id` | Tidak | ID Region Jubelio |
| `add_disc`\* | Ya | `0` |
| `add_fee`\* | Ya | `0` |
| `service_fee`\* | Ya | `0` |
| `items`\* | Ya | Lihat tabel item |

Item (`items[]`):

| Field Jubelio | Wajib | Nilai dari website |
| --- | --- | --- |
| `salesorder_detail_id`\* | Ya | `0` |
| `item_id`\* | Ya | `order_items.jubelio_item_id` |
| `description` | Tidak | Nama produk + varian |
| `tax_id`\* | Ya | `JUBELIO_TAX_ID` (No Tax) |
| `price`\* | Ya | `order_items.price` |
| `unit`\* | Ya | `products.sell_unit` |
| `qty_in_base`\* | Ya | `order_items.qty` |
| `disc`\* | Ya | `0` |
| `disc_amount`\* | Ya | `0` |
| `tax_amount`\* | Ya | `0` |
| `amount`\* | Ya | `price × qty` |
| `location_id`\* | Ya | `JUBELIO_LOCATION_ID` |
| `shipper` | Tidak | Nama layanan kurir, mis. `"JNE Trucking"` |

```json
{
  "salesorder_id": 0,
  "salesorder_no": "[auto]",
  "contact_id": 5512,
  "customer_name": "Budi Santoso",
  "transaction_date": "2026-10-14T14:30:00.000Z",
  "is_tax_included": false,
  "note": "Tolong dibungkus kardus tebal",
  "sub_total": 6750000,
  "total_disc": 0,
  "total_tax": 0,
  "grand_total": 6935000,
  "ref_no": "WEB-000123",
  "location_id": 1,
  "source": 1,
  "store_id": "-1043",
  "is_paid": false,
  "payment_method": "DANA Gapura",
  "shipping_cost": 185000,
  "insurance_cost": 0,
  "shipping_full_name": "Budi Santoso",
  "shipping_phone": "081234567890",
  "shipping_address": "Jl. Melati No. 12, RT 03/RW 05",
  "shipping_area": "TAMBUN UTARA",
  "shipping_city": "KAB. BEKASI",
  "shipping_subdistrict": "Satriajaya",
  "shipping_province": "JAWA BARAT",
  "shipping_province_id": "32",
  "shipping_city_id": "3216",
  "shipping_district_id": "321605",
  "shipping_subdistrict_id": "3216052001",
  "shipping_post_code": "17565",
  "shipping_country": "Indonesia",
  "add_disc": 0,
  "add_fee": 0,
  "service_fee": 0,
  "items": [
    {
      "salesorder_detail_id": 0,
      "item_id": 2976,
      "description": "Sepeda Listrik Uwinfly C1 Pro - Hitam",
      "tax_id": 1,
      "price": 6750000,
      "unit": "Unit",
      "qty_in_base": 1,
      "disc": 0,
      "disc_amount": 0,
      "tax_amount": 0,
      "amount": 6750000,
      "location_id": 1,
      "shipper": "JNE Trucking"
    }
  ]
}
```

Nilai `store_id`, `location_id`, dan `tax_id` di contoh hanya ilustrasi; nilai nyata dari `.env`.

### Response Success

`200 OK`

```json
{
  "id": 98112
}
```

Setelah itu backend memanggil INT-JB-13 untuk mengambil `salesorder_no`.

### Response Failed

| HTTP | Contoh | Penanganan |
| --- | --- | --- |
| 500 | `{"message":"An internal server error occurred"}` | Cek INT-JB-13 (`GET /sales/v2/orders/?q=WEB-000123`); bila SO sudah ada, pakai ID itu; bila tidak, order gagal (ORD-01 → 502) |
| 500 | `code: "23505"` | Duplikat: cari SO lewat `ref_no` seperti di atas |

### Acceptance Criteria

- [ ] `grand_total` = `orders.grand_total` = jumlah order Gapura.
- [ ] Sales order tampil di Jubelio pada toko "Website Saeful Askar", status belum bayar, dengan alamat dan kurir lengkap.
- [ ] Tidak pernah ada dua sales order dengan `ref_no` sama.
- [ ] `jubelio_salesorder_id` dan `jubelio_salesorder_no` tersimpan di `orders`.

---

## INT-JB-11 · Tandai sales order lunas

### Endpoint

`POST https://api2.jubelio.com/sales/orders/set-as-paid`

### User Story

Sebagai backend, saya ingin menandai sales order lunas segera setelah Gapura mengonfirmasi pembayaran, agar gudang mulai memproses pesanan.

### Request Body

| Field | Tipe | Wajib | Nilai |
| --- | --- | --- | --- |
| `ids` | array integer | Ya | `[orders.jubelio_salesorder_id]` |

```json
{
  "ids": [98112]
}
```

### Response Success

`200 OK`

```json
{
  "status": "ok"
}
```

### Response Failed

Lihat INT-HDL. Setelah 5 kali gagal → `jubelio_sync_status = failed`, muncul di ADM-09 (filter `jubelio_sync_status=failed`), bisa dikirim ulang lewat ADM-11.

### Acceptance Criteria

- [ ] Dipanggil oleh `MarkSalesOrderPaidJob` setelah WH-01 `PAID`.
- [ ] Aman dipanggil ulang untuk SO yang sudah lunas.
- [ ] **Diuji di akun Jubelio**: setelah lunas, SO masuk antrean gudang (wms_status berubah ke tahap siap proses).

---

## INT-JB-12 · Batalkan sales order

### Endpoint

`POST https://api2.jubelio.com/sales/orders/cancel/`

### User Story

Sebagai backend, saya ingin membatalkan sales order yang tidak dibayar atau dibatalkan customer, agar stok kembali tersedia untuk Shopee dan website.

### Request Body

| Field | Tipe | Wajib | Nilai |
| --- | --- | --- | --- |
| `ids`\* | array integer | Ya | `[orders.jubelio_salesorder_id]` |

```json
{
  "ids": [98112]
}
```

### Response Success

`200 OK`

```json
{
  "status": "ok"
}
```

### Response Failed

Lihat INT-HDL. Gagal permanen → `jubelio_sync_status = failed`, dikirim ulang lewat ADM-11 (`action = cancel`).

### Acceptance Criteria

- [ ] Dipanggil saat WH-01 `05` (kedaluwarsa), ORD-05, dan pembatalan karena order Gapura gagal dibuat (ORD-01 langkah 8).
- [ ] Setelah berhasil, stok varian naik kembali (terkonfirmasi lewat WH-04 atau rekonsiliasi).

---

## INT-JB-13 · Ambil / cari sales order

### Endpoint

| Panggilan | Path | Untuk |
| --- | --- | --- |
| Detail | `GET https://api2.jubelio.com/sales/orders/{salesorder_id}` | Ambil `salesorder_no`, status, resi |
| Cari | `GET https://api2.jubelio.com/sales/v2/orders/?page=1&page_size=10&q={order_number}&store_id={JUBELIO_STORE_ID}` | Cegah duplikat berdasarkan `ref_no` |

### User Story

Sebagai backend, saya ingin membaca sales order di Jubelio, agar bisa menyimpan nomor SO, memastikan tidak ada duplikat, dan mencocokkan status saat webhook terlewat.

### Request Body

Tidak ada. Header `authorization`\*.

### Response Success

`200 OK` — contoh hasil cari (dipotong):

```json
{
  "data": [
    {
      "salesorder_id": 98112,
      "salesorder_no": "SO-000981",
      "ref_no": "WEB-000123",
      "store_id": -1043,
      "channel_id": 1,
      "is_paid": false,
      "is_canceled": false,
      "internal_status": "PAID",
      "wms_status": null,
      "tracking_no": null,
      "grand_total": "6935000.0000"
    }
  ],
  "totalCount": 1
}
```

### Response Failed

Lihat INT-HDL.

### Acceptance Criteria

- [ ] Pencarian memastikan `ref_no` sama persis (bukan hanya mengandung teks).
- [ ] `grand_total` berupa string desimal; dikonversi ke integer rupiah sebelum dibandingkan.

---

## INT-JB-14 · Setup awal (toko, gudang, pajak, webhook)

### Endpoint

| Panggilan | Path | Untuk |
| --- | --- | --- |
| Daftar toko | `GET https://api2.jubelio.com/locations/store/?page=1&pageSize=200` | Cari `store_id` toko internal website (`channel_id = 1`). `/marketplace/store/` hanya memuat toko marketplace (dan membawa `extra_info` berisi secret), jadi tidak dipakai |
| Daftar gudang | `GET https://api2.jubelio.com/locations/?page=1&pageSize=200` | Cari `location_id` gudang Tambun Utara |
| Daftar pajak | `GET https://api2.jubelio.com/taxes/?page=1&pageSize=200` | Cari `tax_id` "No Tax" |
| Daftarkan webhook | `POST https://api2.jubelio.com/systemsetting/webhook` | Pasang URL WH-02 – WH-05 |

### User Story

Sebagai developer yang menyiapkan lingkungan, saya ingin mengambil ID toko, gudang, dan pajak yang benar serta mendaftarkan URL webhook, agar konfigurasi `.env` tidak diisi dengan tebakan.

### Request Body

Hanya untuk daftarkan webhook:

| Field | Wajib | Nilai |
| --- | --- | --- |
| `webhooks`\* | Ya | Daftar `{ action, url }` |

```json
{
  "webhooks": [
    { "action": "update-product", "url": "https://<domain>/webhooks/jubelio/product" },
    { "action": "update-price", "url": "https://<domain>/webhooks/jubelio/price" },
    { "action": "update-qty", "url": "https://<domain>/webhooks/jubelio/stock" },
    { "action": "update-salesorder", "url": "https://<domain>/webhooks/jubelio/salesorder" }
  ]
}
```

Secret key diisi lewat dashboard Jubelio (Pengaturan → Developer → Webhook) atau action `secret-key`.

### Response Success

`200 OK` — contoh daftar toko dari akun toko (`GET /locations/store/`):

```json
{
  "data": [
    { "store_id": -100, "store_name": "Toko Default", "channel_id": 1, "location_id": null },
    { "store_id": 99936, "store_name": "Ebike Part", "channel_id": 64, "location_id": null },
    { "store_id": -101, "store_name": "SIMDISA", "channel_id": 1, "location_id": null },
    { "store_id": -102, "store_name": "Website", "channel_id": 1, "location_id": null }
  ],
  "totalCount": 4
}
```

`store_id` berupa angka (toko internal bernilai negatif); dibandingkan sebagai string dengan `JUBELIO_STORE_ID`. Di akun toko, pajak `No Tax` = `1`, sedangkan `-1` = PPN 10%: `tax_id` di contoh lain dokumen ini hanya ilustrasi, jangan disalin.

Daftarkan webhook: `{ "status": "ok" }`.

### Response Failed

Lihat INT-HDL.

### Acceptance Criteria

- [ ] Disediakan sebagai perintah `php artisan jubelio:setup` yang menampilkan ID toko/gudang/pajak untuk disalin ke `.env` dan (opsional) mendaftarkan webhook.
- [ ] Perintah ini juga menampilkan nama toko dan gudang dari ID yang sedang terpasang di `.env`, untuk memastikan ID benar (tampilan di ADM-17 menyusul di v1.1).

---

## INT-JS-01 · Tarif ongkir Jubelio Shipment

### Endpoint

Belum diketahui. Dokumentasi API rates Jubelio Shipment tidak publik; diminta ke Jubelio (info@jubelio.com / tim partnership) beserta Client ID, Client Secret, dan akses staging.

### User Story

Sebagai backend, saya ingin meminta daftar layanan kurir dan ongkir dari gudang Tambun Utara ke alamat customer berdasarkan berat paket, agar SHIP-01 bisa menampilkan pilihan pengiriman yang nyata.

### Request Body

Data yang **akan dikirim** (nama field menyesuaikan dokumentasi yang diterima):

| Data | Sumber |
| --- | --- |
| Asal: alamat, kecamatan/kelurahan, kode pos 17566 | Konfigurasi gudang Tambun Utara |
| Tujuan: provinsi, kota, kecamatan, kelurahan (ID dan nama), kode pos | Alamat customer |
| Berat total (gram) | Jumlah `qty × package_weight` varian |
| Dimensi paket (cm) | `products.package_*` (untuk layanan kargo) |
| Nilai barang | Subtotal keranjang (untuk asuransi) |

```json
{
  "origin": { "subdistrict_id": "3216052001", "post_code": "17566" },
  "destination": { "subdistrict_id": "3216052001", "post_code": "17565" },
  "weight_gram": 32000,
  "dimensions_cm": { "length": 150, "width": 30, "height": 80 },
  "item_value": 6750000
}
```

Contoh di atas adalah bentuk internal `ShippingRateService`, bukan format Jubelio Shipment.

### Response Success

Dipetakan ke objek **ShippingRate** (lihat `README.md`): kode dan nama kurir, kode dan nama layanan, jenis layanan, ongkir, asuransi, estimasi hari.

### Response Failed

| Kondisi | Hasil di SHIP-01 |
| --- | --- |
| Tidak ada layanan | `422 NO_SHIPPING_SERVICE` |
| Timeout > 10 detik / 5xx | `502 INTEGRATION_ERROR` |
| Kredensial salah | `502 INTEGRATION_ERROR` + alert ke log |

### Acceptance Criteria

- [ ] Selama dokumentasi belum ada, `JubelioShipmentClient` memakai stub yang mengembalikan 2–3 layanan contoh agar frontend dan alur checkout bisa diuji.
- [ ] Setelah dokumentasi diterima, bagian ini diperbarui dengan endpoint, field wajib, dan contoh response asli.
- [ ] Kode kurir/layanan disimpan di order dan dikirim sebagai `shipper` di INT-JB-10 agar gudang memakai kurir yang sama.

---

## INT-GP-00 · Persiapan Gapura (DANA)

Gapura adalah payment gateway DANA. Website memakai **Hosted Checkout**: backend membuat order di DANA, DANA membalas URL halaman bayar, pelanggan membayar di sana, lalu DANA memberi tahu hasilnya lewat WH-01. Semua panggilan memakai SDK resmi `danaid/dana-php` lewat `App\Services\Gapura\GapuraClient`.

| Hal | Isi |
| --- | --- |
| Base URL | Sandbox `https://api.sandbox.dana.id`, produksi `https://api.saas.dana.id` (dipilih `GAPURA_ENV`) |
| Otentikasi | Tanda tangan RSA-2048 SHA-256 (SNAP) dengan **private key kita** di header `X-SIGNATURE`. String yang ditandatangani: `METHOD:PATH:<hex sha256 body yang di-minify>:<X-TIMESTAMP>`. Tanpa access token. SDK mengisi `X-SIGNATURE`, `X-TIMESTAMP`, `X-PARTNER-ID`, `X-EXTERNAL-ID`, `CHANNEL-ID`, dan `ORIGIN` |
| Kredensial sandbox | Dari `https://dashboard.dana.id/sandbox/`: Merchant ID, Client ID (= `X-PARTNER-ID`), Client Secret, public key, dan private key |
| Kredensial produksi | Buat pasangan kunci sendiri (`openssl genrsa -out rsa_private_key.pem 2048`, lalu `openssl pkcs8 -topk8 -in rsa_private_key.pem -out pkcs8_rsa_private_key.pem -nocrypt`, lalu `openssl rsa -in rsa_private_key.pem -out rsa_public_key.pem -pubout`), unggah **public key** ke Merchant Portal, lalu terima Merchant ID dan Client ID. Public key produksi DANA diminta ke tim integrasi DANA |
| Timeout | 8 detik per panggilan (nilai yang diharapkan DANA) |
| Batas sandbox | `validUpTo` paling lama 30 menit ke depan dan nominal paling besar Rp10.000.000 |

Konfigurasi di `.env` (`config/gapura.php`):

| Variabel | Isi |
| --- | --- |
| `GAPURA_ENV` | `sandbox` atau `production` |
| `GAPURA_CLIENT_ID` | Client ID (`X-PARTNER-ID`) |
| `GAPURA_MERCHANT_ID` | Merchant ID |
| `GAPURA_PRIVATE_KEY_PATH` | Path file private key (PEM), di luar web root dan di luar git |
| `GAPURA_DANA_PUBLIC_KEY_PATH` | Public key DANA untuk verifikasi webhook; hanya perlu di produksi (SDK membawa kunci sandbox) |
| `GAPURA_ORIGIN` | Domain asal request (default `APP_URL`) |
| `GAPURA_MCC` | Merchant category code, wajib di Create Order |
| `GAPURA_NOTIFY_URL` | URL publik webhook; kosong = `APP_URL` + `/v1.0/debit/notify`. Lewat tunnel (ngrok) isi URL lengkapnya |
| `GAPURA_PAYMENT_DURATION` | Batas bayar dalam detik (default 600 = 10 menit; sandbox DANA maksimal 30 menit) |

---

## INT-GP-01 · Buat order Gapura (Hosted Checkout)

### Endpoint

`POST {base_url}/payment-gateway/v1.0/debit/payment-host-to-host.htm` · SNAP service code 54 · `GapuraClient::createOrder()`

### User Story

Sebagai backend, saya ingin membuat order di DANA dengan batas waktu bayar untuk setiap pesanan, agar customer bisa membayar dengan metode yang ia pilih di halaman DANA dan pesanan otomatis batal bila tidak dibayar.

### Request Body

| Field DANA | Wajib | Nilai dari website |
| --- | --- | --- |
| `partnerReferenceNo` | Ya | `orders.order_number` (maks 64 karakter). Kunci idempoten bersama `merchantId`: permintaan ulang dengan data sama mengembalikan order yang sama (`2005400`), data berbeda ditolak `4045418` |
| `merchantId` | Ya | `GAPURA_MERCHANT_ID` |
| `amount.value` | Ya | `orders.grand_total` sebagai string dua desimal, mis. `"6935000.00"` |
| `amount.currency` | Ya | `IDR` |
| `validUpTo` | Ya | Sekarang + `GAPURA_PAYMENT_DURATION`, format `YYYY-MM-DDTHH:mm:ss+07:00` |
| `urlParams[]` `NOTIFICATION` | Ya | `GAPURA_NOTIFY_URL` atau `<APP_URL>/v1.0/debit/notify`, `isDeeplink = "N"` |
| `urlParams[]` `PAY_RETURN` | Ya | `https://<domain>/checkout/finish?order=WEB-000123`, `isDeeplink = "N"` |
| `additionalInfo.mcc` | Ya | `GAPURA_MCC` |
| `additionalInfo.envInfo.sourcePlatform` | Ya | `IPG` |
| `additionalInfo.envInfo.terminalType` | Ya | `SYSTEM` |
| `additionalInfo.order.orderTitle` | Ya | `"Pesanan WEB-000123"` |
| `additionalInfo.order.scenario` | Ya | `REDIRECT` |

```json
{
  "partnerReferenceNo": "WEB-000123",
  "merchantId": "216620000000000000000",
  "amount": { "value": "6935000.00", "currency": "IDR" },
  "validUpTo": "2026-10-20T12:30:00+07:00",
  "urlParams": [
    { "url": "https://<domain>/v1.0/debit/notify", "type": "NOTIFICATION", "isDeeplink": "N" },
    { "url": "https://<domain>/checkout/finish?order=WEB-000123", "type": "PAY_RETURN", "isDeeplink": "N" }
  ],
  "additionalInfo": {
    "mcc": "5499",
    "envInfo": { "sourcePlatform": "IPG", "terminalType": "SYSTEM" },
    "order": { "orderTitle": "Pesanan WEB-000123", "scenario": "REDIRECT" }
  }
}
```

### Response Success

`200 OK`

```json
{
  "responseCode": "2005400",
  "responseMessage": "Successful",
  "referenceNo": "20261020111212800110166000000000001",
  "partnerReferenceNo": "WEB-000123",
  "webRedirectUrl": "https://m.dana.id/d/portal/cashier?ticket=…"
}
```

### Response Failed

| HTTP | `responseCode` DANA | Penanganan |
| --- | --- | --- |
| 400 | `4005400` – `4005402` | Bug data; batalkan SO (INT-JB-12), ORD-01 → 502 |
| 401 | `4015400` | Tanda tangan salah (kunci atau jam server); ORD-01 → 502, alert |
| 403 | `4035402`, `4035405`, `4035415` | Nominal melewati batas atau transaksi tidak diizinkan; ORD-01 → 502 |
| 404 | `4045408` | Merchant tidak valid; ORD-01 → 502, alert |
| 404 | `4045418` | Data berbeda untuk `partnerReferenceNo` yang sama; periksa lewat INT-GP-02 |
| 429, 5xx | `4295400`, `5005401` | Retry dengan data **yang sama** (aman karena idempoten), sekali di request customer; gagal → batalkan SO, ORD-01 → 502 |
| timeout | — | Maksimal 3 percobaan dengan data yang sama; masih gagal → batalkan SO, ORD-01 → 502 |

### Acceptance Criteria

- [ ] `referenceNo` → `payments.gapura_reference_no`, `partnerReferenceNo` → `payments.partner_reference_no`, `webRedirectUrl` → `payments.checkout_url`, `validUpTo` → `payments.expires_at` dan `orders.payment_expires_at`.
- [ ] Hanya `responseCode = 2005400` dengan `webRedirectUrl` yang dianggap berhasil; kode lain → `IntegrationException` tidak retryable.
- [ ] Penandatanganan dan `X-*` header diisi SDK; tidak ada kode tanda tangan buatan sendiri.
- [ ] Mode `sandbox` di lokal/staging, `production` di produksi; `GAPURA_ENV` juga diekspor ke `DANA_ENV` agar aturan sandbox di SDK konsisten.

---

## INT-GP-02 · Cek status pembayaran

### Endpoint

`POST {base_url}/payment-gateway/v1.0/debit/status.htm` · SNAP service code 55 · `GapuraClient::queryPayment()`

### User Story

Sebagai backend, saya ingin mengecek status order langsung ke DANA saat webhook terlambat atau admin memintanya, agar status pesanan tidak tertinggal.

### Request Body

| Field DANA | Wajib | Nilai dari website |
| --- | --- | --- |
| `originalPartnerReferenceNo` | Ya* | `orders.order_number` |
| `serviceCode` | Ya | `54` (kode Create Order) |
| `merchantId` | Ya | `GAPURA_MERCHANT_ID` |

\* Salah satu dari `originalPartnerReferenceNo` atau `originalReferenceNo` wajib diisi.

### Response Success

`200 OK` (dipotong)

```json
{
  "responseCode": "2005500",
  "responseMessage": "Successful",
  "originalPartnerReferenceNo": "WEB-000123",
  "originalReferenceNo": "20261020111212800110166000000000001",
  "latestTransactionStatus": "00",
  "transactionStatusDesc": "success",
  "amount": { "value": "6935000.00", "currency": "IDR" },
  "paidTime": "2026-10-20T10:42:10+07:00",
  "additionalInfo": {
    "paymentViews": [
      { "payOptionInfos": [ { "payMethod": "VIRTUAL_ACCOUNT", "payOption": "VIRTUAL_ACCOUNT_BCA" } ] }
    ]
  }
}
```

`latestTransactionStatus` dipetakan oleh `GapuraStatus` ke `PaymentStatus`:

| Kode DANA | Arti | `PaymentStatus` |
| --- | --- | --- |
| `00` | Sukses, sudah dibayar | `PAID` |
| `02` | Sedang diproses, pembayaran berhasil | `PAID` |
| `01` | Dibuat, belum dibayar | `PENDING` |
| `05` | Order ditutup (kedaluwarsa atau dibatalkan) | `EXPIRED` |
| `07` | Order tidak ditemukan | `EXPIRED` |

Kode lain → `IntegrationException` (tidak dipercaya).

### Response Failed

| HTTP | Penanganan |
| --- | --- |
| 404 `4045501` | Catat sebagai `failed`, tampilkan di ADM-10 |
| 429, 5xx / timeout | ADM-12 → 502; job cadangan mencoba lagi di putaran berikutnya |

### Acceptance Criteria

- [ ] Dipakai oleh ORD-04, ADM-12, dan job `ExpireUnpaidOrders`.
- [ ] Hasil `PAID` dan `EXPIRED` diproses dengan alur yang sama dengan WH-01 (idempotent).

---

## INT-GP-03 · Batalkan order

### Endpoint

`POST {base_url}/payment-gateway/v1.0/debit/cancel.htm` · `GapuraClient::cancelOrder()`

### User Story

Sebagai backend, saya ingin menutup order di DANA saat customer membatalkan pesanan, agar customer tidak bisa membayar pesanan yang sudah dibatalkan.

### Request Body

| Field DANA | Wajib | Nilai dari website |
| --- | --- | --- |
| `originalPartnerReferenceNo` | Ya | `orders.order_number` |
| `merchantId` | Ya | `GAPURA_MERCHANT_ID` |
| `reason` | Tidak | mis. `"Dibatalkan pelanggan"` |

### Response Success

`200 OK` dengan `responseCode = 2005700` (dibatalkan), atau `202` dengan `2025700` (sedang diproses). Keduanya dianggap diterima.

```json
{
  "responseCode": "2005700",
  "responseMessage": "Successful",
  "originalPartnerReferenceNo": "WEB-000123",
  "originalReferenceNo": "20261020111212800110166000000000001",
  "cancelTime": "2026-10-20T10:50:00+07:00"
}
```

### Response Failed

| HTTP | `responseCode` | Penanganan |
| --- | --- | --- |
| 403 | `4035700` Transaction Expired | Order sudah kedaluwarsa; jalankan alur kedaluwarsa, ORD-05 mengikuti status sebenarnya |
| 404 | `4045700` Invalid Transaction Status | Sudah dibayar atau sudah ditutup: jangan batalkan order; jalankan INT-GP-02 lalu alur yang sesuai, ORD-05 → 409 `ORDER_NOT_CANCELLABLE` |
| 404 | `4045701` Transaction Not Found | Order tidak ada di DANA; periksa lewat INT-GP-02 |
| 429, 5xx / timeout | — | ORD-05 → 502, status order tidak berubah |

### Acceptance Criteria

- [ ] Dipanggil **sebelum** membatalkan sales order Jubelio di ORD-05.
- [ ] Notifikasi `05` yang datang setelahnya diabaikan dengan aman (order sudah `cancelled`).
- [ ] Refund (`refund.htm`) tidak dipakai di v1: pesanan dibatalkan hanya sebelum dibayar (PRD F-19).
