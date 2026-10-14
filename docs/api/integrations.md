# Integrasi Keluar (Backend → Jubelio, Jubelio Shipment, Xendit)

File ini mendokumentasikan panggilan yang dilakukan **backend** ke layanan luar. Frontend tidak pernah memanggil layanan ini langsung. Semua panggilan hanya boleh lewat kelas client:

| Layanan | Client | Base URL |
| --- | --- | --- |
| Jubelio Omnichannel | `App\Services\Jubelio\JubelioClient` | `https://api2.jubelio.com` |
| Jubelio Shipment | `App\Services\JubelioShipment\JubelioShipmentClient` | Menunggu dokumentasi dari Jubelio |
| Xendit | `App\Services\Xendit\XenditClient` | `https://api.xendit.co` |

Field Jubelio di bawah diambil dari spec OpenAPI resmi Jubelio (`dist.yaml`, "Jubelio API Reference"). Tanda **\*** = wajib menurut spec. Field Xendit diambil dari SDK resmi `xendit-php`.

---

## INT-HDL · Aturan umum semua panggilan keluar

### Endpoint

Berlaku untuk semua INT-JB, INT-JS, dan INT-XD.

### User Story

Sebagai backend, saya ingin semua panggilan ke layanan luar punya timeout, retry, dan log yang seragam, agar gangguan sementara tidak merusak data dan masalah mudah ditelusuri.

### Request Body

Tidak berlaku (aturan umum).

### Response Success

HTTP 2xx dari layanan luar → hasil dipetakan ke model/DTO internal.

### Response Failed

| Kondisi | Penanganan |
| --- | --- |
| Timeout (Jubelio 15 detik, Jubelio Shipment 10 detik, Xendit 15 detik) | Lempar `JubelioApiException` / `IntegrationException`; job di-retry |
| Jubelio 401 / token kedaluwarsa | Login ulang sekali (INT-JB-01), ulangi request sekali |
| Jubelio 429 | Tunggu 60 detik, ulangi; job sinkron menahan laju < 600 request/menit |
| Jubelio 500 dengan `code` SQLSTATE (mis. `23505`) | Simpan pesan apa adanya di `sync_logs.message`; untuk sales order, cek dulu apakah SO dengan `ref_no` sama sudah ada sebelum retry |
| Xendit 4xx | Tidak di-retry; catat `error_code` dan `message` dari Xendit |
| 5xx lain | Retry di queue: 5 kali, jeda 10 dtk, 30 dtk, 2 mnt, 5 mnt, 15 mnt |

### Acceptance Criteria

- [ ] Hanya kelas client yang memakai HTTP client ke domain luar (dicek di code review).
- [ ] Setiap panggilan dicatat (method, path, status, durasi) di log aplikasi; body yang berisi data pribadi/kredensial tidak dicatat.
- [ ] Panggilan di dalam request customer (checkout, ongkir, region) tidak di-retry lebih dari sekali agar customer tidak menunggu lama; sisanya lewat queue.
- [ ] Semua client punya fake/fixture untuk test (`tests/Fixtures/jubelio`, `tests/Fixtures/xendit`).

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
| 500 | `{"statusCode":500,"error":"Internal Server Error","message":"…","code":"Invalid email or password"}` | Tandai `login_ok = false` (ADM-17), log error, jangan retry terus-menerus |

### Acceptance Criteria

- [ ] Token disimpan di Redis dengan TTL 11 jam 30 menit (token berlaku 12 jam) oleh `JubelioTokenManager`.
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
- [ ] Angka string (`package_weight`, dimensi) dikonversi ke integer.

---

## INT-JB-05 · Berat per varian

### Endpoint

`GET https://api2.jubelio.com/variations?page={n}&pageSize=200`

### User Story

Sebagai backend, saya ingin mengambil berat paket per SKU, agar ongkir baterai dengan kapasitas berbeda dihitung dari berat varian yang benar.

### Request Body

Tidak ada. Header `authorization`\*.

### Response Success

`200 OK` (dipotong)

```json
{
  "data": [
    { "item_group_id": 1940, "item_id": 3001, "item_code": "BAT-48V-12AH", "package_weight": 6500, "is_variant": true },
    { "item_group_id": 1940, "item_id": 3002, "item_code": "BAT-48V-20AH", "package_weight": 9500, "is_variant": true }
  ],
  "totalCount": 52
}
```

