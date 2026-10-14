# Keranjang

Keranjang disimpan per user di tabel `cart_items` (tidak ada keranjang tamu). Harga dan stok selalu dibaca dari data katalog terbaru, jadi isi keranjang ikut berubah bila Jubelio mengubah harga atau stok. Konvensi umum ada di `README.md`.

Setiap response keranjang mengembalikan objek **Cart** lengkap (lihat `README.md`), sehingga frontend cukup mengganti seluruh state keranjang dari response terakhir.

Aturan jumlah (qty) yang berlaku di CART-02 dan CART-03:

1. `qty` ≥ 1.
2. `qty` ≤ `available_qty` varian.
3. Bila produk punya `min_qty`, `qty` ≥ `min_qty`.
4. Bila produk punya `max_qty`, total qty semua varian produk itu di keranjang ≤ `max_qty`.

---

## CART-01 · Lihat keranjang

### Endpoint

`GET /api/v1/cart` · **Customer**

### User Story

Sebagai customer, saya ingin melihat isi keranjang beserta harga, subtotal, berat, dan peringatan stok terbaru, agar saya tahu apa yang akan saya bayar.

### Request Body

Tidak ada.

### Response Success

`200 OK` — objek **Cart**. Keranjang kosong: `"items": []` dan semua angka `summary` bernilai 0.

