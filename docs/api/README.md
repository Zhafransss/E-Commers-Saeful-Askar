# API Contract v1 — Website E-Commerce Saeful Askar

Dokumen ini adalah kontrak antara frontend (Blade + Alpine.js) dan backend (Laravel) untuk website Toko Sepeda Listrik Saeful Askar. Semua endpoint sudah ditetapkan di sini sebelum pengerjaan dimulai. Perubahan kontrak disepakati berdua dan di-commit terpisah.

- Versi: 1.0
- Tanggal: 14 Oktober 2026
- Acuan: PRD Website E-Commerce Saeful Askar (`../PRD-Website-ECommerce-Saeful-Askar.md`), ERD v2 (`../erd.md`), OpenAPI resmi Jubelio (`dist.yaml`)

## Isi folder

| File | Isi | Pemakai |
| --- | --- | --- |
| `README.md` | Konvensi, objek bersama, kode error, indeks endpoint | FE + BE |
| `auth.md` | Registrasi, login, logout, profil, password | FE + BE |
| `catalog.md` | Kategori, daftar produk, detail produk | FE + BE |
| `cart.md` | Keranjang | FE + BE |
| `addresses-regions.md` | Wilayah (Region Jubelio) dan buku alamat | FE + BE |
| `shipping.md` | Tarif ongkir (Jubelio Shipment) | FE + BE |
| `checkout-orders.md` | Checkout, pembayaran, riwayat dan detail pesanan | FE + BE |
| `articles.md` | Artikel publik | FE + BE |
| `admin.md` | Panel admin: artikel, pesanan, sinkron | FE + BE |
| `webhooks.md` | Webhook masuk dari Xendit dan Jubelio | BE |
| `integrations.md` | Panggilan keluar ke Jubelio, Jubelio Shipment, Xendit | BE |
| `dist.yaml` | OpenAPI resmi Jubelio (diunduh dari dokumentasi Jubelio), acuan INT-JB-xx dan signature webhook | BE |

## Format setiap endpoint

Setiap endpoint ditulis dengan enam bagian yang sama:

1. **Endpoint**: method, path, autentikasi, parameter path/query.
2. **User Story**: siapa, ingin apa, untuk apa.
3. **Request Body**: field, tipe, wajib/tidak, aturan, dan contoh JSON. Endpoint tanpa body ditulis "Tidak ada".
4. **Response Success**: status HTTP dan contoh JSON lengkap.
5. **Response Failed**: setiap kemungkinan gagal (status, `code`, kapan terjadi) dan contoh JSON.
6. **Acceptance Criteria**: daftar centang yang harus lulus sebelum endpoint dianggap selesai.

## Konvensi umum

### Base URL

| Lingkungan | Base URL API | Base URL webhook |
| --- | --- | --- |
| Lokal | `http://localhost:8000/api/v1` | `http://localhost:8000/webhooks` |
| Staging | `https://staging.<domain>/api/v1` | `https://staging.<domain>/webhooks` |
| Produksi | `https://<domain>/api/v1` | `https://<domain>/webhooks` |

`<domain>` diisi setelah domain toko ditetapkan.

### Header

| Header | Nilai | Kapan |
| --- | --- | --- |
| `Accept` | `application/json` | Semua request |
| `Content-Type` | `application/json` | Request dengan body JSON |
| `Content-Type` | `multipart/form-data` | Upload gambar artikel (admin) |
| `X-XSRF-TOKEN` | Nilai cookie `XSRF-TOKEN` | Semua request selain GET |
| `X-Requested-With` | `XMLHttpRequest` | Semua request dari Alpine.js |
| `Idempotency-Key` | UUID v4 buatan frontend | Hanya `POST /checkout` |

### Autentikasi

- Memakai **session cookie Laravel (Sanctum stateful)**, bukan token di localStorage.
- Sebelum login, register, atau request POST pertama, frontend memanggil `GET /sanctum/csrf-cookie` (lihat `auth.md`).
- Label di bagian **Endpoint**:
  - **Publik**: boleh tanpa login.
  - **Customer**: wajib login dengan role `customer` atau `admin`.
  - **Admin**: wajib login dengan role `admin`.
  - **Webhook**: dipanggil server Xendit/Jubelio, diverifikasi dengan token/signature.