### Response Failed

Lihat INT-HDL.

### Acceptance Criteria

- [ ] `package_weight` → `product_variants.package_weight` (gram) dicocokkan lewat `item_id`.
- [ ] Bila `package_weight` per SKU kosong/0, pakai `package_weight` item group (INT-JB-04).
- [ ] **Diuji di akun Jubelio**: apakah berat per varian benar-benar bisa berbeda. Bila tidak, varian dengan berat berbeda dipisah menjadi produk sendiri di Jubelio.

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
      "item_id": "2976",
      "item_code": "UWF-C1PRO-HTM",
      "item_group_id": "1931",
      "location_stocks": [
        { "item_id": "2976", "location_id": "1", "on_hand": 5, "on_order": 0, "reserved": 1, "available": 4 }
      ],
      "total_stocks": { "on_hand": 5, "on_order": 0, "reserved": 1, "available": 4 }
    }
  ]
}
```

### Response Failed

Lihat INT-HDL. Saat checkout (ORD-01) kegagalan dibalas `502 INTEGRATION_ERROR` ke customer.

### Acceptance Criteria

- [ ] Stok yang dipakai = `location_stocks[].available` dengan `location_id = JUBELIO_LOCATION_ID`.
- [ ] `item_id` di response berupa string; dicocokkan setelah dikonversi ke integer.
- [ ] Hasil cocok persis berdasarkan `item_code` (pencarian `q` bisa mengembalikan SKU mirip).
- [ ] **Diuji di akun Jubelio**: sales order belum bayar (INT-JB-10) mengurangi `available`. Bila tidak, tambah reservasi stok (`POST /inventory/reserved/`).

---

## INT-JB-07 · Data item untuk sales order

### Endpoint

`GET https://api2.jubelio.com/inventory/items/to-sell/{location_id}?type={type}&page=1&pageSize=200`

| Parameter | Wajib | Nilai |
| --- | --- | --- |
| `location_id`\* | Ya | `JUBELIO_LOCATION_ID` |
| `type`\* | Ya | Nilai tidak dijelaskan di spec; dipastikan saat uji coba |
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
    "sell_tax_id": -1,
    "rate": 0,
    "tax_name": "No Tax",
    "uom_id": -1
  }
]
```

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
| Kota | `GET https://api2.jubelio.com/region/cities/?province_id={id}` |
| Kecamatan | `GET https://api2.jubelio.com/region/districts/?city_id={id}` |
| Kelurahan | `GET https://api2.jubelio.com/region/subdistricts/?district_id={id}` |

### User Story

Sebagai backend, saya ingin menyediakan daftar wilayah dengan ID yang sama dengan Jubelio, agar alamat di sales order (`shipping_*_id`) dikenali Jubelio dan kurir.

### Request Body

Tidak ada. Header `authorization`\*.

### Response Success

`200 OK` — contoh kelurahan:

```json
[
  { "subdistrict_id": "3216122004", "district_id": "321612", "name": "Satriajaya" }
]
```

Provinsi: `province_id`, `name`. Kota: `city_id`, `province_id`, `name`. Kecamatan: `district_id`, `city_id`, `name`.

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
| `s_address`, `s_province`, `s_province_id`, `s_city`, `s_city_id`, `s_district_id`, `s_subdistrict`, `s_subdistrict_id`, `s_post_code` | Tidak | Alamat pengiriman pesanan saat itu |
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
  "s_district_id": "321612",
  "s_subdistrict": "Satriajaya",
  "s_subdistrict_id": 3216122004,
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
| `payment_method` | Tidak | `"Xendit"` |
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
  "payment_method": "Xendit",
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
  "shipping_district_id": "321612",
  "shipping_subdistrict_id": "3216122004",
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
      "tax_id": -1,
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

- [ ] `grand_total` = `orders.grand_total` = jumlah invoice Xendit.
- [ ] Sales order tampil di Jubelio pada toko "Website Saeful Askar", status belum bayar, dengan alamat dan kurir lengkap.
- [ ] Tidak pernah ada dua sales order dengan `ref_no` sama.
- [ ] `jubelio_salesorder_id` dan `jubelio_salesorder_no` tersimpan di `orders`.

---

## INT-JB-11 · Tandai sales order lunas

