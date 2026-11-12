# Webhook Masuk (Gapura dan Jubelio)

Webhook dipanggil server Gapura (DANA) dan Jubelio, bukan oleh frontend. Semua webhook mengikuti pola yang sama:

1. Verifikasi signature. Gagal → `401`, event tetap dicatat dengan `signature_valid = false`.
2. Simpan ke `webhook_events` dengan `idempotency_key` unik. Key yang sudah pernah diproses → balas `200` tanpa memproses lagi.
3. Balas `200` secepatnya (target < 1 detik).
4. Proses di queue (`ProcessGapuraWebhookJob` / `ProcessJubelioWebhookJob`), lalu ubah status event menjadi `processed`, `ignored`, atau `failed`.

Route webhook dikecualikan dari CSRF dan tidak memakai session. Base URL ada di `README.md`. Webhook Jubelio berada di bawah `/webhooks`; webhook Gapura (WH-01) berada di `/v1.0/debit/notify` karena DANA hanya mau memanggil path itu.

| Pengirim | Percobaan ulang bila bukan 200 | Verifikasi |
| --- | --- | --- |
| Gapura (DANA) | Dikirim ulang oleh DANA hingga 7 hari bila tidak dibalas `2005600` | Header `X-SIGNATURE` = tanda tangan RSA SHA-256 DANA, diverifikasi dengan public key DANA |
| Jubelio | Hingga 3 kali | Header `sign` = HMAC-SHA256 dari (body + secret key), dengan secret key sebagai kunci |

---

## WH-01 · Hasil pembayaran dari Gapura (Finish Notify)

### Endpoint

`POST /v1.0/debit/notify` · **Webhook (Gapura / DANA)**

Path ini ditetapkan DANA (format ASPI) dan dipakai DANA untuk menandatangani request, jadi route-nya berada di root, bukan di bawah `/webhooks`. URL lengkapnya dikirim ke DANA saat Create Order (`urlParams` bertipe `NOTIFICATION`, INT-GP-01) dan juga diisi di Merchant Portal DANA.

| Header | Wajib | Keterangan |
| --- | --- | --- |
| `X-SIGNATURE` | Ya | Tanda tangan RSA SHA-256 dari DANA (Base64), diverifikasi dengan **public key DANA** |
| `X-TIMESTAMP` | Ya | Waktu kirim, `YYYY-MM-DDTHH:mm:ss+07:00` |
| `X-PARTNER-ID`, `X-EXTERNAL-ID`, `CHANNEL-ID` | Ya | Identitas pesan dari DANA |
| `Content-Type` | Ya | `application/json` |

String yang ditandatangani: `POST:/v1.0/debit/notify:<hex sha256 dari body yang di-minify, huruf kecil>:<X-TIMESTAMP>`. Verifikasi dilakukan SDK resmi (`Dana\Webhook\v1\WebhookParser`) lewat `GapuraClient::parseNotification()`. Di sandbox SDK memakai public key DANA bawaannya; di produksi memakai file dari `GAPURA_DANA_PUBLIC_KEY_PATH`.

### User Story

Sebagai sistem, saya ingin tahu begitu pembayaran di DANA selesai atau order DANA ditutup karena kedaluwarsa, agar pesanan langsung ditandai lunas dan diteruskan ke gudang, atau dibatalkan dan stoknya dikembalikan.

### Request Body

Dikirim DANA. Field yang dipakai:

| Field | Tipe | Keterangan |
| --- | --- | --- |
| `originalPartnerReferenceNo` | string | = `order_number` (mis. `WEB-000123`) = `payments.partner_reference_no` |
| `originalReferenceNo` | string | ID order di DANA = `payments.gapura_reference_no` |
| `merchantId` | string | Harus sama dengan `GAPURA_MERCHANT_ID` |
| `amount` | object | `{ "value": "6935000.00", "currency": "IDR" }` — total tagihan |
| `latestTransactionStatus` | string | `00` lunas, `02` sedang diproses (pembayaran berhasil), `05` order ditutup (kedaluwarsa atau dibatalkan) |
| `transactionStatusDesc` | string | mis. `success` |
| `createdTime`, `finishedTime` | string | `YYYY-MM-DDTHH:mm:ss+07:00` |
| `additionalInfo.paymentInfo.paidTime` | string | Waktu bayar (saat `00`/`02`) |
| `additionalInfo.paymentInfo.payOptionInfos[]` | array | `payMethod` (mis. `VIRTUAL_ACCOUNT`, `NETWORK_PAY`, `CREDIT_CARD`) dan `payOption` (mis. `VIRTUAL_ACCOUNT_BCA`, `NETWORK_PAY_PG_OVO`) |
| `additionalInfo.extendInfo` | string (JSON) | Saat `05`: `{"closedReason":"…"}` |