```json
{
  "data": {
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
      },
      {
        "id": 6,
        "variant": {
          "id": 41,
          "sku": "BAT-48V-20AH",
          "name": "Baterai Lithium 48V - 20Ah",
          "variation_values": [{ "label": "Kapasitas", "value": "20Ah" }],
          "price": 3400000,
          "available_qty": 1,
          "in_stock": true,
          "package_weight": 9500,
          "image_url": null
        },
        "product": { "id": 15, "slug": "baterai-lithium-48v", "name": "Baterai Lithium 48V", "min_qty": null, "max_qty": null },
        "qty": 2,
        "subtotal": 6800000,
        "weight_total": 19000,
        "issue": "QTY_EXCEEDS_STOCK"
      }
    ],
    "summary": {
      "item_count": 2,
      "total_qty": 3,
      "subtotal": 13550000,
      "total_weight": 51000,
      "has_issue": true
    }
  }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |

### Acceptance Criteria

- [ ] Harga dan `available_qty` diambil dari `product_variants` saat request, bukan disimpan di keranjang.
- [ ] `issue` = `OUT_OF_STOCK` bila stok 0, `QTY_EXCEEDS_STOCK` bila qty > stok, `PRODUCT_INACTIVE` bila produk/varian dinonaktifkan di Jubelio.
- [ ] Item bermasalah tetap ditampilkan (tidak dihapus otomatis) agar customer bisa memutuskan.
- [ ] `summary.subtotal` = jumlah `subtotal` item tanpa `issue = PRODUCT_INACTIVE`.
- [ ] `summary.total_weight` (gram) = jumlah `qty × package_weight` dan dipakai SHIP-01.
- [ ] Item urut dari yang terakhir ditambahkan.

---

## CART-02 · Tambah item ke keranjang

### Endpoint

`POST /api/v1/cart/items` · **Customer**

### User Story

Sebagai customer, saya ingin memasukkan varian tertentu (mis. warna Hitam) dengan jumlah tertentu ke keranjang, agar bisa saya beli nanti.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `variant_id` | integer | Ya | ID varian aktif |
| `qty` | integer | Ya | ≥ 1; mengikuti aturan jumlah di atas |

```json
{
  "variant_id": 31,
  "qty": 1
}
```

Bila varian sudah ada di keranjang, qty **ditambahkan** ke qty lama (bukan item baru).

### Response Success

`201 Created` bila item baru, `200 OK` bila menambah qty item yang sudah ada. Body = objek **Cart** terbaru.

```json
{
  "data": {
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
    "summary": { "item_count": 1, "total_qty": 1, "subtotal": 6750000, "total_weight": 32000, "has_issue": false }
  },
  "message": "Produk ditambahkan ke keranjang."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login (frontend mengarahkan ke halaman login) |
| 404 | `VARIANT_NOT_FOUND` | `variant_id` tidak ada |
| 409 | `OUT_OF_STOCK` | Stok varian 0 atau qty (termasuk qty lama di keranjang) > stok |
| 419 | `CSRF_TOKEN_MISMATCH` | Cookie XSRF tidak ada |
| 422 | `VARIANT_INACTIVE` | Varian/produk dinonaktifkan di Jubelio |
| 422 | `QTY_LIMIT_EXCEEDED` | Melewati `max_qty` atau kurang dari `min_qty` |
| 422 | `VALIDATION_ERROR` | `variant_id`/`qty` kosong atau bukan angka |

```json
{
  "message": "Stok tidak cukup. Tersisa 4 unit.",
  "code": "OUT_OF_STOCK",
  "errors": { "qty": ["Maksimal 4 unit untuk varian ini."] },
  "available_qty": 4
}
```

```json
{
  "message": "Maksimal pembelian produk ini 2 unit per pesanan.",
  "code": "QTY_LIMIT_EXCEEDED",
  "errors": { "qty": ["Maksimal 2 unit."] },
  "max_qty": 2
}
```

### Acceptance Criteria

- [ ] Varian yang sama ditambahkan dua kali menjadi satu baris dengan qty dijumlahkan (unique `user_id` + `product_variant_id`).
- [ ] Stok dicek terhadap `available_qty` lokal; total qty setelah ditambah tidak boleh melebihi stok.
- [ ] `max_qty` dihitung dari total qty semua varian produk yang sama di keranjang.
- [ ] Error `OUT_OF_STOCK` menyertakan `available_qty` agar frontend bisa menampilkan sisa stok.
- [ ] Badge keranjang di header diperbarui dari `summary.total_qty`.

---

## CART-03 · Ubah jumlah item

### Endpoint

`PATCH /api/v1/cart/items/{id}` · **Customer**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `id` | path | integer | ID baris keranjang (`items[].id`) |

### User Story

Sebagai customer, saya ingin mengubah jumlah barang di keranjang, agar sesuai kebutuhan saya sebelum checkout.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `qty` | integer | Ya | ≥ 1; mengikuti aturan jumlah. Untuk menghapus gunakan CART-04 |

```json
{
  "qty": 2
}
```

### Response Success

`200 OK` — objek **Cart** terbaru (bentuk sama dengan CART-01).

```json
{
  "data": {
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
        "qty": 2,
        "subtotal": 13500000,
        "weight_total": 64000,
        "issue": null
      }
    ],
    "summary": { "item_count": 1, "total_qty": 2, "subtotal": 13500000, "total_weight": 64000, "has_issue": false }
  }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 404 | `CART_ITEM_NOT_FOUND` | Baris tidak ada atau milik user lain |
| 409 | `OUT_OF_STOCK` | qty > stok tersedia |
| 422 | `VARIANT_INACTIVE` | Varian sudah nonaktif (hanya bisa dihapus) |
| 422 | `QTY_LIMIT_EXCEEDED` | Melewati `max_qty` / kurang dari `min_qty` |
| 422 | `VALIDATION_ERROR` | `qty` < 1 atau bukan angka |

```json
{
  "message": "Item keranjang tidak ditemukan.",
  "code": "CART_ITEM_NOT_FOUND"
}
```

### Acceptance Criteria

- [ ] Customer tidak bisa mengubah baris keranjang milik user lain (dibalas 404, bukan 403, agar ID tidak bocor).
- [ ] qty yang melebihi stok ditolak; qty lama tidak berubah.
- [ ] Menurunkan qty pada item ber-`issue = QTY_EXCEEDS_STOCK` hingga ≤ stok menghapus `issue`.

---

## CART-04 · Hapus item

### Endpoint

`DELETE /api/v1/cart/items/{id}` · **Customer**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `id` | path | integer | ID baris keranjang |

### User Story

Sebagai customer, saya ingin menghapus barang dari keranjang, agar tidak ikut dibeli.

### Request Body

Tidak ada.

### Response Success

`200 OK` — objek **Cart** terbaru.

```json
{
  "data": {
    "items": [],
    "summary": { "item_count": 0, "total_qty": 0, "subtotal": 0, "total_weight": 0, "has_issue": false }
  },
  "message": "Produk dihapus dari keranjang."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 404 | `CART_ITEM_NOT_FOUND` | Baris tidak ada atau milik user lain |

### Acceptance Criteria

- [ ] Baris terhapus permanen dari `cart_items`.
- [ ] Item nonaktif (`issue = PRODUCT_INACTIVE`) tetap bisa dihapus.

---

## CART-05 · Kosongkan keranjang

### Endpoint

`DELETE /api/v1/cart` · **Customer**

### User Story

Sebagai customer, saya ingin mengosongkan seluruh keranjang sekaligus, agar bisa mulai memilih dari awal.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "data": {
    "items": [],
    "summary": { "item_count": 0, "total_qty": 0, "subtotal": 0, "total_weight": 0, "has_issue": false }
  },
  "message": "Keranjang dikosongkan."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |

### Acceptance Criteria

- [ ] Semua baris `cart_items` milik user terhapus; keranjang user lain tidak tersentuh.
- [ ] Memanggil di keranjang yang sudah kosong tetap `200`.

---

## CART-06 · Jumlah item untuk badge header

### Endpoint

`GET /api/v1/cart/count` · **Publik** (tamu mendapat 0)

### User Story

Sebagai pengunjung atau customer, saya ingin melihat angka jumlah barang di ikon keranjang pada setiap halaman, agar tahu isi keranjang tanpa membukanya.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "data": {
    "total_qty": 3,
    "has_issue": true
  }
}
```

Tamu: `{ "data": { "total_qty": 0, "has_issue": false } }`.

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 500 | `SERVER_ERROR` | Error database |

### Acceptance Criteria

- [ ] Tidak pernah membalas 401; tamu mendapat 0.
- [ ] Respons ringan (< 100 ms), tanpa memuat data produk lengkap.
- [ ] `total_qty` sama dengan `summary.total_qty` di CART-01.