### Format response sukses

Objek tunggal:

```json
{
  "data": { }
}
```

Daftar dengan paginasi:

```json
{
  "data": [ ],
  "meta": {
    "current_page": 1,
    "per_page": 12,
    "total": 48,
    "last_page": 4
  }
}
```

Aksi tanpa data balik (mis. logout, hapus):

```json
{
  "message": "Berhasil keluar."
}
```

### Format response gagal

Semua error memakai bentuk yang sama:

```json
{
  "message": "Data yang dikirim tidak valid.",
  "code": "VALIDATION_ERROR",
  "errors": {
    "email": ["Email sudah terdaftar."]
  }
}
```

- `message`: kalimat untuk ditampilkan ke pengguna, Bahasa Indonesia.
- `code`: kode tetap untuk logika frontend (tabel di bawah).
- `errors`: hanya ada pada `VALIDATION_ERROR`; key = nama field, value = daftar pesan.

### Kode error umum

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 400 | `BAD_REQUEST` | Format request rusak (JSON tidak valid) |
| 401 | `UNAUTHENTICATED` | Belum login atau session habis |
| 403 | `FORBIDDEN` | Login tapi tidak berhak (mis. customer membuka endpoint admin, membuka order orang lain) |
| 404 | `NOT_FOUND` | Data tidak ada atau tidak aktif |
| 405 | `METHOD_NOT_ALLOWED` | Method HTTP salah |
| 409 | `CONFLICT` | Bentrok status (mis. membayar order yang sudah dibatalkan) |
| 409 | `OUT_OF_STOCK` | Stok tidak cukup |
| 419 | `CSRF_TOKEN_MISMATCH` | Cookie XSRF hilang atau kedaluwarsa |
| 422 | `VALIDATION_ERROR` | Field tidak valid |
| 429 | `TOO_MANY_REQUESTS` | Melebihi batas request |
| 500 | `SERVER_ERROR` | Error tak terduga di backend |
| 501 | `NOT_IMPLEMENTED` | **Hanya selama pengembangan:** route sudah terdaftar tapi endpoint belum dibangun backend (response menyertakan `endpoint`, mis. `"CAT-02"`). Tidak boleh ada lagi saat rilis |
| 502 | `INTEGRATION_ERROR` | Jubelio, Jubelio Shipment, atau Xendit gagal merespons dengan benar |
| 503 | `SERVICE_UNAVAILABLE` | Mode perawatan |

Kode khusus per endpoint ditulis di file masing-masing. Daftar lengkapnya ada di bagian **Indeks kode error khusus** di bawah.

### Aturan data

| Hal | Aturan | Contoh |
| --- | --- | --- |
| Uang | Integer rupiah, tanpa desimal | `12500000` |
| Berat | Integer gram | `28500` |
| Tanggal-waktu | ISO 8601 dengan zona +07:00 | `"2026-10-14T21:30:00+07:00"` |
| Tanggal saja | `YYYY-MM-DD` | `"2026-10-14"` |
| ID | Integer, kecuali order memakai `order_number` | `42`, `"WEB-000123"` |
| Nama field | `snake_case` | `available_qty` |
| Nilai kosong | `null`, bukan string kosong | `"barcode": null` |
| Boolean | `true` / `false` | `"is_default": true` |

### Paginasi, pencarian, dan urutan

| Query | Tipe | Default | Aturan |
| --- | --- | --- | --- |
| `page` | integer | 1 | ≥ 1 |
| `per_page` | integer | 12 | 1–48 untuk publik, 1–100 untuk admin |
| `q` | string | — | Kata kunci, maks 100 karakter |
| `sort` | string | per endpoint | Nilai yang diizinkan ditulis di tiap endpoint |

Nilai di query string dan `multipart/form-data` (keduanya hanya membawa teks):

| Tipe | Dikirim sebagai | Contoh |
| --- | --- | --- |
| boolean | `1` / `0` (bukan `true`/`false`, karena rule `boolean` Laravel menolak string itu) | `?in_stock=1`, `remove_image=1` |
| daftar | Dipisah koma | `?status=paid,processing` |

Boolean di body JSON tetap `true` / `false`.