### Endpoint

`POST https://api2.jubelio.com/sales/orders/set-as-paid`

### User Story

Sebagai backend, saya ingin menandai sales order lunas segera setelah Xendit mengonfirmasi pembayaran, agar gudang mulai memproses pesanan.

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

Lihat INT-HDL. Setelah 5 kali gagal → `jubelio_sync_status = failed`, muncul di ADM-01/ADM-09, bisa dikirim ulang lewat ADM-11.

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

- [ ] Dipanggil saat WH-01 `EXPIRED`, ORD-05, dan pembatalan karena invoice Xendit gagal dibuat (ORD-01 langkah 8).
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
| Daftar toko | `GET https://api2.jubelio.com/marketplace/store/?page=1&pageSize=200` | Cari `store_id` toko internal "Website Saeful Askar" (`channel_id = 1`) |
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

`200 OK` — contoh daftar toko (dipotong):

```json
{
  "data": [
    { "channel_id": 64, "store_id": "38565", "store_name": "Saeful Askar Official" },
    { "channel_id": 1, "store_id": "-1043", "store_name": "Website Saeful Askar" }
  ],
  "totalCount": 2
}
```

Daftarkan webhook: `{ "status": "ok" }`.

### Response Failed

Lihat INT-HDL.

### Acceptance Criteria

- [ ] Disediakan sebagai perintah `php artisan jubelio:setup` yang menampilkan ID toko/gudang/pajak untuk disalin ke `.env` dan (opsional) mendaftarkan webhook.
- [ ] ADM-17 memakai daftar toko dan gudang untuk menampilkan nama dari ID yang terpasang.

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
  "origin": { "subdistrict_id": "3216122004", "post_code": "17566" },
  "destination": { "subdistrict_id": "3216122004", "post_code": "17565" },
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

## INT-XD-01 · Buat invoice Xendit

### Endpoint

`POST https://api.xendit.co/v2/invoices/` · Basic Auth (`XENDIT_SECRET_KEY` sebagai username, password kosong)

### User Story

Sebagai backend, saya ingin membuat tagihan Xendit dengan batas waktu bayar untuk setiap pesanan, agar customer bisa membayar dengan metode yang ia pilih dan pesanan otomatis batal bila tidak dibayar.

### Request Body

| Field Xendit | Wajib | Nilai dari website |
| --- | --- | --- |
| `external_id` | Ya | `orders.order_number` |
| `amount` | Ya | `orders.grand_total` |
| `payer_email` | Tidak | `users.email` |
| `description` | Tidak | `"Pesanan WEB-000123 - Toko Sepeda Listrik Saeful Askar"` |
| `invoice_duration` | Tidak | `XENDIT_INVOICE_DURATION` (detik) |
| `currency` | Tidak | `IDR` |
| `customer` | Tidak | Nama, email, nomor HP |
| `items` | Tidak | Nama, qty, harga tiap item + baris ongkir |
| `success_redirect_url` | Tidak | `https://<domain>/checkout/finish?order=WEB-000123` |
| `failure_redirect_url` | Tidak | `https://<domain>/checkout/finish?order=WEB-000123&failed=1` |
| `payment_methods` | Tidak | Dikosongkan = semua metode aktif di akun; atau daftar yang disepakati |
| `locale` | Tidak | `id` |
| `metadata` | Tidak | `{ "order_id": 123 }` |

```json
{
  "external_id": "WEB-000123",
  "amount": 6935000,
  "payer_email": "budi@example.com",
  "description": "Pesanan WEB-000123 - Toko Sepeda Listrik Saeful Askar",
  "invoice_duration": 7200,
  "currency": "IDR",
  "locale": "id",
  "customer": {
    "given_names": "Budi Santoso",
    "email": "budi@example.com",
    "mobile_number": "+6281234567890"
  },
  "items": [
    { "name": "Sepeda Listrik Uwinfly C1 Pro - Hitam", "quantity": 1, "price": 6750000 },
    { "name": "Ongkir JNE Trucking", "quantity": 1, "price": 185000 }
  ],
  "success_redirect_url": "https://<domain>/checkout/finish?order=WEB-000123",
  "failure_redirect_url": "https://<domain>/checkout/finish?order=WEB-000123&failed=1",
  "metadata": { "order_id": 123 }
}
```

