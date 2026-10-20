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
- [ ] Order dicari lewat `salesorder_id`, cadangan `ref_no`; tidak ketemu → event `failed` dan dicatat di log (daftar di ADM-15 menyusul di v1.1).
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