### Batas request (rate limit)

| Kelompok | Batas |
| --- | --- |
| `POST /auth/login`, `POST /auth/register`, `POST /auth/forgot-password` | 5 request/menit per IP + email |
| `POST /shipping/rates` | 20 request/menit per user |
| `POST /checkout` | 5 request/menit per user |
| Endpoint lain | 60 request/menit per user atau IP |

Saat terlampaui: `429 TOO_MANY_REQUESTS` dengan header `Retry-After` (detik).

### Status order website

| Status | Arti | Diubah oleh |
| --- | --- | --- |
| `pending_payment` | Order dibuat, menunggu pembayaran Xendit | Checkout |
| `paid` | Pembayaran diterima | Webhook Xendit PAID |
| `processing` | Diproses gudang di Jubelio (picking/packing) | Webhook Jubelio `update-salesorder` |
| `shipped` | Sudah diserahkan ke kurir, ada nomor resi | Webhook Jubelio `update-salesorder` |
| `completed` | Diterima customer / ditandai selesai di Jubelio | Webhook Jubelio `update-salesorder` |
| `expired` | Batas waktu bayar habis, order dibatalkan otomatis | Webhook Xendit EXPIRED / job cadangan |
| `cancelled` | Dibatalkan customer (sebelum bayar) atau dibatalkan di Jubelio | `POST /orders/{order_number}/cancel` / webhook Jubelio |

Transisi yang sah:

```
pending_payment → paid → processing → shipped → completed
pending_payment → expired
pending_payment → cancelled
paid / processing → cancelled   (hanya dari Jubelio, mis. pembatalan oleh admin gudang)
```

## Objek bersama

Objek di bawah dipakai berulang. Contoh JSON di file endpoint selalu mengikuti bentuk ini.

### Category

```json
{
  "id": 3,
  "name": "Sepeda Listrik",
  "slug": "sepeda-listrik",
  "parent_id": null,
  "product_count": 18
}
```

### ProductSummary (kartu produk di daftar)

```json
{
  "id": 12,
  "slug": "sepeda-listrik-uwinfly-c1-pro",
  "name": "Sepeda Listrik Uwinfly C1 Pro",
  "category": { "id": 3, "name": "Sepeda Listrik", "slug": "sepeda-listrik" },
  "thumbnail_url": "https://jubelio.blob.core.windows.net/images/abc/thumb.jpg",
  "price_min": 6750000,
  "price_max": 7250000,
  "total_available_qty": 9,
  "in_stock": true,
  "variant_count": 3
}
```

### Variant

```json
{
  "id": 31,
  "sku": "UWF-C1PRO-HTM",
  "name": "Sepeda Listrik Uwinfly C1 Pro - Hitam",
  "variation_values": [{ "label": "Warna", "value": "Hitam" }],
  "price": 6750000,
  "available_qty": 4,
  "in_stock": true,
  "package_weight": 32000,
  "image_url": "https://jubelio.blob.core.windows.net/images/abc/hitam.jpg"
}
```

### ProductDetail

```json
{
  "id": 12,
  "slug": "sepeda-listrik-uwinfly-c1-pro",
  "name": "Sepeda Listrik Uwinfly C1 Pro",
  "category": { "id": 3, "name": "Sepeda Listrik", "slug": "sepeda-listrik" },
  "brand_name": "Uwinfly",
  "description_html": "<p>Motor 500W, baterai 48V 12Ah, jarak tempuh hingga 45 km.</p>",
  "sell_unit": "Unit",
  "min_qty": 1,
  "max_qty": 2,
  "package_dimensions": { "length": 150, "width": 30, "height": 80 },
  "images": [
    { "id": 101, "url": "https://jubelio.blob.core.windows.net/images/abc/1.jpg", "thumbnail_url": "https://jubelio.blob.core.windows.net/images/abc/1-thumb.jpg", "variant_id": null, "sort_order": 0 }
  ],
  "variations": [{ "label": "Warna", "values": ["Hitam", "Putih"] }],
  "variants": [ "Variant", "Variant" ],
  "price_min": 6750000,
  "price_max": 7250000,
  "in_stock": true,
  "updated_at": "2026-10-14T20:15:00+07:00"
}
```