```json
{
  "originalPartnerReferenceNo": "WEB-000123",
  "originalReferenceNo": "20261020111212800110166000000000001",
  "merchantId": "216620000000000000000",
  "amount": { "value": "6935000.00", "currency": "IDR" },
  "latestTransactionStatus": "00",
  "transactionStatusDesc": "success",
  "createdTime": "2026-10-20T10:30:00+07:00",
  "finishedTime": "2026-10-20T10:42:11+07:00",
  "additionalInfo": {
    "paymentInfo": {
      "cashierRequestId": "64c4fdc544a7edff9c1e51aebc80f2eb",
      "paidTime": "2026-10-20T10:42:10+07:00",
      "payOptionInfos": [
        { "payMethod": "VIRTUAL_ACCOUNT", "payOption": "VIRTUAL_ACCOUNT_BCA", "payAmount": { "value": "6935000.00", "currency": "IDR" } }
      ]
    }
  }
}
```

### Response Success

`200 OK` (juga untuk event duplikat dan event yang diabaikan). Bentuk balasan ditentukan DANA; bila tidak menerima `2005600`, DANA mengirim ulang hingga 7 hari.

```json
{
  "responseCode": "2005600",
  "responseMessage": "Successful"
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `INVALID_SIGNATURE` | `X-SIGNATURE` atau `X-TIMESTAMP` tidak ada, atau tanda tangan tidak cocok dengan body |
| 422 | `VALIDATION_ERROR` | `originalPartnerReferenceNo`, `originalReferenceNo`, atau `latestTransactionStatus` tidak ada |
| 500 | `SERVER_ERROR` | Gagal menyimpan event (DANA akan mengirim ulang) |

```json
{
  "message": "Signature webhook tidak valid.",
  "code": "INVALID_SIGNATURE"
}
```

### Acceptance Criteria

- [ ] `idempotency_key` = `gapura:{originalPartnerReferenceNo}:{latestTransactionStatus}`; notifikasi yang sama dua kali hanya diproses sekali.
- [ ] `00` atau `02` pada order `pending_payment`:
    - [ ] `payments.status = PAID`, `paid_amount`, `paid_at`, `payment_method` (`payMethod`), `payment_channel` (`payOption`) tersimpan.
    - [ ] Order → `paid`, `paid_at`, riwayat status (`source = gapura`).
    - [ ] Job `MarkSalesOrderPaidJob` memanggil INT-JB-11; gagal → retry 5 kali dengan jeda bertahap, lalu `jubelio_sync_status = failed`.
    - [ ] Email "Pembayaran diterima" dikirim ke customer.
- [ ] `05` pada order `pending_payment`:
    - [ ] `payments.status = EXPIRED`, order → `expired`, `cancel_reason = "Batas waktu pembayaran habis"`.
    - [ ] Job `CancelSalesOrderJob` memanggil INT-JB-12 agar stok kembali.
    - [ ] Email "Pesanan dibatalkan" dikirim ke customer.
- [ ] `05` untuk order yang sudah `cancelled` (dibatalkan customer lewat ORD-05) → diabaikan dengan aman, balas `2005600`.
- [ ] `amount.value` ≠ `payments.amount` (atau `merchantId` bukan milik kita) → order tidak diubah, event `failed` dan dicatat di log dengan level `warning` untuk dicek admin (daftar di ADM-15 menyusul di v1.1).
- [ ] `00`/`02` untuk order yang sudah `expired`/`cancelled` → order tidak diubah, event `failed` dengan pesan "Dibayar setelah dibatalkan — perlu refund/proses manual".
- [ ] `originalPartnerReferenceNo` tidak dikenal → event `ignored`, balas `2005600`.
- [ ] Job cadangan `ExpireUnpaidOrders` (tiap 5 menit) menjalankan alur `05` untuk order yang lewat `payment_expires_at` setelah memastikan status di DANA (INT-GP-02).

---

## WH-02 · Produk dibuat/diubah di Jubelio

### Endpoint

`POST /webhooks/jubelio/product` · **Webhook (Jubelio)** · action `update-product`

| Header | Wajib | Keterangan |
| --- | --- | --- |
| `sign` | Ya | HMAC-SHA256 dari body mentah (di-trim) + `JUBELIO_WEBHOOK_SECRET`, dengan secret sebagai kunci (contoh resmi Jubelio, `dist.yaml` bagian Webhook Signature) |
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

- [ ] Signature dihitung dari body mentah: `hash_hmac('sha256', trim(raw_body) . JUBELIO_WEBHOOK_SECRET, JUBELIO_WEBHOOK_SECRET)` dan dibandingkan dengan header `sign` memakai `hash_equals`.
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

> **Aktif sejak 3 Nov 2026**, dengan cakupan yang diputuskan client: pesanan yang sales order-nya **diselesaikan** di Jubelio (`POST /sales/orders/mark-as-complete`, yang juga menerbitkan faktur) menjadi `shipped` (Dikirim). `completed` (Selesai) diubah admin lewat ADM-29. Tidak ada email ke pelanggan. Status `processing`, pembatalan dari Jubelio, dan email "Pesanan dikirim" belum dipakai.

### Endpoint

`POST /webhooks/jubelio/salesorder` · **Webhook (Jubelio)** · action `update-salesorder`

Header sama dengan WH-02. Halaman pengaturan webhook Jubelio di akun toko tidak punya bagian Sales Order, jadi webhook ini belum pernah datang; status `shipped` saat ini selalu datang dari `orders:check-shipped` (setiap menit, jeda sekitar 1–2 menit; WS-000028 berubah 3 menit setelah dibayar). URL ini ikut didaftarkan oleh `php artisan jubelio:setup --register-webhooks` (INT-JB-14), atau diisi manual di Jubelio (Pengaturan → Developer → Webhook, bagian Sales Order).

### User Story

Sebagai customer, saya ingin status pesanan di website menjadi Dikirim saat gudang menyelesaikan pesanan di Jubelio, agar saya tahu pesanan saya sudah diproses.

### Request Body

Payload sales order dari Jubelio (`webhookSalesOrder` di `dist.yaml`). Hanya field berikut yang dipakai, untuk mencari pesanannya:

| Field | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `salesorder_id` | integer | Ya | = `orders.jubelio_salesorder_id` |
| `ref_no` | string | Tidak | = `order_number` (cadangan pencocokan) |
| `salesorder_no` | string | Tidak | = `order_number` bila `JUBELIO_USE_ORDER_NUMBER=true` (cadangan pencocokan) |

Status tidak dibaca dari payload: website membaca ulang sales order lewat INT-JB-13 (`GET /sales/orders/{id}`), karena payload webhook bisa tidak lengkap atau lebih lama dari keadaan sekarang.

```json
{
  "action": "update-salesorder",
  "salesorder_id": 193460,
  "salesorder_no": "WS-000027",
  "ref_no": "WS-000027",
  "status": "Completed",
  "invoice_no": "INV-000187421",
  "is_paid": true,
  "is_canceled": false
}
```

### Response Success

`200 OK`

```json
{
  "status": "ok"
}
```

Sales order dari channel lain (Shopee, dll.) juga dijawab `200` tetapi **tidak dicatat** di `webhook_events`: Jubelio mengirim webhook ini untuk semua channel, dan tabel itu akan penuh oleh pesanan yang bukan milik website.

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `INVALID_SIGNATURE` | Signature tidak cocok |
| 422 | `VALIDATION_ERROR` | `salesorder_id` tidak ada atau bukan angka |
| 500 | `SERVER_ERROR` | Gagal menyimpan event |

### Acceptance Criteria

- [x] Pesanan dicari lewat `salesorder_id`, cadangan `ref_no`/`salesorder_no`. Bukan pesanan website → `200` tanpa dicatat.
- [x] Pesanan yang tidak berstatus `paid`/`processing` → event `ignored`.
- [x] Sales order dibaca ulang dari Jubelio (INT-JB-13). Pesanan menjadi `shipped` bila **semua** terpenuhi: `marked_as_complete = true` (atau `internal_status = COMPLETED`), `invoice_no` terisi, dan `is_canceled` bukan `true`. Nilai ini dipastikan pada WS-000027 (3 Nov 2026): setelah mark-as-complete, Jubelio mengisi `marked_as_complete: true`, `internal_status`/`wms_status`/`channel_status` `COMPLETED`, `completed_date`, dan `invoice_no: "INV-000187421"`, sedangkan `tracking_no` tetap `null`.
- [x] Belum selesai di Jubelio → event `processed` tanpa perubahan status.
- [x] Perubahan dicatat di `order_status_histories` (`source = jubelio`, catatan "Diselesaikan di Jubelio, faktur INV-…"). `tracking_no` dan kurir ikut disimpan bila Jubelio mengisinya dan order belum punya.
- [x] Status hanya bergerak maju (`OrderStatusRecorder`); event berulang tidak mengubah apa pun.
- [x] Cadangan bila webhook tidak sampai: `orders:check-shipped` setiap menit (sejak 3 Nov 2026; sebelumnya 10 menit) menanyakan paling banyak 30 pesanan `paid`/`processing` berumur ≤ 60 hari, yang paling lama tidak ditanyakan lebih dulu.
- [x] Resi yang menyusul: pesanan `shipped` yang belum punya resi ikut ditanyakan oleh `orders:check-shipped` (sejak 12 Nov 2026), karena gudang bisa menyelesaikan sales order sebelum resi kurir masuk ke Jubelio. Hanya `tracking_no` (dan `jubelio_courier` bila kosong) yang diisi; status tidak berubah.
- [ ] Belum dipakai: `processing` dari status picking/packing, pembatalan dari Jubelio (`is_canceled`), dan email "Pesanan dikirim" (`OrderShippedMail`, template sudah ada).