### Response Success

`200 OK` (dipotong)

```json
{
  "id": "6527a1b2c3d4e5f6a7b8c9d0",
  "external_id": "WEB-000123",
  "status": "PENDING",
  "amount": 6935000,
  "invoice_url": "https://checkout.xendit.co/web/6527a1b2c3d4e5f6a7b8c9d0",
  "expiry_date": "2026-10-14T16:30:00.000Z",
  "currency": "IDR"
}
```

### Response Failed

| HTTP | `error_code` Xendit | Penanganan |
| --- | --- | --- |
| 400 | `API_VALIDATION_ERROR` | Bug data; batalkan SO (INT-JB-12), ORD-01 → 502 |
| 401 | `INVALID_API_KEY` | Kredensial salah; ORD-01 → 502, alert |
| 409 | `DUPLICATE_ERROR` | Invoice dengan `external_id` sama sudah ada → ambil lewat INT-XD-02 |
| 5xx / timeout | — | Retry sekali; gagal → batalkan SO, ORD-01 → 502 |

### Acceptance Criteria

- [ ] `id` → `payments.xendit_invoice_id`, `invoice_url` → `payments.invoice_url`, `expiry_date` → `payments.expires_at` dan `orders.payment_expires_at` (dikonversi ke +07:00).
- [ ] Jumlah `items` = `amount`.
- [ ] Nomor HP dikirim format E.164 (`+62…`).
- [ ] Mode test (`xnd_development_…`) di lokal/staging, live di produksi.

---

## INT-XD-02 · Ambil status invoice

### Endpoint

`GET https://api.xendit.co/v2/invoices/{invoice_id}` · Basic Auth

### User Story

Sebagai backend, saya ingin mengecek status invoice langsung ke Xendit saat webhook terlambat atau admin memintanya, agar status pesanan tidak tertinggal.

### Request Body

Tidak ada.

### Response Success

`200 OK` (dipotong)

```json
{
  "id": "6527a1b2c3d4e5f6a7b8c9d0",
  "external_id": "WEB-000123",
  "status": "PAID",
  "amount": 6935000,
  "paid_amount": 6935000,
  "paid_at": "2026-10-14T14:42:10.000Z",
  "payment_method": "BANK_TRANSFER",
  "payment_channel": "BCA",
  "expiry_date": "2026-10-14T16:30:00.000Z"
}
```

Status: `PENDING`, `PAID`, `SETTLED`, `EXPIRED`.

### Response Failed

| HTTP | Penanganan |
| --- | --- |
| 404 `INVOICE_NOT_FOUND_ERROR` | Catat sebagai `failed`, tampilkan di ADM-10 |
| 5xx / timeout | ADM-12 → 502; job cadangan mencoba lagi di putaran berikutnya |

### Acceptance Criteria

- [ ] Dipakai oleh ORD-04, ADM-12, dan job `ExpireUnpaidOrders`.
- [ ] Hasil `PAID`/`SETTLED`/`EXPIRED` diproses dengan alur yang sama dengan WH-01 (idempotent).

---

## INT-XD-03 · Kedaluwarsakan invoice

### Endpoint

`POST https://api.xendit.co/invoices/{invoice_id}/expire!` · Basic Auth

### User Story

Sebagai backend, saya ingin menutup tagihan Xendit saat customer membatalkan pesanan, agar customer tidak bisa membayar pesanan yang sudah dibatalkan.

### Request Body

Tidak ada.

### Response Success

`200 OK` — objek invoice dengan `"status": "EXPIRED"`.

```json
{
  "id": "6527a1b2c3d4e5f6a7b8c9d0",
  "external_id": "WEB-000123",
  "status": "EXPIRED",
  "amount": 6935000
}
```

### Response Failed

| HTTP | Penanganan |
| --- | --- |
| 404 / invoice sudah `PAID` | Jangan batalkan order; jalankan alur bayar, ORD-05 → 409 `ORDER_NOT_CANCELLABLE` |
| 5xx / timeout | ORD-05 → 502, status order tidak berubah |

### Acceptance Criteria

- [ ] Dipanggil **sebelum** membatalkan sales order Jubelio di ORD-05.
- [ ] Callback `EXPIRED` yang datang setelahnya diabaikan dengan aman (order sudah `cancelled`).