`variants` berisi objek **Variant**. `package_dimensions` dalam cm.

### CartItem dan Cart

```json
{
  "items": [
    {
      "id": 5,
      "variant": {
        "id": 31,
        "sku": "UWF-C1PRO-HTM",
        "name": "Sepeda Listrik Uwinfly C1 Pro - Hitam",
        "variation_values": [{ "label": "Warna", "value": "Hitam" }],
        "price": 6750000,
        "available_qty": 4,
        "in_stock": true,
        "package_weight": 32000,
        "image_url": "https://jubelio.blob.core.windows.net/images/abc/hitam.jpg"
      },
      "product": { "id": 12, "slug": "sepeda-listrik-uwinfly-c1-pro", "name": "Sepeda Listrik Uwinfly C1 Pro", "min_qty": 1, "max_qty": 2 },
      "qty": 1,
      "subtotal": 6750000,
      "weight_total": 32000,
      "issue": null
    }
  ],
  "summary": {
    "item_count": 1,
    "total_qty": 1,
    "subtotal": 6750000,
    "total_weight": 32000,
    "has_issue": false
  }
}
```

`issue` bernilai `null` atau salah satu: `"OUT_OF_STOCK"` (stok 0), `"QTY_EXCEEDS_STOCK"` (qty > stok), `"PRODUCT_INACTIVE"` (dinonaktifkan di Jubelio). Harga selalu harga terbaru; perubahan harga antara layar checkout dan tombol Bayar ditangkap ORD-01 (`PRICE_CHANGED`). Checkout ditolak selama `has_issue` = `true`.

### Address

```json
{
  "id": 7,
  "label": "Rumah",
  "recipient_name": "Budi Santoso",
  "phone": "081234567890",
  "address_line": "Jl. Melati No. 12, RT 03/RW 05",
  "province": { "id": "32", "name": "JAWA BARAT" },
  "city": { "id": "3216", "name": "KAB. BEKASI" },
  "district": { "id": "321612", "name": "TAMBUN UTARA" },
  "subdistrict": { "id": "3216122004", "name": "Satriajaya" },
  "post_code": "17565",
  "is_default": true,
  "created_at": "2026-10-14T21:00:00+07:00",
  "updated_at": "2026-10-14T21:00:00+07:00"
}
```

ID wilayah mengikuti endpoint Region Jubelio (string). Response REG-04 (kelurahan) menambah field `post_code` (boleh `null`).

### ShippingRate

```json
{
  "rate_id": "rt_9f2c1a7e",
  "courier_code": "jne",
  "courier_name": "JNE",
  "service_code": "JTR",
  "service_name": "JNE Trucking",
  "service_type": "cargo",
  "cost": 185000,
  "insurance_cost": 0,
  "etd_min_days": 3,
  "etd_max_days": 5,
  "etd_text": "3–5 hari"
}
```

`service_type`: `instant` | `same_day` | `regular` | `cargo`. `rate_id` berlaku 30 menit dan dipakai saat checkout.

### OrderSummary (daftar pesanan)

```json
{
  "order_number": "WEB-000123",
  "status": "pending_payment",
  "status_label": "Menunggu pembayaran",
  "item_count": 1,
  "first_item": { "name": "Sepeda Listrik Uwinfly C1 Pro - Hitam", "image_url": "https://jubelio.blob.core.windows.net/images/abc/hitam.jpg" },
  "grand_total": 6935000,
  "payment_expires_at": "2026-10-14T23:30:00+07:00",
  "created_at": "2026-10-14T21:30:00+07:00"
}
```

### OrderDetail

