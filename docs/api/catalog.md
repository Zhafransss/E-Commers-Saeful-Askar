# Katalog — Kategori dan Produk

Semua data katalog adalah salinan dari Jubelio yang disimpan di database lokal. Endpoint di file ini **tidak** memanggil Jubelio per request. Konvensi umum ada di `README.md`.

Aturan tampil yang berlaku untuk semua endpoint katalog:

- Produk tampil bila `products.is_active = true` dan punya minimal satu varian aktif.
- Varian tampil bila `product_variants.is_active = true`.
- Stok yang ditampilkan adalah `available_qty` hasil sinkron terakhir dari gudang Tambun Utara.

---

## CAT-01 · Daftar kategori

### Endpoint

`GET /api/v1/categories` · **Publik**

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `with_empty` | boolean | Tidak | Default `0`: kategori tanpa produk aktif tidak ikut |

### User Story

Sebagai pengunjung, saya ingin melihat daftar kategori (mis. Sepeda Listrik, Baterai), agar saya bisa menyaring produk sesuai yang saya cari.

### Request Body

Tidak ada.

### Response Success

`200 OK` — daftar **Category**, tanpa paginasi, urut nama. Kategori anak memakai `parent_id`.

```json
{
  "data": [
    { "id": 4, "name": "Baterai", "slug": "baterai", "parent_id": null, "product_count": 7 },
    { "id": 3, "name": "Sepeda Listrik", "slug": "sepeda-listrik", "parent_id": null, "product_count": 18 },
    { "id": 9, "name": "Sepeda Listrik Lipat", "slug": "sepeda-listrik-lipat", "parent_id": 3, "product_count": 5 }
  ]
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 422 | `VALIDATION_ERROR` | `with_empty` bukan boolean |
| 500 | `SERVER_ERROR` | Error database |

### Acceptance Criteria

- [ ] Data berasal dari tabel `categories` hasil sinkron Jubelio (INT-JB-02).
- [ ] `product_count` hanya menghitung produk aktif dengan minimal satu varian aktif.
- [ ] Kategori dengan `product_count = 0` tidak muncul kecuali `with_empty=1`.
- [ ] Response di-cache maksimal 10 menit dan cache dihapus setelah sinkron kategori/produk.

---

## CAT-02 · Daftar dan pencarian produk

### Endpoint

`GET /api/v1/products` · **Publik**

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `q` | string | Tidak | Cari di nama produk, nama varian, dan SKU; maks 100 karakter |
| `category` | string | Tidak | Slug kategori; kategori induk ikut menampilkan produk kategori anak |
| `in_stock` | boolean | Tidak | `1` = hanya produk dengan stok > 0 |
| `price_min` | integer | Tidak | Rupiah, ≥ 0 |
| `price_max` | integer | Tidak | Rupiah, ≥ `price_min` |
| `sort` | string | Tidak | `latest` (default), `price_asc`, `price_desc`, `name_asc` |
| `page` | integer | Tidak | Default 1 |
| `per_page` | integer | Tidak | Default 12, maks 48 |

### User Story

Sebagai pengunjung, saya ingin mencari dan menyaring sepeda listrik atau baterai berdasarkan kata kunci, kategori, harga, dan ketersediaan stok, agar cepat menemukan produk yang sesuai.

### Request Body

Tidak ada.

### Response Success

`200 OK` — daftar **ProductSummary** dengan paginasi.

```json
{
  "data": [
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
    },
    {
      "id": 15,
      "slug": "baterai-lithium-48v",
      "name": "Baterai Lithium 48V",
      "category": { "id": 4, "name": "Baterai", "slug": "baterai" },
      "thumbnail_url": "https://jubelio.blob.core.windows.net/images/def/thumb.jpg",
      "price_min": 2100000,
      "price_max": 3400000,
      "total_available_qty": 0,
      "in_stock": false,
      "variant_count": 2
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 12,
    "total": 25,
    "last_page": 3
  }
}
```

Hasil kosong tetap `200` dengan `"data": []` dan `"total": 0`.

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 422 | `VALIDATION_ERROR` | `sort` di luar pilihan, `per_page` > 48, `price_max` < `price_min`, `q` > 100 karakter |
| 500 | `SERVER_ERROR` | Error database |

```json
{
  "message": "Data yang dikirim tidak valid.",
  "code": "VALIDATION_ERROR",
  "errors": { "sort": ["Pilihan urutan tidak dikenal."] }
}
```

`category` yang tidak ada **tidak** dianggap error: hasilnya kosong.

### Acceptance Criteria

- [ ] Hanya produk aktif dengan minimal satu varian aktif yang tampil.
- [ ] `price_min`/`price_max` dihitung dari varian aktif; produk satu varian punya nilai yang sama.
- [ ] `in_stock` = `total_available_qty > 0`; produk habis tetap tampil kecuali `in_stock=1`.
- [ ] Pencarian SKU persis (mis. `UWF-C1PRO-HTM`) menemukan produknya.
- [ ] Filter harga memakai rentang harga varian (produk tampil bila ada varian di dalam rentang).
- [ ] `sort=latest` mengurutkan berdasarkan `jubelio_last_modified` terbaru, lalu `id`.
- [ ] Waktu respons < 500 ms untuk 48 item (tanpa panggilan ke Jubelio).
- [ ] Hasil sinkron dari Jubelio terlihat di endpoint ini paling lambat 1 menit setelah job sinkron selesai.

---

## CAT-03 · Detail produk

### Endpoint

`GET /api/v1/products/{slug}` · **Publik**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `slug` | path | string | Slug produk, mis. `sepeda-listrik-uwinfly-c1-pro` |

### User Story

Sebagai pengunjung, saya ingin melihat foto, spesifikasi, varian, harga, dan stok sebuah produk, agar saya yakin sebelum memasukkannya ke keranjang.

### Request Body

Tidak ada.

### Response Success

`200 OK` — objek **ProductDetail**.

```json
{
  "data": {
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
      { "id": 101, "url": "https://jubelio.blob.core.windows.net/images/abc/1.jpg", "thumbnail_url": "https://jubelio.blob.core.windows.net/images/abc/1-thumb.jpg", "variant_id": null, "sort_order": 0 },
      { "id": 102, "url": "https://jubelio.blob.core.windows.net/images/abc/hitam.jpg", "thumbnail_url": "https://jubelio.blob.core.windows.net/images/abc/hitam-thumb.jpg", "variant_id": 31, "sort_order": 1 }
    ],
    "variations": [{ "label": "Warna", "values": ["Hitam", "Putih"] }],
    "variants": [
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
      },
      {
        "id": 32,
        "sku": "UWF-C1PRO-PTH",
        "name": "Sepeda Listrik Uwinfly C1 Pro - Putih",
        "variation_values": [{ "label": "Warna", "value": "Putih" }],
        "price": 7250000,
        "available_qty": 0,
        "in_stock": false,
        "package_weight": 32000,
        "image_url": null
      }
    ],
    "price_min": 6750000,
    "price_max": 7250000,
    "in_stock": true,
    "updated_at": "2026-10-14T20:15:00+07:00"
  }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 404 | `PRODUCT_NOT_FOUND` | Slug tidak ada, produk nonaktif, atau semua variannya nonaktif |
| 500 | `SERVER_ERROR` | Error database |

```json
{
  "message": "Produk tidak ditemukan atau sudah tidak dijual.",
  "code": "PRODUCT_NOT_FOUND"
}
```

### Acceptance Criteria

- [ ] `description_html` adalah deskripsi Jubelio yang sudah disanitasi (tanpa `<script>`, `<iframe>`, atribut `on*`).
- [ ] Hanya varian aktif yang ada di `variants`; varian stok 0 tetap tampil dengan `in_stock = false`.
- [ ] `package_weight` per varian sama dengan nilai Jubelio (INT-JB-05).
- [ ] Gambar urut `sort_order`; gambar dengan `variant_id` dipakai saat varian itu dipilih.
- [ ] `min_qty`/`max_qty` bernilai `null` bila Jubelio tidak mengisinya.
- [ ] Halaman Blade `products/show` memakai data yang sama untuk judul dan meta deskripsi (SEO).
- [ ] Slug tidak berubah walau nama produk diubah di Jubelio.
