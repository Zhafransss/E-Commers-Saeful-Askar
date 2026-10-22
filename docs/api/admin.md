# Panel Admin

Semua endpoint di file ini wajib login dengan `role = admin` (middleware `EnsureUserIsAdmin`). Customer yang memanggilnya mendapat `403 FORBIDDEN`. Produk, harga, dan stok **tidak** dikelola di sini karena sumbernya Jubelio. Konvensi umum ada di `README.md`.

Versi 1 memuat ADM-09 – ADM-14 dan ADM-18 – ADM-23 (revisi client 22 Okt 2026). Pengelolaan artikel (ADM-02 – ADM-08) dihapus dari lingkup produk. ADM-01, ADM-15, ADM-16, dan ADM-17 ditunda ke v1.1 (PRD §9); di v1, order bermasalah ditangani lewat ADM-09 – ADM-12, dan webhook gagal diperiksa langsung di tabel `webhook_events` / log server.

Error yang berlaku untuk semua endpoint admin:

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 403 | `FORBIDDEN` | Login sebagai customer |

```json
{
  "message": "Anda tidak punya akses ke halaman ini.",
  "code": "FORBIDDEN"
}
```

---

## ADM-01 · Ringkasan dashboard (v1.1)

> **Ditunda ke v1.1** (PRD §9). Kontrak disimpan sebagai acuan; endpoint ini tidak dibuat di versi 1.

### Endpoint

`GET /api/v1/admin/dashboard` · **Admin**

### User Story