```json
{
  "order_number": "WEB-000123",
  "status": "shipped",
  "status_label": "Dikirim",
  "items": [
    {
      "id": 88,
      "variant_id": 31,
      "sku": "UWF-C1PRO-HTM",
      "product_name": "Sepeda Listrik Uwinfly C1 Pro",
      "variant_name": "Hitam",
      "image_url": "https://jubelio.blob.core.windows.net/images/abc/hitam.jpg",
      "price": 6750000,
      "qty": 1,
      "weight_gram": 32000,
      "subtotal": 6750000
    }
  ],
  "shipping_address": {
    "recipient_name": "Budi Santoso",
    "phone": "081234567890",
    "address_line": "Jl. Melati No. 12, RT 03/RW 05",
    "subdistrict": "Satriajaya",
    "district": "TAMBUN UTARA",
    "city": "KAB. BEKASI",
    "province": "JAWA BARAT",
    "post_code": "17565"
  },
  "shipping": {
    "courier_code": "jne",
    "courier_name": "JNE",
    "service_code": "JTR",
    "service_name": "JNE Trucking",
    "tracking_no": "JT1234567890",
    "etd_text": "3–5 hari"
  },
  "amounts": {
    "subtotal": 6750000,
    "shipping_cost": 185000,
    "insurance_cost": 0,
    "discount": 0,
    "tax": 0,
    "grand_total": 6935000
  },
  "payment": {
    "status": "PAID",
    "invoice_url": null,
    "payment_method": "BANK_TRANSFER",
    "payment_channel": "BCA",
    "expires_at": "2026-10-14T23:30:00+07:00",
    "paid_at": "2026-10-14T21:42:10+07:00"
  },
  "note": "Tolong dibungkus kardus tebal",
  "status_history": [
    { "status": "pending_payment", "label": "Menunggu pembayaran", "at": "2026-10-14T21:30:00+07:00" },
    { "status": "paid", "label": "Dibayar", "at": "2026-10-14T21:42:10+07:00" },
    { "status": "processing", "label": "Diproses gudang", "at": "2026-10-15T09:10:00+07:00" },
    { "status": "shipped", "label": "Dikirim", "at": "2026-10-15T15:05:00+07:00" }
  ],
  "can_pay": false,
  "can_cancel": false,
  "payment_expires_at": "2026-10-14T23:30:00+07:00",
  "created_at": "2026-10-14T21:30:00+07:00"
}
```

`payment.invoice_url` hanya terisi saat status `pending_payment` dan invoice belum kedaluwarsa. `can_pay` dan `can_cancel` = `true` hanya saat `pending_payment`.

### ArticleSummary dan Article

```json
{
  "id": 4,
  "slug": "cara-merawat-baterai-sepeda-listrik",
  "title": "Cara Merawat Baterai Sepeda Listrik",
  "image_url": "https://<domain>/storage/articles/baterai.jpg",
  "excerpt": "Lima kebiasaan sederhana agar baterai lithium awet hingga 3 tahun.",
  "published_at": "2026-10-10T08:00:00+07:00"
}
```

**Article** = ArticleSummary + `"body_html"` + `"author": { "name": "Admin Saeful Askar" }` + `"updated_at"`. `excerpt` dibuat otomatis dari 160 karakter pertama `body` (tanpa tag HTML).

## Indeks endpoint

Total **58 endpoint** milik website: 53 dipanggil frontend dan 5 webhook masuk. Empat endpoint admin bertanda **(v1.1)** — ADM-01, ADM-15, ADM-16, ADM-17 — ditunda sesuai PRD §9, sehingga versi 1 berisi **54 endpoint** (49 frontend + 5 webhook). Kontraknya tetap disimpan di `admin.md` sebagai acuan v1.1. Ditambah 18 kelompok panggilan keluar (INT-JB-01 – INT-JB-14, INT-JS-01, INT-XD-01 – INT-XD-03) dan aturan umumnya (INT-HDL) di `integrations.md`.

### Untuk frontend (`/api/v1`, kecuali CSRF)

