# Checkout, Pembayaran, dan Pesanan

Checkout membuat order website, sales order **belum bayar** di Jubelio (agar stok terkunci di semua channel termasuk Shopee), dan order Gapura (DANA) dengan batas waktu bayar. Status order lalu berubah lewat webhook (lihat `webhooks.md`). Konvensi umum, objek **OrderSummary**/**OrderDetail**, dan tabel status ada di `README.md`.

Konfigurasi yang dipakai:

| Kunci | Nilai awal | Keterangan |
| --- | --- | --- |
| `GAPURA_PAYMENT_DURATION` | 7200 detik (2 jam) | Batas waktu bayar (`validUpTo`); menunggu keputusan final. Di sandbox dipotong menjadi 30 menit |
| `JUBELIO_STORE_ID` | store_id toko internal "Website Saeful Askar" | Diisi setelah toko dibuat di Jubelio |
| `JUBELIO_LOCATION_ID` | location_id gudang Tambun Utara | Satu gudang |
| `JUBELIO_TAX_ID` | ID pajak "No Tax" | Toko tidak memakai PPN |

---

## ORD-01 · Checkout (buat pesanan dan order Gapura)

### Endpoint

`POST /api/v1/checkout` · **Customer** · rate limit 5/menit per user

| Header | Wajib | Keterangan |
| --- | --- | --- |
| `Idempotency-Key` | Ya | UUID v4 baru per klik tombol "Bayar"; dipakai ulang bila request yang sama dikirim ulang |

### User Story

Sebagai customer, saya ingin menekan "Bayar" setelah memilih alamat dan kurir, lalu diarahkan ke halaman pembayaran DANA (Gapura), agar pesanan saya tercatat dan barang segera disiapkan setelah saya membayar.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `address_id` | integer | Ya | Alamat milik user |
| `rate_id` | string | Ya | Dari SHIP-01, belum kedaluwarsa, dibuat untuk user + alamat + isi keranjang yang sama |
| `note` | string | Tidak | Catatan untuk toko, maks 255 karakter |
| `expected_grand_total` | integer | Ya | Total yang dilihat customer di layar (subtotal + ongkir); dipakai mendeteksi perubahan harga |

Item pesanan diambil dari keranjang user, tidak dikirim frontend.

```json
{
  "address_id": 7,
  "rate_id": "rt_9f2c1a7e",
  "note": "Tolong dibungkus kardus tebal",
  "expected_grand_total": 6935000
}
```

### Response Success

`201 Created`. Frontend langsung mengarahkan browser ke `payment.checkout_url`.

```json
{
  "data": {
    "order": {
      "order_number": "WEB-000123",
      "status": "pending_payment",
      "status_label": "Menunggu pembayaran",
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
        "tracking_no": null,
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
        "status": "PENDING",
        "checkout_url": "https://m.dana.id/d/portal/cashier?ticket=2026101421301280011016600000001",
        "payment_method": null,
        "payment_channel": null,
        "expires_at": "2026-10-14T23:30:00+07:00",
        "paid_at": null
      },
      "note": "Tolong dibungkus kardus tebal",
      "status_history": [
        { "status": "pending_payment", "label": "Menunggu pembayaran", "at": "2026-10-14T21:30:00+07:00" }
      ],
      "can_pay": true,
      "can_cancel": true,
      "payment_expires_at": "2026-10-14T23:30:00+07:00",
      "created_at": "2026-10-14T21:30:00+07:00"
    },
    "redirect_url": "https://m.dana.id/d/portal/cashier?ticket=2026101421301280011016600000001"
  }
}
```

### Response Failed

| HTTP | `code` | Kapan | Data tambahan |
| --- | --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login | — |
| 404 | `ADDRESS_NOT_FOUND` | Alamat tidak ada/milik user lain | — |
| 409 | `CART_HAS_ISSUES` | Ada item `OUT_OF_STOCK`/`QTY_EXCEEDS_STOCK`/`PRODUCT_INACTIVE` di keranjang | `cart` (objek Cart) |
| 409 | `OUT_OF_STOCK` | Cek stok langsung ke Jubelio menunjukkan stok sudah habis/kurang | `items` (varian + stok terbaru) |
| 409 | `RATE_EXPIRED` | `rate_id` lewat 30 menit, tidak dikenal, atau isi keranjang/alamat berubah sejak tarif dihitung | — |
| 409 | `PRICE_CHANGED` | `expected_grand_total` ≠ total yang dihitung server | `amounts` terbaru |
| 409 | `DUPLICATE_REQUEST` | `Idempotency-Key` sama sedang diproses | — |
| 422 | `CART_EMPTY` | Keranjang kosong | — |
| 422 | `PROFILE_INCOMPLETE` | User belum punya nomor HP | — |
| 422 | `VALIDATION_ERROR` | Field kosong/salah format, header `Idempotency-Key` tidak ada | `errors` |
| 429 | `TOO_MANY_REQUESTS` | Lebih dari 5/menit | — |
| 502 | `INTEGRATION_ERROR` | Jubelio atau Gapura gagal; pesanan tidak jadi dibuat | — |

```json
{
  "message": "Stok beberapa produk sudah berubah. Periksa kembali keranjang Anda.",
  "code": "OUT_OF_STOCK",
  "items": [
    { "variant_id": 31, "sku": "UWF-C1PRO-HTM", "name": "Sepeda Listrik Uwinfly C1 Pro - Hitam", "requested_qty": 1, "available_qty": 0 }
  ]
}
```

```json
{
  "message": "Harga atau ongkir berubah. Total baru Rp6.985.000.",
  "code": "PRICE_CHANGED",
  "amounts": { "subtotal": 6800000, "shipping_cost": 185000, "insurance_cost": 0, "discount": 0, "tax": 0, "grand_total": 6985000 }
}
```

```json
{
  "message": "Pesanan belum bisa dibuat karena sistem toko sedang sibuk. Tidak ada tagihan yang dibuat. Silakan coba lagi.",
  "code": "INTEGRATION_ERROR"
}
```

### Acceptance Criteria

Urutan proses di backend (`CheckoutService`):

1. [ ] Validasi input, kepemilikan alamat, keranjang tidak kosong, keranjang tanpa `issue`, user punya nomor HP.
2. [ ] Ambil rate dari cache dengan `rate_id`; tolak bila kedaluwarsa atau user/alamat/berat tidak sama.
3. [ ] Hitung ulang subtotal dari harga varian saat ini; bandingkan `subtotal + ongkir` dengan `expected_grand_total`.
4. [ ] Cek stok langsung ke Jubelio untuk setiap SKU (INT-JB-06); tolak `OUT_OF_STOCK` bila kurang, dan perbarui `available_qty` lokal.
5. [ ] Pastikan user punya contact Jubelio; bila belum, buat (INT-JB-09) dan simpan `jubelio_contact_id`.
6. [ ] Simpan `orders` (status `pending_payment`, `jubelio_sync_status = pending`), `order_items` (salinan nama, SKU, harga, berat), salinan alamat, dan `order_status_histories`, dalam satu transaksi.
7. [ ] Buat sales order belum bayar di Jubelio (INT-JB-10) dengan `ref_no = order_number`, `store_id`, `location_id`, `tax_id` No Tax, ongkir, kurir; simpan `jubelio_salesorder_id` dan `jubelio_sync_status = synced`.
8. [ ] Buat order Gapura (INT-GP-01) dengan `partnerReferenceNo = order_number`, `amount = grand_total`, `validUpTo` dari `GAPURA_PAYMENT_DURATION`; simpan ke `payments` dan `orders.payment_expires_at`.
9. [ ] Kosongkan keranjang user, lalu balas 201.

Penanganan gagal:

- [ ] Langkah 7 gagal → order ditandai `cancelled` dengan `cancel_reason = "Gagal membuat pesanan di Jubelio"`, keranjang **tidak** dikosongkan, balas 502.
- [ ] Langkah 8 gagal → batalkan sales order Jubelio (INT-JB-12), order `cancelled`, keranjang tidak dikosongkan, balas 502.
- [ ] Request kedua dengan `Idempotency-Key` yang sama setelah sukses mengembalikan response 201 yang sama persis, tanpa membuat order/SO/order Gapura baru (disimpan 24 jam).
- [ ] Dua klik "Bayar" bersamaan tidak pernah menghasilkan dua sales order di Jubelio.

Hasil yang dicek:

- [ ] Sales order muncul di Jubelio pada toko "Website Saeful Askar" dengan status belum bayar, item, alamat, kurir, ongkir, dan `ref_no` yang benar.
- [ ] `grand_total` order = `amount` order Gapura = `grand_total` sales order Jubelio.
- [ ] `order_number` berurutan dan unik (`WEB-000001`, `WEB-000002`, …).

---

## ORD-02 · Riwayat pesanan saya

### Endpoint

`GET /api/v1/orders` · **Customer**

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `status` | string | Tidak | Satu atau beberapa status dipisah koma, mis. `pending_payment` atau `paid,processing` |
| `page` | integer | Tidak | Default 1 |
| `per_page` | integer | Tidak | Default 10, maks 48 |

Pengelompokan tab yang disarankan untuk frontend:

| Tab | Nilai `status` |
| --- | --- |
| Belum dibayar | `pending_payment` |
| Diproses | `paid,processing` |
| Dikirim | `shipped` |
| Selesai | `completed` |
| Dibatalkan | `expired,cancelled` |

### User Story

Sebagai customer, saya ingin melihat daftar pesanan saya per status, agar tahu mana yang belum dibayar, sedang diproses, atau sudah dikirim.

### Request Body

Tidak ada.

### Response Success

`200 OK` — daftar **OrderSummary**, terbaru di atas.

```json
{
  "data": [
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
  ],
  "meta": { "current_page": 1, "per_page": 10, "total": 1, "last_page": 1 }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 422 | `VALIDATION_ERROR` | `status` berisi nilai tak dikenal |

```json
{
  "message": "Data yang dikirim tidak valid.",
  "code": "VALIDATION_ERROR",
  "errors": { "status": ["Status tidak dikenal: dikirim."] }
}
```

### Acceptance Criteria

- [ ] Hanya pesanan milik user yang login.
- [ ] Pesanan `cancelled` karena gagal dibuat di Jubelio (ORD-01 langkah 7–8) tidak ditampilkan.
- [ ] `status_label` dalam Bahasa Indonesia sesuai tabel status di `README.md`.

---

## ORD-03 · Detail pesanan

### Endpoint

`GET /api/v1/orders/{order_number}` · **Customer**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `order_number` | path | string | mis. `WEB-000123` |

### User Story

Sebagai customer, saya ingin melihat rincian pesanan, status terbaru, dan nomor resi, agar saya bisa memantau pengiriman sepeda atau baterai saya.

Juga dipakai halaman `checkout/finish` (kembali dari DANA) yang memanggil endpoint ini setiap 3 detik sampai status bukan `pending_payment` atau 60 detik berlalu.

### Request Body

Tidak ada.

### Response Success

`200 OK` — objek **OrderDetail** (contoh lengkap di `README.md`).

```json
{
  "data": {
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
    "amounts": { "subtotal": 6750000, "shipping_cost": 185000, "insurance_cost": 0, "discount": 0, "tax": 0, "grand_total": 6935000 },
    "payment": {
      "status": "PAID",
      "checkout_url": null,
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
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 404 | `ORDER_NOT_FOUND` | Nomor tidak ada atau milik user lain |

```json
{
  "message": "Pesanan tidak ditemukan.",
  "code": "ORDER_NOT_FOUND"
}
```

### Acceptance Criteria

- [ ] Pesanan milik user lain dibalas 404 (lewat `OrderPolicy`).
- [ ] `tracking_no` terisi setelah webhook `update-salesorder` membawa nomor resi.
- [ ] `status_history` urut waktu naik dan mencakup setiap perubahan status.
- [ ] Data item dan alamat adalah salinan saat checkout, tidak berubah walau katalog/alamat berubah.
- [ ] `payment.checkout_url` hanya terisi bila status `pending_payment` dan `payment_expires_at` belum lewat.

---

## ORD-04 · Lanjutkan pembayaran

### Endpoint

`GET /api/v1/orders/{order_number}/payment` · **Customer**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `order_number` | path | string | mis. `WEB-000123` |

### User Story

Sebagai customer yang menutup halaman pembayaran sebelum membayar, saya ingin membuka lagi tautan bayar dari riwayat pesanan, agar tetap bisa membayar sebelum batas waktu habis.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "data": {
    "order_number": "WEB-000123",
    "status": "PENDING",
    "checkout_url": "https://m.dana.id/d/portal/cashier?ticket=2026101421301280011016600000001",
    "amount": 6935000,
    "expires_at": "2026-10-14T23:30:00+07:00",
    "seconds_left": 5400
  }
}
```

### Response Failed

| HTTP | `code` | Kapan | Data tambahan |
| --- | --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login | — |
| 404 | `ORDER_NOT_FOUND` | Nomor tidak ada atau milik user lain | — |
| 409 | `ORDER_NOT_PAYABLE` | Order sudah dibayar, kedaluwarsa, atau dibatalkan | `order_status` |

```json
{
  "message": "Pesanan ini sudah tidak bisa dibayar karena batas waktu pembayaran habis.",
  "code": "ORDER_NOT_PAYABLE",
  "order_status": "expired"
}
```

### Acceptance Criteria

- [ ] Mengembalikan halaman bayar yang sudah ada (`payments.checkout_url`); tidak membuat order Gapura baru.
- [ ] Bila status lokal masih `PENDING` tapi `expires_at` sudah lewat, backend mengecek ke DANA (INT-GP-02) dan menjalankan alur kedaluwarsa bila memang `EXPIRED`, lalu membalas 409.
- [ ] Bila DANA ternyata sudah `PAID` (webhook terlambat), backend menjalankan alur bayar (sama dengan WH-01) lalu membalas 409 dengan `order_status = paid`.
- [ ] `seconds_left` ≥ 0 dan dipakai frontend untuk hitung mundur.

---

## ORD-05 · Batalkan pesanan yang belum dibayar

### Endpoint

`POST /api/v1/orders/{order_number}/cancel` · **Customer**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `order_number` | path | string | mis. `WEB-000123` |

### User Story

Sebagai customer yang berubah pikiran sebelum membayar, saya ingin membatalkan pesanan, agar tagihan ditutup dan stok kembali untuk pembeli lain.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `reason` | string | Tidak | Maks 255 karakter |

```json
{
  "reason": "Ingin ganti warna"
}
```

### Response Success

`200 OK` — objek **OrderDetail** dengan status `cancelled`.

```json
{
  "data": {
    "order_number": "WEB-000123",
    "status": "cancelled",
    "status_label": "Dibatalkan",
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
    "shipping": { "courier_code": "jne", "courier_name": "JNE", "service_code": "JTR", "service_name": "JNE Trucking", "tracking_no": null, "etd_text": "3–5 hari" },
    "amounts": { "subtotal": 6750000, "shipping_cost": 185000, "insurance_cost": 0, "discount": 0, "tax": 0, "grand_total": 6935000 },
    "payment": { "status": "EXPIRED", "checkout_url": null, "payment_method": null, "payment_channel": null, "expires_at": "2026-10-14T23:30:00+07:00", "paid_at": null },
    "note": "Tolong dibungkus kardus tebal",
    "status_history": [
      { "status": "pending_payment", "label": "Menunggu pembayaran", "at": "2026-10-14T21:30:00+07:00" },
      { "status": "cancelled", "label": "Dibatalkan", "at": "2026-10-14T21:50:00+07:00" }
    ],
    "can_pay": false,
    "can_cancel": false,
    "payment_expires_at": "2026-10-14T23:30:00+07:00",
    "created_at": "2026-10-14T21:30:00+07:00"
  },
  "message": "Pesanan dibatalkan."
}
```

### Response Failed

| HTTP | `code` | Kapan | Data tambahan |
| --- | --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login | — |
| 404 | `ORDER_NOT_FOUND` | Nomor tidak ada atau milik user lain | — |
| 409 | `ORDER_NOT_CANCELLABLE` | Status bukan `pending_payment`, atau DANA ternyata sudah `PAID` | `order_status` |
| 502 | `INTEGRATION_ERROR` | Gapura/Jubelio gagal; status tidak berubah | — |

```json
{
  "message": "Pesanan yang sudah dibayar tidak bisa dibatalkan dari website. Silakan hubungi toko.",
  "code": "ORDER_NOT_CANCELLABLE",
  "order_status": "paid"
}
```

### Acceptance Criteria

- [ ] Urutan: batalkan order Gapura (INT-GP-03) → batalkan sales order Jubelio (INT-JB-12) → status order `cancelled`, `cancelled_at`, `cancel_reason` → catat riwayat status.
- [ ] Bila DANA menolak karena order sudah dibayar, order **tidak** dibatalkan; backend menjalankan alur bayar dan membalas 409.
- [ ] Bila pembatalan di Jubelio gagal, order tetap `cancelled` di website, `jubelio_sync_status = failed`, dan tercatat di `sync_logs` untuk dikirim ulang (ADM-11); customer tetap mendapat 200.
- [ ] Stok varian di Jubelio kembali bertambah setelah sales order dibatalkan (diverifikasi lewat webhook `update-qty` berikutnya).