Sebagai admin, saya ingin melihat ringkasan pesanan website hari ini dan kesehatan integrasi dalam satu layar, agar segera tahu bila ada order yang gagal masuk Jubelio atau sinkron yang macet.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "data": {
    "orders_today": {
      "pending_payment": 3,
      "paid": 2,
      "processing": 1,
      "shipped": 4,
      "completed": 0,
      "expired": 1,
      "cancelled": 0,
      "revenue_paid": 13870000
    },
    "needs_attention": {
      "orders_sync_failed": 1,
      "webhooks_failed": 0,
      "products_without_weight": 2
    },
    "last_sync": {
      "products": { "status": "success", "finished_at": "2026-10-14T21:00:00+07:00", "items_processed": 25 },
      "stock": { "status": "success", "finished_at": "2026-10-14T21:15:00+07:00", "items_processed": 52 },
      "categories": { "status": "success", "finished_at": "2026-10-14T02:00:00+07:00", "items_processed": 6 }
    },
    "generated_at": "2026-10-14T21:20:00+07:00"
  }
}
```

### Response Failed

Hanya error umum admin (401, 403).

### Acceptance Criteria

- [ ] "Hari ini" dihitung dari 00:00 sampai 23:59 WIB.
- [ ] `revenue_paid` = jumlah `grand_total` order yang dibayar hari ini.
- [ ] `orders_sync_failed` = order dengan `jubelio_sync_status = failed`.
- [ ] `products_without_weight` = varian aktif dengan `package_weight` 0 atau `null` (perlu diperbaiki di Jubelio agar ongkir benar).

---

## ADM-09 · Daftar pesanan website (admin)

### Endpoint

`GET /api/v1/admin/orders` · **Admin**

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `q` | string | Tidak | Nomor order, nama/email/HP customer, nomor resi |
| `status` | string | Tidak | Satu atau beberapa status order dipisah koma |
| `jubelio_sync_status` | string | Tidak | `pending` \| `synced` \| `failed` |
| `date_from` | string | Tidak | `YYYY-MM-DD`, berdasarkan `created_at` |
| `date_to` | string | Tidak | `YYYY-MM-DD`, ≥ `date_from` |
| `page` | integer | Tidak | Default 1 |
| `per_page` | integer | Tidak | Default 20, maks 100 |

### User Story

Sebagai admin, saya ingin melihat semua pesanan dari website beserta status pembayaran dan status masuk ke Jubelio, agar bisa menindaklanjuti pesanan yang bermasalah.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "data": [
    {
      "order_number": "WEB-000124",
      "status": "pending_payment",
      "status_label": "Menunggu pembayaran",
      "customer": { "id": 22, "name": "Siti Aminah", "email": "siti@example.com", "phone": "081377788899" },
      "grand_total": 3585000,
      "item_count": 1,
      "payment_status": "PENDING",
      "jubelio_sync_status": "failed",
      "jubelio_salesorder_no": null,
      "tracking_no": null,
      "created_at": "2026-10-14T21:35:00+07:00"
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 12, "last_page": 1 }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 422 | `VALIDATION_ERROR` | Status/tanggal tidak valid |

### Acceptance Criteria

- [ ] Termasuk order `cancelled` karena gagal dibuat di Jubelio (agar bisa diperiksa).
- [ ] Urut `created_at` terbaru.

---

## ADM-10 · Detail pesanan (admin)

### Endpoint

`GET /api/v1/admin/orders/{order_number}` · **Admin**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `order_number` | path | string | mis. `WEB-000124` |

### User Story

Sebagai admin, saya ingin melihat rincian pesanan lengkap dengan data customer, order Gapura, sales order Jubelio, dan riwayat integrasinya, agar bisa menelusuri masalah.

### Request Body

Tidak ada.

### Response Success

`200 OK` — **OrderDetail** + data internal.

```json
{
  "data": {
    "order_number": "WEB-000124",
    "status": "pending_payment",
    "status_label": "Menunggu pembayaran",
    "customer": { "id": 22, "name": "Siti Aminah", "email": "siti@example.com", "phone": "081377788899" },
    "items": [
      { "id": 90, "variant_id": 41, "sku": "BAT-48V-20AH", "product_name": "Baterai Lithium 48V", "variant_name": "20Ah", "image_url": null, "price": 3400000, "qty": 1, "weight_gram": 9500, "subtotal": 3400000 }
    ],
    "shipping_address": {
      "recipient_name": "Siti Aminah",
      "phone": "081377788899",
      "address_line": "Jl. Kenanga No. 3",
      "subdistrict": "Karet",
      "district": "SETIABUDI",
      "city": "KOTA ADM. JAKARTA SELATAN",
      "province": "DKI JAKARTA",
      "post_code": "12920"
    },
    "shipping": { "courier_code": "sicepat", "courier_name": "SiCepat", "service_code": "REG", "service_name": "SiCepat Reguler", "tracking_no": null, "etd_text": "1–2 hari" },
    "amounts": { "subtotal": 3400000, "shipping_cost": 185000, "insurance_cost": 0, "discount": 0, "tax": 0, "grand_total": 3585000 },
    "payment": { "status": "PENDING", "checkout_url": "https://m.dana.id/d/portal/cashier?ticket=2026101421351280011016600000002", "payment_method": null, "payment_channel": null, "expires_at": "2026-10-14T23:35:00+07:00", "paid_at": null },
    "note": null,
    "status_history": [
      { "status": "pending_payment", "label": "Menunggu pembayaran", "at": "2026-10-14T21:35:00+07:00", "source": "system" }
    ],
    "integration": {
      "jubelio_salesorder_id": null,
      "jubelio_salesorder_no": null,
      "jubelio_sync_status": "failed",
      "jubelio_last_error": "SQLSTATE 23505",
      "gapura_reference_no": "20261014213512800110166000000000002",
      "partner_reference_no": "WEB-000124",
      "sync_logs": [
        { "id": 301, "type": "order_push", "status": "failed", "message": "SQLSTATE 23505", "started_at": "2026-10-14T21:35:01+07:00", "finished_at": "2026-10-14T21:35:03+07:00" }
      ]
    },
    "can_pay": true,
    "can_cancel": true,
    "payment_expires_at": "2026-10-14T23:35:00+07:00",
    "created_at": "2026-10-14T21:35:00+07:00"
  }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 404 | `ORDER_NOT_FOUND` | Nomor tidak ada |

### Acceptance Criteria

- [ ] Admin bisa membuka pesanan customer mana pun.
- [ ] `integration.sync_logs` memuat semua log `sync_logs` dengan `reference_id` = order ini, terbaru di atas.
- [ ] Kredensial atau token tidak pernah muncul di response.

---

## ADM-11 · Kirim ulang pesanan ke Jubelio

### Endpoint

`POST /api/v1/admin/orders/{order_number}/push-jubelio` · **Admin**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `order_number` | path | string | mis. `WEB-000124` |

### User Story

Sebagai admin, saya ingin mengirim ulang pesanan yang gagal masuk atau gagal dibatalkan di Jubelio, agar data di Jubelio sama dengan website tanpa input manual.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `action` | string | Tidak | `create` \| `mark_paid` \| `cancel`. Default: dipilih otomatis dari status order |

```json
{
  "action": "create"
}
```

### Response Success

`202 Accepted` — dikerjakan di queue.

```json
{
  "data": {
    "order_number": "WEB-000124",
    "action": "create",
    "sync_log_id": 305,
    "jubelio_sync_status": "pending"
  },
  "message": "Pesanan dikirim ulang ke Jubelio. Periksa status beberapa saat lagi."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 404 | `ORDER_NOT_FOUND` | Nomor tidak ada |
| 409 | `ALREADY_SYNCED` | Aksi yang diminta sudah tercermin di Jubelio |
| 422 | `VALIDATION_ERROR` | `action` tak dikenal atau tidak cocok dengan status order |

```json
{
  "message": "Pesanan ini sudah tercatat di Jubelio (SO-000981).",
  "code": "ALREADY_SYNCED"
}
```

### Acceptance Criteria

- [ ] Sebelum `create`, backend mencari sales order dengan `ref_no` = nomor order (GET `/sales/v2/orders/?q=`) agar tidak membuat duplikat.
- [ ] Pilihan otomatis: belum ada SO → `create`; status `paid` tapi SO belum lunas → `mark_paid`; status `expired`/`cancelled` tapi SO belum batal → `cancel`.
- [ ] Hasil tercatat di `sync_logs` dan terlihat di ADM-10.

---

## ADM-12 · Periksa ulang status pembayaran

### Endpoint

`POST /api/v1/admin/orders/{order_number}/refresh-payment` · **Admin**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `order_number` | path | string | mis. `WEB-000124` |

### User Story

Sebagai admin yang menerima laporan "sudah bayar tapi status belum berubah", saya ingin mengecek status pembayaran langsung ke DANA (Gapura), agar pesanan segera diproses bila memang sudah dibayar.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "data": {
    "order_number": "WEB-000124",
    "gapura_status": "00",
    "order_status_before": "pending_payment",
    "order_status_after": "paid",
    "changed": true
  },
  "message": "Pembayaran ditemukan. Pesanan ditandai dibayar."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 404 | `ORDER_NOT_FOUND` | Nomor tidak ada |
| 409 | `ORDER_HAS_NO_PAYMENT` | Pesanan tidak punya order pembayaran di Gapura (mis. checkout yang gagal sebelum order Gapura dibuat) |
| 502 | `INTEGRATION_ERROR` | Gapura tidak merespons |

```json
{
  "message": "Gapura tidak merespons. Coba lagi beberapa saat.",
  "code": "INTEGRATION_ERROR"
}
```

### Acceptance Criteria

- [ ] Memanggil INT-GP-02 dan, bila `PAID` (`00`/`02`) atau `EXPIRED` (`05`/`07`), menjalankan alur yang sama dengan WH-01. `gapura_status` berisi kode `latestTransactionStatus` DANA.
- [ ] Bila status tidak berubah, `changed = false` dan tidak ada perubahan data.
- [ ] Aman dipanggil berkali-kali (idempotent).

---

## ADM-13 · Jalankan sinkron manual

### Endpoint

`POST /api/v1/admin/sync` · **Admin**

### User Story

Sebagai admin yang baru mengubah produk di Jubelio, saya ingin menjalankan sinkron sekarang, agar website langsung menampilkan data terbaru tanpa menunggu jadwal.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `type` | string | Ya | `categories` \| `products` \| `stock` \| `full` |
| `full` | boolean | Tidak | Untuk `products`: `true` = sinkron penuh, `false` = hanya yang berubah sejak sinkron terakhir (default) |

```json
{
  "type": "products",
  "full": false
}
```

### Response Success

`202 Accepted` — dijalankan di queue.

```json
{
  "data": {
    "sync_log_id": 310,
    "type": "products",
    "status": "running",
    "started_at": "2026-10-14T21:50:00+07:00"
  },
  "message": "Sinkron produk dimulai."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 409 | `SYNC_ALREADY_RUNNING` | Sinkron jenis yang sama sedang berjalan |
| 422 | `VALIDATION_ERROR` | `type` tak dikenal |

```json
{
  "message": "Sinkron produk masih berjalan sejak 21.45. Tunggu hingga selesai.",
  "code": "SYNC_ALREADY_RUNNING",
  "sync_log_id": 309
}
```

### Acceptance Criteria

- [ ] Hanya satu sinkron per jenis berjalan bersamaan (atomic lock Laravel di cache `database`).
- [ ] `full` = kategori → produk → stok berurutan, masing-masing dengan baris `sync_logs` sendiri.
- [ ] Sinkron mematuhi batas 600 request/menit Jubelio.

---

## ADM-18 · Daftar pelanggan

`GET /api/v1/admin/users` · **Admin**. Hanya melihat data pelanggan (`role = customer`); tidak ada endpoint untuk mengubah atau menghapus.

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `q` | string | Tidak | Cari di nama, email, dan HP |
| `page`, `per_page` | integer | Tidak | Default 20, maks 100 |

`200 OK`, terbaru dulu: `{ "data": [{ "id", "name", "email", "phone", "orders_count", "paid_orders_count", "paid_total", "registered_at" }], "meta": {...} }`. `paid_*` menghitung pesanan berstatus `paid`, `processing`, `shipped`, `completed`. Password dan token tidak pernah dikirim.

## ADM-19 · Detail pelanggan

`GET /api/v1/admin/users/{id}` · **Admin**. Isinya seperti ADM-18, ditambah `email_verified_at`, `addresses` (objek Address) dan `recent_orders` (10 terbaru: `order_number`, `status`, `status_label`, `grand_total`, `created_at`). `404 USER_NOT_FOUND` untuk id yang tidak ada, atau milik admin.

## ADM-20 · Daftar produk dan varian

`GET /api/v1/admin/products` · **Admin**. Produk dibaca dari salinan lokal katalog Jubelio, semua varian ikut (termasuk yang nonaktif). Tidak ada endpoint untuk mengubah produk di sini.

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `q` | string | Tidak | Cari di nama produk, merek, SKU, dan nama varian |
| `status` | string | Tidak | `active` atau `inactive` |
| `page`, `per_page` | integer | Tidak | Default 20, maks 100 |

`200 OK`: `{ "data": [{ "id", "jubelio_item_group_id", "name", "slug", "brand_name", "category", "thumbnail_url", "is_active", "price_min", "price_max", "total_available_qty", "variants": [{ "id", "sku", "name", "price", "available_qty", "package_weight", "image_url", "is_active" }], "synced_at" }], "meta": {...} }`. Harga dan stok total hanya menghitung varian aktif.

## ADM-21 · Daftar pesanan dan resi

`GET /api/v1/admin/shipments` · **Admin**. Pesanan yang sudah dibayar (`paid`, `processing`, `shipped`, `completed`), terbaru dulu, dengan nomor resi yang tercatat.

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `q` | string | Tidak | No. pesanan, resi, nama penerima atau pelanggan, HP |
| `resi` | string | Tidak | `ada` atau `belum` |
| `page`, `per_page` | integer | Tidak | Default 20, maks 100 |

`200 OK`: `{ "data": [{ "order_number", "status", "status_label", "customer": {id, name, phone}, "recipient_name", "destination", "courier", "service", "jubelio_courier", "jubelio_salesorder_no", "tracking_no", "grand_total", "paid_at", "created_at" }], "meta": {...} }`. `courier` dan `service` adalah pilihan pelanggan saat checkout; `jubelio_courier` dan `tracking_no` diambil dari Jubelio.

## ADM-22 · Ambil resi dari Jubelio

`POST /api/v1/admin/shipments/refresh` · **Admin**. Tanpa body. Untuk pesanan `paid`/`processing`/`shipped` yang punya sales order Jubelio tetapi belum punya resi, membaca `tracking_no` dan `shipper` dari sales order itu dan menyimpannya. Paling banyak 30 pesanan sekali panggil, yang paling lama belum ditanyakan didahulukan. Resi tidak pernah diketik manual di sini.

`200 OK`: `{ "data": { "checked", "updated", "failed", "remaining" }, "message": "1 resi baru ditemukan di Jubelio." }`. Satu pesanan yang gagal dibaca tidak menghentikan yang lain.

## ADM-24 · Kirim invoice ke email pelanggan

`POST /api/v1/admin/orders/{order_number}/send-invoice` · **Admin**. Tanpa body. Mengantrekan email "Invoice pesanan" ke email pelanggan dengan invoice PDF terlampir. Hanya untuk pesanan yang sudah dibayar (`paid`, `processing`, `shipped`, `completed`); selain itu `409 ORDER_NOT_PAID`, dan `404 ORDER_NOT_FOUND` untuk nomor yang tidak ada.

`202 Accepted`: `{ "data": { "order_number", "sent_to" }, "message": "Invoice dikirim ke ... Email sampai dalam sekitar satu menit." }`. Pengiriman otomatis tidak lewat endpoint ini: email "Pembayaran diterima" sudah membawa invoice PDF saat pembayaran dikonfirmasi.

## ADM-23 · Periksa pembayaran semua pesanan menunggu

`POST /api/v1/admin/orders/refresh-payments` · **Admin**. Tanpa body. Menanyakan ke DANA (aturan yang sama dengan ADM-12) untuk pesanan `pending_payment` yang punya order pembayaran, terbaru dulu, paling banyak 40 sekali panggil.

`200 OK`: `{ "data": { "checked", "paid", "expired", "failed", "remaining" }, "message": "3 pesanan diperiksa: 1 dibayar, 1 kedaluwarsa." }`. `remaining` adalah pesanan menunggu yang belum ikut diperiksa; tekan lagi bila lebih dari 0.

---

## ADM-14 · Riwayat sinkron

### Endpoint

`GET /api/v1/admin/sync-logs` · **Admin**

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `type` | string | Tidak | `categories` \| `products` \| `stock` \| `order_push` \| `order_paid` \| `order_cancel` |
| `status` | string | Tidak | `running` \| `success` \| `failed` |
| `page` | integer | Tidak | Default 1 |
| `per_page` | integer | Tidak | Default 20, maks 100 |

### User Story

Sebagai admin, saya ingin melihat kapan sinkron terakhir berjalan dan apa yang gagal, agar bisa memastikan data website selalu sama dengan Jubelio.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "data": [
    {
      "id": 310,
      "type": "products",
      "status": "success",
      "reference_id": null,
      "items_processed": 25,
      "message": null,
      "started_at": "2026-10-14T21:50:00+07:00",
      "finished_at": "2026-10-14T21:51:12+07:00",
      "duration_seconds": 72
    },
    {
      "id": 301,
      "type": "order_push",
      "status": "failed",
      "reference_id": 124,
      "items_processed": 0,
      "message": "SQLSTATE 23505",
      "started_at": "2026-10-14T21:35:01+07:00",
      "finished_at": "2026-10-14T21:35:03+07:00",
      "duration_seconds": 2
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 87, "last_page": 5 }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 422 | `VALIDATION_ERROR` | Filter tak dikenal |

### Acceptance Criteria

- [ ] Urut `started_at` terbaru.
- [ ] Log lebih dari 90 hari dihapus otomatis oleh job terjadwal.

---

## ADM-15 · Daftar webhook masuk (v1.1)

> **Ditunda ke v1.1** (PRD §9). Kontrak disimpan sebagai acuan; endpoint ini tidak dibuat di versi 1.

### Endpoint

`GET /api/v1/admin/webhook-events` · **Admin**

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `provider` | string | Tidak | `gapura` \| `jubelio` |
| `status` | string | Tidak | `received` \| `processed` \| `ignored` \| `failed` |
| `event_type` | string | Tidak | mis. `notify`, `update-qty` |
| `page` | integer | Tidak | Default 1 |
| `per_page` | integer | Tidak | Default 20, maks 100 |

### User Story

Sebagai admin, saya ingin melihat webhook yang diterima dari Gapura dan Jubelio, agar bisa menemukan notifikasi yang gagal diproses.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "data": [
    {
      "id": 1201,
      "provider": "jubelio",
      "event_type": "update-salesorder",
      "status": "failed",
      "signature_valid": true,
      "summary": "SO-000981 · Shipped",
      "error": "Order WEB-000123 tidak ditemukan",
      "received_at": "2026-10-15T15:05:02+07:00",
      "processed_at": null
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 340, "last_page": 17 }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 422 | `VALIDATION_ERROR` | Filter tak dikenal |

### Acceptance Criteria

- [ ] Payload lengkap tidak dikirim di daftar (hanya `summary`); payload dilihat lewat log server bila perlu.
- [ ] Event dengan `signature_valid = false` ditampilkan agar upaya pemalsuan terlihat.

---

## ADM-16 · Proses ulang webhook (v1.1)

> **Ditunda ke v1.1** (PRD §9). Kontrak disimpan sebagai acuan; endpoint ini tidak dibuat di versi 1.

### Endpoint

`POST /api/v1/admin/webhook-events/{id}/retry` · **Admin**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `id` | path | integer | ID `webhook_events` |

### User Story

Sebagai admin, saya ingin memproses ulang webhook yang gagal setelah penyebabnya diperbaiki, agar status pesanan atau stok kembali benar.

### Request Body

Tidak ada.

### Response Success

`202 Accepted`

```json
{
  "data": { "id": 1201, "status": "received" },
  "message": "Webhook dijadwalkan untuk diproses ulang."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 404 | `WEBHOOK_EVENT_NOT_FOUND` | ID tidak ada |
| 409 | `CONFLICT` | Event sudah `processed`, atau signature tidak valid |

```json
{
  "message": "Webhook dengan signature tidak valid tidak bisa diproses ulang.",
  "code": "CONFLICT"
}
```

### Acceptance Criteria

- [ ] Hanya event `failed` atau `ignored` yang bisa diproses ulang.
- [ ] Pemrosesan ulang memakai job yang sama dengan pemrosesan pertama (hasil idempotent).

---

## ADM-17 · Status konfigurasi integrasi (v1.1)

> **Ditunda ke v1.1** (PRD §9). Kontrak disimpan sebagai acuan; endpoint ini tidak dibuat di versi 1.

### Endpoint

`GET /api/v1/admin/settings/integrations` · **Admin**

### User Story

Sebagai admin yang menyiapkan toko, saya ingin melihat apakah Jubelio, RajaOngkir, dan Gapura sudah terhubung dan URL webhook mana yang harus didaftarkan, agar setup tidak ada yang terlewat.

### Request Body

Tidak ada.

### Response Success

`200 OK` — hanya status, tanpa nilai rahasia.

```json
{
  "data": {
    "jubelio": {
      "configured": true,
      "login_ok": true,
      "token_expires_at": "2026-10-15T09:00:00+07:00",
      "store_id": "-1043",
      "store_name": "Website Saeful Askar",
      "location_id": 1,
      "location_name": "Gudang Tambun Utara",
      "tax_id": -1,
      "webhook_secret_set": true
    },
    "rajaongkir": {
      "configured": false,
      "driver": "stub",
      "origin": "Jl. Raya Pisangan No.1, Satriajaya, Tambun Utara, Kab. Bekasi 17566"
    },
    "gapura": {
      "configured": true,
      "mode": "sandbox",
      "private_key_found": true,
      "dana_public_key_set": false,
      "payment_duration_seconds": 600
    },
    "webhook_urls": {
      "gapura_notify": "https://<domain>/v1.0/debit/notify",
      "jubelio_update_product": "https://<domain>/webhooks/jubelio/product",
      "jubelio_update_price": "https://<domain>/webhooks/jubelio/price",
      "jubelio_update_qty": "https://<domain>/webhooks/jubelio/stock",
      "jubelio_update_salesorder": "https://<domain>/webhooks/jubelio/salesorder"
    }
  }
}
```

Nilai `store_id`, `location_id`, dan `tax_id` di contoh hanya ilustrasi.

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |

`login_ok = false` bila login Jubelio gagal (bukan error HTTP).

### Acceptance Criteria

- [ ] Tidak ada password, private key, atau token Jubelio di response.
- [ ] `login_ok` diperoleh dengan mencoba token yang ada / login ulang (INT-JB-01), hasil di-cache 5 menit.
- [ ] `store_name` dan `location_name` diambil dari Jubelio (INT-JB-14) untuk memastikan ID di `.env` benar.
- [ ] `mode` Gapura = nilai `GAPURA_ENV` (`sandbox` atau `production`); `private_key_found` = file di `GAPURA_PRIVATE_KEY_PATH` ada; `dana_public_key_set` hanya wajib `true` di produksi.