| Kode | Method | Path | Auth | File | PRD |
| --- | --- | --- | --- | --- | --- |
| AUTH-01 | GET | `/sanctum/csrf-cookie` | Publik | auth.md | F-05 |
| AUTH-02 | POST | `/auth/register` | Publik | auth.md | F-05 |
| AUTH-03 | POST | `/auth/login` | Publik | auth.md | F-05 |
| AUTH-04 | POST | `/auth/logout` | Customer | auth.md | F-05 |
| AUTH-05 | GET | `/auth/me` | Customer | auth.md | F-05 |
| AUTH-06 | PUT | `/auth/profile` | Customer | auth.md | F-05 |
| AUTH-07 | PUT | `/auth/password` | Customer | auth.md | F-05 |
| AUTH-08 | POST | `/auth/forgot-password` | Publik | auth.md | F-05 |
| AUTH-09 | POST | `/auth/reset-password` | Publik | auth.md | F-05 |
| CAT-01 | GET | `/categories` | Publik | catalog.md | F-01 |
| CAT-02 | GET | `/products` | Publik | catalog.md | F-01, F-03, F-04 |
| CAT-03 | GET | `/products/{slug}` | Publik | catalog.md | F-02, F-03, F-04 |
| CART-01 | GET | `/cart` | Customer | cart.md | F-07 |
| CART-02 | POST | `/cart/items` | Customer | cart.md | F-07 |
| CART-03 | PATCH | `/cart/items/{id}` | Customer | cart.md | F-07 |
| CART-04 | DELETE | `/cart/items/{id}` | Customer | cart.md | F-07 |
| CART-05 | DELETE | `/cart` | Customer | cart.md | F-07 |
| CART-06 | GET | `/cart/count` | Publik | cart.md | F-07 |
| REG-01 | GET | `/regions/provinces` | Customer | addresses-regions.md | F-06 |
| REG-02 | GET | `/regions/cities` | Customer | addresses-regions.md | F-06 |
| REG-03 | GET | `/regions/districts` | Customer | addresses-regions.md | F-06 |
| REG-04 | GET | `/regions/subdistricts` | Customer | addresses-regions.md | F-06 |
| ADDR-01 | GET | `/addresses` | Customer | addresses-regions.md | F-06 |
| ADDR-02 | POST | `/addresses` | Customer | addresses-regions.md | F-06 |
| ADDR-03 | GET | `/addresses/{id}` | Customer | addresses-regions.md | F-06 |
| ADDR-04 | PUT | `/addresses/{id}` | Customer | addresses-regions.md | F-06 |
| ADDR-05 | DELETE | `/addresses/{id}` | Customer | addresses-regions.md | F-06 |
| ADDR-06 | PATCH | `/addresses/{id}/default` | Customer | addresses-regions.md | F-06 |
| SHIP-01 | POST | `/shipping/rates` | Customer | shipping.md | F-08 |
| ORD-01 | POST | `/checkout` | Customer | checkout-orders.md | F-09 |
| ORD-02 | GET | `/orders` | Customer | checkout-orders.md | F-12 |
| ORD-03 | GET | `/orders/{order_number}` | Customer | checkout-orders.md | F-12 |
| ORD-04 | GET | `/orders/{order_number}/payment` | Customer | checkout-orders.md | F-09, F-10 |
| ORD-05 | POST | `/orders/{order_number}/cancel` | Customer | checkout-orders.md | F-19 |
| ART-01 | GET | `/articles` | Publik | articles.md | F-14 |
| ART-02 | GET | `/articles/{slug}` | Publik | articles.md | F-14 |
| ADM-01 (v1.1) | GET | `/admin/dashboard` | Admin | admin.md | F-16, F-17 |
| ADM-02 | GET | `/admin/articles` | Admin | admin.md | F-15 |
| ADM-03 | POST | `/admin/articles` | Admin | admin.md | F-15 |
| ADM-04 | GET | `/admin/articles/{id}` | Admin | admin.md | F-15 |
| ADM-05 | POST | `/admin/articles/{id}` (`_method=PUT`) | Admin | admin.md | F-15 |
| ADM-06 | DELETE | `/admin/articles/{id}` | Admin | admin.md | F-15 |
| ADM-07 | PATCH | `/admin/articles/{id}/status` | Admin | admin.md | F-15 |
| ADM-08 | POST | `/admin/uploads/images` | Admin | admin.md | F-15 |
| ADM-09 | GET | `/admin/orders` | Admin | admin.md | F-16 |
| ADM-10 | GET | `/admin/orders/{order_number}` | Admin | admin.md | F-16 |
| ADM-11 | POST | `/admin/orders/{order_number}/push-jubelio` | Admin | admin.md | F-16 |
| ADM-12 | POST | `/admin/orders/{order_number}/refresh-payment` | Admin | admin.md | F-16 |
| ADM-13 | POST | `/admin/sync` | Admin | admin.md | F-17 |
| ADM-14 | GET | `/admin/sync-logs` | Admin | admin.md | F-17 |
| ADM-15 (v1.1) | GET | `/admin/webhook-events` | Admin | admin.md | F-16 |
| ADM-16 (v1.1) | POST | `/admin/webhook-events/{id}/retry` | Admin | admin.md | F-16 |
| ADM-17 (v1.1) | GET | `/admin/settings/integrations` | Admin | admin.md | F-16 |

