# Webhook Masuk (Xendit dan Jubelio)

Webhook dipanggil server Xendit/Jubelio, bukan oleh frontend. Semua webhook mengikuti pola yang sama:

1. Verifikasi token/signature. Gagal → `401`, event tetap dicatat dengan `signature_valid = false`.
2. Simpan ke `webhook_events` dengan `idempotency_key` unik. Key yang sudah pernah diproses → balas `200` tanpa memproses lagi.
3. Balas `200` secepatnya (target < 1 detik).
4. Proses di queue (`ProcessXenditWebhookJob` / `ProcessJubelioWebhookJob`), lalu ubah status event menjadi `processed`, `ignored`, atau `failed`.

Route webhook dikecualikan dari CSRF dan tidak memakai session. Base URL ada di `README.md`.

| Pengirim | Percobaan ulang bila bukan 200 | Verifikasi |
| --- | --- | --- |
| Xendit | Dikirim ulang otomatis oleh Xendit | Header `x-callback-token` = callback token di dashboard Xendit |
| Jubelio | Hingga 3 kali | Signature = SHA256(JSON body + webhook secret key) |

---

## WH-01 · Status invoice Xendit

### Endpoint

`POST /webhooks/xendit/invoice` · **Webhook (Xendit)**

| Header | Wajib | Keterangan |
| --- | --- | --- |
| `x-callback-token` | Ya | Harus sama dengan `XENDIT_CALLBACK_TOKEN` |
| `Content-Type` | Ya | `application/json` |

Didaftarkan di dashboard Xendit: Settings → Callbacks → Invoices paid / expired.

### User Story

Sebagai sistem, saya ingin tahu begitu invoice Xendit dibayar atau kedaluwarsa, agar pesanan langsung ditandai lunas dan diteruskan ke gudang, atau dibatalkan dan stoknya dikembalikan.

### Request Body

Dikirim Xendit. Field yang dipakai:

| Field | Tipe | Keterangan |
| --- | --- | --- |
| `id` | string | ID invoice Xendit = `payments.xendit_invoice_id` |
| `external_id` | string | = `order_number` (mis. `WEB-000123`) |
| `status` | string | `PAID`, `SETTLED`, atau `EXPIRED` |
| `amount` | number | Total tagihan |
| `paid_amount` | number | Jumlah yang dibayar (saat `PAID`/`SETTLED`) |
| `paid_at` | string | Waktu bayar (ISO 8601, UTC) |
| `payment_method` | string | mis. `BANK_TRANSFER`, `EWALLET`, `QR_CODE`, `CREDIT_CARD` |
| `payment_channel` | string | mis. `BCA`, `OVO`, `QRIS` |
| `updated` | string | Waktu perubahan terakhir |

```json
{
  "id": "6527a1b2c3d4e5f6a7b8c9d0",
  "external_id": "WEB-000123",
  "user_id": "5f1a2b3c4d5e6f7a8b9c0d1e",
  "status": "PAID",
  "merchant_name": "Saeful Askar",
  "amount": 6935000,
  "paid_amount": 6935000,
  "payer_email": "budi@example.com",
  "description": "Pesanan WEB-000123 - Toko Sepeda Listrik Saeful Askar",
  "currency": "IDR",
  "payment_method": "BANK_TRANSFER",
  "payment_channel": "BCA",
  "payment_destination": "8808999912345678",
  "paid_at": "2026-10-14T14:42:10.000Z",
  "created": "2026-10-14T14:30:00.000Z",
  "updated": "2026-10-14T14:42:11.000Z"
}
```

### Response Success

`200 OK` (juga untuk event duplikat dan event yang diabaikan, agar Xendit berhenti mengirim ulang).

