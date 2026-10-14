# Panel Admin

Semua endpoint di file ini wajib login dengan `role = admin` (middleware `EnsureUserIsAdmin`). Customer yang memanggilnya mendapat `403 FORBIDDEN`. Produk, harga, dan stok **tidak** dikelola di sini karena sumbernya Jubelio. Konvensi umum ada di `README.md`.

Objek **AdminArticle**:

```json
{
  "id": 4,
  "title": "Cara Merawat Baterai Sepeda Listrik",
  "slug": "cara-merawat-baterai-sepeda-listrik",
  "image_url": "https://<domain>/storage/articles/baterai.jpg",
  "body_html": "<p>Baterai lithium paling awet bila diisi sebelum di bawah 20%…</p>",
  "status": "published",
  "published_at": "2026-10-10T08:00:00+07:00",
  "author": { "id": 1, "name": "Admin Saeful Askar" },
  "created_at": "2026-10-09T15:00:00+07:00",
  "updated_at": "2026-10-11T09:30:00+07:00"
}
```

`status`: `draft` | `published`. Artikel `published` dengan `published_at` di masa depan = terjadwal.

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

## ADM-01 · Ringkasan dashboard

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

## ADM-02 · Daftar artikel (admin)

### Endpoint

`GET /api/v1/admin/articles` · **Admin**

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `q` | string | Tidak | Cari judul |
| `status` | string | Tidak | `draft` \| `published` \| `scheduled` |
| `page` | integer | Tidak | Default 1 |
| `per_page` | integer | Tidak | Default 20, maks 100 |

### User Story

Sebagai admin, saya ingin melihat semua artikel termasuk draft dan terjadwal, agar bisa memilih mana yang akan disunting atau diterbitkan.

### Request Body

Tidak ada.

### Response Success

`200 OK` — daftar **AdminArticle** tanpa `body_html`, urut `updated_at` terbaru.