### Webhook masuk (`/webhooks`)

| Kode | Method | Path | Pengirim | Event |
| --- | --- | --- | --- | --- |
| WH-01 | POST | `/webhooks/xendit/invoice` | Xendit | Invoice `PAID`, `SETTLED`, `EXPIRED` |
| WH-02 | POST | `/webhooks/jubelio/product` | Jubelio | `update-product` |
| WH-03 | POST | `/webhooks/jubelio/price` | Jubelio | `update-price` |
| WH-04 | POST | `/webhooks/jubelio/stock` | Jubelio | `update-qty` |
| WH-05 | POST | `/webhooks/jubelio/salesorder` | Jubelio | `update-salesorder` |

### Panggilan keluar (backend → layanan luar)

| Kode | Tujuan | Isi | File |
| --- | --- | --- | --- |
| INT-JB-01 … INT-JB-14 | Jubelio Omnichannel | Login, kategori, produk, berat, stok, data item, region, contact, sales order, setup | integrations.md |
| INT-JS-01 | Jubelio Shipment | Tarif ongkir | integrations.md |
| INT-XD-01 … INT-XD-03 | Xendit | Buat, ambil, kedaluwarsakan invoice | integrations.md |
| INT-HDL | Semua | Aturan umum retry, timeout, logging | integrations.md |

## Indeks kode error khusus

| `code` | HTTP | Endpoint |
| --- | --- | --- |
| `INVALID_CREDENTIALS` | 422 | AUTH-03 |
| `INVALID_CURRENT_PASSWORD` | 422 | AUTH-07 |
| `INVALID_RESET_TOKEN` | 422 | AUTH-09 |
| `PRODUCT_NOT_FOUND` | 404 | CAT-03, CART-02 |
| `VARIANT_NOT_FOUND` | 404 | CART-02 |
| `VARIANT_INACTIVE` | 422 | CART-02, CART-03 |
| `OUT_OF_STOCK` | 409 | CART-02, CART-03, ORD-01 |
| `QTY_LIMIT_EXCEEDED` | 422 | CART-02, CART-03 |
| `CART_ITEM_NOT_FOUND` | 404 | CART-03, CART-04 |
| `REGION_NOT_FOUND` | 422 | ADDR-02, ADDR-04 |
| `ADDRESS_NOT_FOUND` | 404 | ADDR-03 – ADDR-06, SHIP-01, ORD-01 |
| `ADDRESS_LIMIT_REACHED` | 422 | ADDR-02 |
| `CART_EMPTY` | 422 | SHIP-01, ORD-01 |
| `CART_HAS_ISSUES` | 409 | ORD-01 |
| `NO_SHIPPING_SERVICE` | 422 | SHIP-01 |
| `RATE_EXPIRED` | 409 | ORD-01 |
| `PRICE_CHANGED` | 409 | ORD-01 |
| `PROFILE_INCOMPLETE` | 422 | ORD-01 |
| `DUPLICATE_REQUEST` | 409 | ORD-01 |
| `ORDER_NOT_FOUND` | 404 | ORD-03 – ORD-05, ADM-10 – ADM-12 |
| `ORDER_NOT_PAYABLE` | 409 | ORD-04 |
| `ORDER_NOT_CANCELLABLE` | 409 | ORD-05 |
| `ARTICLE_NOT_FOUND` | 404 | ART-02, ADM-04 – ADM-07 |
| `SLUG_TAKEN` | 422 | ADM-03, ADM-05 |
| `ALREADY_SYNCED` | 409 | ADM-11 |
| `SYNC_ALREADY_RUNNING` | 409 | ADM-13 |
| `WEBHOOK_EVENT_NOT_FOUND` | 404 | ADM-16 (v1.1) |
| `INVALID_CALLBACK_TOKEN` | 401 | WH-01 |
| `INVALID_SIGNATURE` | 401 | WH-02 – WH-05 |