```json
{
  "received": true
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `INVALID_CALLBACK_TOKEN` | Header tidak ada atau tidak cocok |
| 422 | `VALIDATION_ERROR` | `id`, `external_id`, atau `status` tidak ada |
| 500 | `SERVER_ERROR` | Gagal menyimpan event (Xendit akan mengirim ulang) |

```json
{
  "message": "Callback token tidak valid.",
  "code": "INVALID_CALLBACK_TOKEN"
}
```

### Acceptance Criteria

- [ ] `idempotency_key` = `xendit:{id}:{status}`; callback yang sama dua kali hanya diproses sekali.
- [ ] `PAID` atau `SETTLED` pada order `pending_payment`:
    - [ ] `payments.status = PAID`, `paid_at`, `payment_method`, `payment_channel` tersimpan.
    - [ ] Order → `paid`, `paid_at`, riwayat status (`source = xendit`).
    - [ ] Job `MarkSalesOrderPaidJob` memanggil INT-JB-11; gagal → retry 5 kali dengan jeda bertahap, lalu `jubelio_sync_status = failed`.
    - [ ] Email "Pembayaran diterima" dikirim ke customer.
- [ ] `EXPIRED` pada order `pending_payment`:
    - [ ] `payments.status = EXPIRED`, order → `expired`, `cancel_reason = "Batas waktu pembayaran habis"`.
    - [ ] Job `CancelSalesOrderJob` memanggil INT-JB-12 agar stok kembali.
    - [ ] Email "Pesanan dibatalkan" dikirim ke customer.
- [ ] `paid_amount` ≠ `amount` → order tidak diubah, event `failed`, muncul di ADM-15 untuk dicek admin.
- [ ] `PAID` untuk order yang sudah `expired`/`cancelled` → order tidak diubah, event `failed` dengan pesan "Dibayar setelah dibatalkan — perlu refund/proses manual".
- [ ] `external_id` tidak dikenal → event `ignored`, balas 200.
- [ ] Job cadangan `ExpireUnpaidOrders` (tiap 5 menit) menjalankan alur `EXPIRED` untuk order yang lewat `payment_expires_at` setelah memastikan status invoice di Xendit (INT-XD-02).

---

## WH-02 · Produk dibuat/diubah di Jubelio

### Endpoint

`POST /webhooks/jubelio/product` · **Webhook (Jubelio)** · action `update-product`

| Header | Wajib | Keterangan |
| --- | --- | --- |
| Header signature Jubelio | Ya | Nama header dipastikan saat uji coba (di docs hanya ada di gambar contoh) |
| `Content-Type` | Ya | `application/json` |

Didaftarkan di Jubelio: Pengaturan → Developer → Webhook, atau INT-JB-14 (`POST /systemsetting/webhook`).

### User Story

Sebagai sistem, saya ingin segera mengambil ulang data produk setelah admin mengubahnya di Jubelio, agar nama, deskripsi, gambar, varian, dan berat di website sama persis dengan Jubelio.

### Request Body

```json
{
  "action": "update-product",
  "item_group_id": 1931,
  "item_group_name": "Sepeda Listrik Uwinfly C1 Pro"
}
```

| Field | Tipe | Keterangan |
| --- | --- | --- |
| `action` | string | `update-product` |
| `item_group_id` | integer | = `products.jubelio_item_group_id` |
| `item_group_name` | string | Nama produk (hanya untuk log) |

### Response Success

`200 OK`

```json
{
  "status": "ok"
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `INVALID_SIGNATURE` | Signature tidak cocok |
| 422 | `VALIDATION_ERROR` | `item_group_id` tidak ada |
| 500 | `SERVER_ERROR` | Gagal menyimpan event (Jubelio mengirim ulang) |

```json
{
  "message": "Signature webhook tidak valid.",
  "code": "INVALID_SIGNATURE"
}
```

### Acceptance Criteria

- [ ] Signature dihitung dari body mentah: `sha256(raw_body + JUBELIO_WEBHOOK_SECRET)` dan dibandingkan dengan `hash_equals`.
- [ ] `idempotency_key` = `jubelio:update-product:` + SHA256 body.
- [ ] Job `SyncProductGroupJob` memanggil INT-JB-04 dan INT-JB-05, lalu memperbarui `products`, `product_variants`, `product_images`.
- [ ] Varian yang hilang dari Jubelio → `is_active = false` (tidak dihapus, agar order lama tetap terbaca).
- [ ] Produk yang diarsipkan/nonaktif di Jubelio → `products.is_active = false` dan hilang dari CAT-02.
- [ ] Produk baru (belum ada di database) dibuat lengkap dengan slug baru.
- [ ] Perubahan terlihat di CAT-03 paling lambat 1 menit setelah webhook diterima.

---

## WH-03 · Harga diubah di Jubelio

### Endpoint

`POST /webhooks/jubelio/price` · **Webhook (Jubelio)** · action `update-price`

Header sama dengan WH-02.

### User Story

Sebagai sistem, saya ingin memperbarui harga begitu admin mengubahnya di Jubelio, agar customer selalu melihat dan membayar harga yang benar.

### Request Body

```json
{
  "action": "update-price",
  "item_group_id": 1931,
  "item_group_name": "Sepeda Listrik Uwinfly C1 Pro"
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

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `INVALID_SIGNATURE` | Signature tidak cocok |
| 422 | `VALIDATION_ERROR` | `item_group_id` tidak ada |
| 500 | `SERVER_ERROR` | Gagal menyimpan event |

### Acceptance Criteria

- [ ] Harga varian diambil ulang (INT-JB-04): pakai harga toko internal website dari `product_skus[].prices` bila ada untuk `JUBELIO_STORE_ID`, selain itu `sell_price`.
- [ ] Harga di keranjang customer otomatis mengikuti (keranjang tidak menyimpan harga).
- [ ] Order yang sudah dibuat tidak berubah harganya.
- [ ] `item_group_id` yang belum ada di database diperlakukan seperti WH-02 (produk baru diambil lengkap).

---

## WH-04 · Stok berubah di Jubelio

### Endpoint

`POST /webhooks/jubelio/stock` · **Webhook (Jubelio)** · action `update-qty`

Header sama dengan WH-02.

### User Story

Sebagai sistem, saya ingin memperbarui stok setiap kali stok berubah di Jubelio (termasuk karena order Shopee, transfer, atau retur), agar website tidak menjual barang yang sudah habis.

### Request Body

```json
{
  "action": "update-qty",
  "item_group_id": 1931,
  "item_group_name": "Sepeda Listrik Uwinfly C1 Pro",
  "item_ids": [2976, 2977],
  "location_id": 1
}
```

| Field | Tipe | Keterangan |
| --- | --- | --- |
| `item_ids` | array integer | = `product_variants.jubelio_item_id` yang stoknya berubah |
| `location_id` | integer | Gudang tempat stok berubah |

### Response Success

`200 OK`

```json
{
  "status": "ok"
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `INVALID_SIGNATURE` | Signature tidak cocok |
| 422 | `VALIDATION_ERROR` | `item_ids` kosong |
| 500 | `SERVER_ERROR` | Gagal menyimpan event |

### Acceptance Criteria

- [ ] `location_id` ≠ `JUBELIO_LOCATION_ID` → event `ignored`.
- [ ] Job `SyncStockJob` mengambil stok terbaru item tersebut (INT-JB-06) dan memperbarui `product_variants.available_qty`.
- [ ] Stok di CAT-02/CAT-03 dan `issue` keranjang terbarui paling lambat 1 menit.
- [ ] Rekonsiliasi stok terjadwal (tiap 15 menit) tetap berjalan sebagai cadangan bila webhook terlewat.

---

## WH-05 · Sales order berubah di Jubelio

### Endpoint

`POST /webhooks/jubelio/salesorder` · **Webhook (Jubelio)** · action `update-salesorder`

Header sama dengan WH-02.

### User Story

Sebagai customer, saya ingin status pesanan dan nomor resi di website ikut berubah saat gudang memproses dan mengirim pesanan di Jubelio, agar saya tahu kapan barang sampai.

### Request Body

Payload sales order lengkap dari Jubelio. Field yang dipakai:

| Field | Tipe | Keterangan |
| --- | --- | --- |
| `salesorder_id` | integer | = `orders.jubelio_salesorder_id` |
| `salesorder_no` | string | Disimpan ke `orders.jubelio_salesorder_no` |
| `store_id` | string | Hanya diproses bila = `JUBELIO_STORE_ID` |
| `ref_no` | string | = `order_number` (cadangan pencocokan) |
| `is_paid` | boolean | Status lunas di Jubelio |
| `is_canceled` | boolean | Dibatalkan di Jubelio |
| `internal_status` | string | mis. `PROCESSING`, `SHIPPED`, `COMPLETED` |
| `wms_status` | string | mis. `PAID`, `READY_TO_PICK`, `READY_TO_SHIP` |
| `status` | string | mis. `Created`, `In Progress` |
| `tracking_no` | string | Nomor resi |
| `courier` / `shipper` | string | Kurir yang dipakai gudang |
| `marked_as_complete` | string | Penanda selesai |
| `last_modified` | string | Dipakai menolak event lama |

```json
{
  "action": "update-salesorder",
  "salesorder_id": 98112,
  "salesorder_no": "SO-000981",
  "source": "INTERNAL",
  "store_id": "-1043",
  "store_name": "Website Saeful Askar",
  "status": "In Progress",
  "ref_no": "WEB-000123",
  "contact_id": 5512,
  "customer_name": "Budi Santoso",
  "grand_total": 6935000,
  "is_paid": true,
  "is_canceled": false,
  "internal_status": "SHIPPED",
  "wms_status": "READY_TO_SHIP",
  "courier": "JNE",
  "shipper": "JNE Trucking",
  "tracking_no": "JT1234567890",
  "location_id": 1,
  "last_modified": "2026-10-15T08:05:00.000Z",
  "items": [
    { "salesorder_detail_id": 1, "item_id": 2976, "item_code": "UWF-C1PRO-HTM", "qty_in_base": 1, "price": 6750000, "amount": 6750000 }
  ]
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

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `INVALID_SIGNATURE` | Signature tidak cocok |
| 422 | `VALIDATION_ERROR` | `salesorder_id` tidak ada |
| 500 | `SERVER_ERROR` | Gagal menyimpan event |

### Acceptance Criteria

- [ ] Order dari toko lain (Shopee, dll.) → event `ignored`.
- [ ] Order dicari lewat `salesorder_id`, cadangan `ref_no`; tidak ketemu → event `failed` (terlihat di ADM-15).
- [ ] Event dengan `last_modified` lebih lama dari yang terakhir diproses untuk order itu → `ignored`.
- [ ] Pemetaan status (urutan pengecekan dari atas; nilai pasti dikonfirmasi dengan contoh webhook asli saat uji coba):

    | Kondisi di payload | Status website |
    | --- | --- |
    | `is_canceled = true` | `cancelled` (bila sebelumnya `paid`/`processing`, admin diberi tanda untuk refund) |
    | `marked_as_complete` terisi atau `internal_status = COMPLETED` | `completed` |
    | `tracking_no` terisi atau `internal_status = SHIPPED` | `shipped` |
    | `wms_status`/`internal_status` menunjukkan picking/packing | `processing` |
    | Lainnya | status tidak berubah |

- [ ] Status hanya bergerak maju (`paid` → `processing` → `shipped` → `completed`); event yang akan memundurkan status diabaikan.
- [ ] `tracking_no`, `courier`/`shipper`, dan `salesorder_no` disimpan ke order; setiap perubahan status dicatat di `order_status_histories` (`source = jubelio`).
- [ ] Email "Pesanan dikirim" (berisi nomor resi) dikirim saat status pertama kali menjadi `shipped`.