```json
{
  "data": [
    {
      "id": 5,
      "title": "Promo Akhir Tahun Sepeda Listrik",
      "slug": "promo-akhir-tahun-sepeda-listrik",
      "image_url": null,
      "status": "draft",
      "published_at": null,
      "author": { "id": 1, "name": "Admin Saeful Askar" },
      "created_at": "2026-10-14T20:00:00+07:00",
      "updated_at": "2026-10-14T20:30:00+07:00"
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 5, "last_page": 1 }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 422 | `VALIDATION_ERROR` | `status` tak dikenal |

### Acceptance Criteria

- [ ] `status=scheduled` = `published` dengan `published_at` > sekarang.
- [ ] Artikel draft ikut tampil (berbeda dengan ART-01).

---

## ADM-03 · Buat artikel

### Endpoint

`POST /api/v1/admin/articles` · **Admin** · `Content-Type: multipart/form-data`

### User Story

Sebagai admin, saya ingin menulis artikel baru dengan gambar sampul, judul, slug, tanggal, dan isi, agar bisa diterbitkan sekarang, dijadwalkan, atau disimpan sebagai draft.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `title` | string | Ya | 5–150 karakter |
| `slug` | string | Tidak | Huruf kecil, angka, tanda hubung; maks 160; unik. Kosong = dibuat dari `title` |
| `image` | file | Tidak | JPG/PNG/WEBP, maks 2 MB, min lebar 800 px |
| `body` | string | Ya | HTML dari editor, min 50 karakter teks |
| `status` | string | Ya | `draft` \| `published` |
| `published_at` | string | Tidak | ISO 8601. Wajib kosong/diabaikan bila `draft`; bila `published` dan kosong = sekarang |

Contoh (ditampilkan sebagai JSON; dikirim sebagai form-data):

```json
{
  "title": "Cara Merawat Baterai Sepeda Listrik",
  "slug": "cara-merawat-baterai-sepeda-listrik",
  "image": "(file baterai.jpg)",
  "body": "<p>Baterai lithium paling awet bila diisi sebelum di bawah 20%…</p>",
  "status": "published",
  "published_at": "2026-10-10T08:00:00+07:00"
}
```

### Response Success

`201 Created` — objek **AdminArticle**.

```json
{
  "data": {
    "id": 4,
    "title": "Cara Merawat Baterai Sepeda Listrik",
    "slug": "cara-merawat-baterai-sepeda-listrik",
    "image_url": "https://<domain>/storage/articles/baterai.jpg",
    "body_html": "<p>Baterai lithium paling awet bila diisi sebelum di bawah 20%…</p>",
    "status": "published",
    "published_at": "2026-10-10T08:00:00+07:00",
    "author": { "id": 1, "name": "Admin Saeful Askar" },
    "created_at": "2026-10-09T15:00:00+07:00",
    "updated_at": "2026-10-09T15:00:00+07:00"
  },
  "message": "Artikel disimpan."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 422 | `SLUG_TAKEN` | Slug sudah dipakai artikel lain |
| 422 | `VALIDATION_ERROR` | Judul/isi kosong, gambar terlalu besar atau bukan gambar, format tanggal salah |

```json
{
  "message": "Slug sudah dipakai artikel lain.",
  "code": "SLUG_TAKEN",
  "errors": { "slug": ["Gunakan slug lain, mis. cara-merawat-baterai-sepeda-listrik-2."] }
}
```

### Acceptance Criteria

- [ ] Gambar disimpan di `storage/app/public/articles/` dengan nama acak; kolom `articles.image` menyimpan path, response mengirim URL lengkap.
- [ ] `body` disanitasi sebelum disimpan (daftar tag yang diizinkan sama dengan ART-02).
- [ ] Slug otomatis dari judul ("Cara Merawat Baterai" → `cara-merawat-baterai`); bila bentrok ditambah `-2`, `-3` (hanya untuk slug otomatis).
- [ ] `author_id` = admin yang login.

---

## ADM-04 · Detail artikel (admin)

### Endpoint

`GET /api/v1/admin/articles/{id}` · **Admin**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `id` | path | integer | ID artikel |

### User Story

Sebagai admin, saya ingin membuka artikel (termasuk draft) untuk disunting, agar form terisi dengan isi terakhir.

### Request Body

Tidak ada.

### Response Success

`200 OK` — objek **AdminArticle** lengkap dengan `body_html`.

```json
{
  "data": {
    "id": 5,
    "title": "Promo Akhir Tahun Sepeda Listrik",
    "slug": "promo-akhir-tahun-sepeda-listrik",
    "image_url": null,
    "body_html": "<p>Draft isi promo…</p>",
    "status": "draft",
    "published_at": null,
    "author": { "id": 1, "name": "Admin Saeful Askar" },
    "created_at": "2026-10-14T20:00:00+07:00",
    "updated_at": "2026-10-14T20:30:00+07:00"
  }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 404 | `ARTICLE_NOT_FOUND` | ID tidak ada |

```json
{
  "message": "Artikel tidak ditemukan.",
  "code": "ARTICLE_NOT_FOUND"
}
```

### Acceptance Criteria

- [ ] Artikel draft dan terjadwal bisa dibuka (berbeda dengan ART-02).

---

## ADM-05 · Ubah artikel

### Endpoint

`POST /api/v1/admin/articles/{id}` dengan field `_method=PUT` · **Admin** · `Content-Type: multipart/form-data`

PHP tidak membaca file pada request `PUT` multipart, sehingga dipakai `POST` + `_method=PUT` (method spoofing Laravel).

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `id` | path | integer | ID artikel |

### User Story

Sebagai admin, saya ingin menyunting judul, gambar, isi, atau jadwal artikel, agar informasinya tetap benar.

### Request Body

Sama dengan ADM-03, ditambah:

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `_method` | string | Ya | `PUT` |
| `remove_image` | boolean | Tidak | `true` = hapus gambar sampul; diabaikan bila `image` dikirim |

```json
{
  "_method": "PUT",
  "title": "Cara Merawat Baterai Sepeda Listrik agar Awet",
  "slug": "cara-merawat-baterai-sepeda-listrik",
  "body": "<p>Baterai lithium paling awet bila diisi sebelum di bawah 20%…</p>",
  "status": "published",
  "published_at": "2026-10-10T08:00:00+07:00",
  "remove_image": false
}
```

### Response Success

`200 OK` — objek **AdminArticle** terbaru.

```json
{
  "data": {
    "id": 4,
    "title": "Cara Merawat Baterai Sepeda Listrik agar Awet",
    "slug": "cara-merawat-baterai-sepeda-listrik",
    "image_url": "https://<domain>/storage/articles/baterai.jpg",
    "body_html": "<p>Baterai lithium paling awet bila diisi sebelum di bawah 20%…</p>",
    "status": "published",
    "published_at": "2026-10-10T08:00:00+07:00",
    "author": { "id": 1, "name": "Admin Saeful Askar" },
    "created_at": "2026-10-09T15:00:00+07:00",
    "updated_at": "2026-10-14T21:40:00+07:00"
  },
  "message": "Artikel diperbarui."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 404 | `ARTICLE_NOT_FOUND` | ID tidak ada |
| 422 | `SLUG_TAKEN` | Slug baru dipakai artikel lain |
| 422 | `VALIDATION_ERROR` | Field tidak valid |

### Acceptance Criteria

- [ ] Gambar lama dihapus dari storage bila diganti atau `remove_image = true`.
- [ ] Mengubah slug artikel yang sudah terbit membuat URL lama tidak berlaku; frontend admin menampilkan peringatan sebelum menyimpan.
- [ ] `author_id` tidak berubah saat disunting admin lain.

---

## ADM-06 · Hapus artikel

### Endpoint

`DELETE /api/v1/admin/articles/{id}` · **Admin**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `id` | path | integer | ID artikel |

### User Story

Sebagai admin, saya ingin menghapus artikel yang tidak relevan lagi, agar tidak tampil di website.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "message": "Artikel dihapus."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 404 | `ARTICLE_NOT_FOUND` | ID tidak ada |

### Acceptance Criteria

- [ ] Artikel dan file gambarnya dihapus permanen, langsung hilang dari ART-01/ART-02.
- [ ] Frontend menampilkan konfirmasi di halaman sebelum memanggil endpoint ini.

---

## ADM-07 · Terbitkan / jadikan draft

### Endpoint

`PATCH /api/v1/admin/articles/{id}/status` · **Admin**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `id` | path | integer | ID artikel |

### User Story

Sebagai admin, saya ingin menerbitkan, menjadwalkan, atau menarik artikel dengan satu klik dari daftar, tanpa membuka form sunting.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `status` | string | Ya | `draft` \| `published` |
| `published_at` | string | Tidak | ISO 8601; bila `published` dan kosong = sekarang |

```json
{
  "status": "published",
  "published_at": "2026-10-20T08:00:00+07:00"
}
```

### Response Success

`200 OK` — objek **AdminArticle** tanpa `body_html`.

```json
{
  "data": {
    "id": 5,
    "title": "Promo Akhir Tahun Sepeda Listrik",
    "slug": "promo-akhir-tahun-sepeda-listrik",
    "image_url": null,
    "status": "published",
    "published_at": "2026-10-20T08:00:00+07:00",
    "author": { "id": 1, "name": "Admin Saeful Askar" },
    "created_at": "2026-10-14T20:00:00+07:00",
    "updated_at": "2026-10-14T21:45:00+07:00"
  },
  "message": "Artikel dijadwalkan terbit 20 Okt 2026 08.00."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 404 | `ARTICLE_NOT_FOUND` | ID tidak ada |
| 422 | `VALIDATION_ERROR` | `status` tak dikenal, tanggal salah format |

### Acceptance Criteria

- [ ] `draft` mengosongkan `published_at`.
- [ ] Artikel terjadwal otomatis tampil di ART-01 saat `published_at` tercapai (tanpa job tambahan, karena query memakai waktu sekarang).

---

## ADM-08 · Upload gambar untuk isi artikel

### Endpoint

`POST /api/v1/admin/uploads/images` · **Admin** · `Content-Type: multipart/form-data`

### User Story

Sebagai admin, saya ingin menyisipkan gambar di tengah isi artikel lewat editor, agar artikel lebih jelas.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `image` | file | Ya | JPG/PNG/WEBP, maks 2 MB |

```json
{
  "image": "(file langkah-1.jpg)"
}
```

### Response Success

`201 Created`

```json
{
  "data": {
    "url": "https://<domain>/storage/articles/content/9a1c7f.jpg",
    "width": 1200,
    "height": 800
  }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401/403 | `UNAUTHENTICATED` / `FORBIDDEN` | Lihat error umum |
| 422 | `VALIDATION_ERROR` | Bukan gambar, format lain, > 2 MB |

```json
{
  "message": "Data yang dikirim tidak valid.",
  "code": "VALIDATION_ERROR",
  "errors": { "image": ["Ukuran gambar maksimal 2 MB."] }
}
```

### Acceptance Criteria

- [ ] File disimpan di `storage/app/public/articles/content/` dengan nama acak.
- [ ] Gambar lebih lebar dari 1600 px diperkecil ke 1600 px.
- [ ] URL yang dikembalikan bisa langsung dipakai di `<img src>` editor.

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

Sebagai admin, saya ingin melihat rincian pesanan lengkap dengan data customer, invoice Xendit, sales order Jubelio, dan riwayat integrasinya, agar bisa menelusuri masalah.

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
    "payment": { "status": "PENDING", "invoice_url": "https://checkout.xendit.co/web/6527a1b2c3d4e5f6a7b8c9d1", "payment_method": null, "payment_channel": null, "expires_at": "2026-10-14T23:35:00+07:00", "paid_at": null },
    "note": null,
    "status_history": [
      { "status": "pending_payment", "label": "Menunggu pembayaran", "at": "2026-10-14T21:35:00+07:00", "source": "system" }
    ],
    "integration": {
      "jubelio_salesorder_id": null,
      "jubelio_salesorder_no": null,
      "jubelio_sync_status": "failed",
      "jubelio_last_error": "SQLSTATE 23505",
      "xendit_invoice_id": "6527a1b2c3d4e5f6a7b8c9d1",
      "xendit_external_id": "WEB-000124",
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

Sebagai admin yang menerima laporan "sudah bayar tapi status belum berubah", saya ingin mengecek status invoice langsung ke Xendit, agar pesanan segera diproses bila memang sudah dibayar.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "data": {
    "order_number": "WEB-000124",
    "xendit_status": "PAID",
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
| 502 | `INTEGRATION_ERROR` | Xendit tidak merespons |

```json
{
  "message": "Xendit tidak merespons. Coba lagi beberapa saat.",
  "code": "INTEGRATION_ERROR"
}
```

### Acceptance Criteria

- [ ] Memanggil INT-XD-02 dan, bila `PAID`/`SETTLED`/`EXPIRED`, menjalankan alur yang sama dengan WH-01.
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

- [ ] Hanya satu sinkron per jenis berjalan bersamaan (lock di Redis).
- [ ] `full` = kategori → produk → stok berurutan, masing-masing dengan baris `sync_logs` sendiri.
- [ ] Sinkron mematuhi batas 600 request/menit Jubelio.

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

## ADM-15 · Daftar webhook masuk

### Endpoint

`GET /api/v1/admin/webhook-events` · **Admin**

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `provider` | string | Tidak | `xendit` \| `jubelio` |
| `status` | string | Tidak | `received` \| `processed` \| `ignored` \| `failed` |
| `event_type` | string | Tidak | mis. `invoice.paid`, `update-qty` |
| `page` | integer | Tidak | Default 1 |
| `per_page` | integer | Tidak | Default 20, maks 100 |

### User Story

Sebagai admin, saya ingin melihat webhook yang diterima dari Xendit dan Jubelio, agar bisa menemukan notifikasi yang gagal diproses.

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

## ADM-16 · Proses ulang webhook

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

## ADM-17 · Status konfigurasi integrasi

### Endpoint

`GET /api/v1/admin/settings/integrations` · **Admin**

### User Story

Sebagai admin yang menyiapkan toko, saya ingin melihat apakah Jubelio, Jubelio Shipment, dan Xendit sudah terhubung dan URL webhook mana yang harus didaftarkan, agar setup tidak ada yang terlewat.

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
    "jubelio_shipment": {
      "configured": false,
      "environment": "staging",
      "origin": "Jl. Raya Pisangan No.1, Satriajaya, Tambun Utara, Kab. Bekasi 17566"
    },
    "xendit": {
      "configured": true,
      "mode": "test",
      "callback_token_set": true,
      "invoice_duration_seconds": 7200
    },
    "webhook_urls": {
      "xendit_invoice": "https://<domain>/webhooks/xendit/invoice",
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

- [ ] Tidak ada password, secret key, callback token, atau token Jubelio di response.
- [ ] `login_ok` diperoleh dengan mencoba token yang ada / login ulang (INT-JB-01), hasil di-cache 5 menit.
- [ ] `store_name` dan `location_name` diambil dari Jubelio (INT-JB-14) untuk memastikan ID di `.env` benar.
- [ ] `mode` Xendit = `test` bila secret key diawali `xnd_development_`, `live` bila `xnd_production_`.
