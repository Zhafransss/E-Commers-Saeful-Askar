# Artikel (Publik)

Artikel dikelola admin (lihat `admin.md`) dan tidak terkait Jubelio. Kolom mengikuti permintaan frontend (`image`, `slug`, `title`, `date`, `body`) dengan penyesuaian: `date` → `published_at`, `Title` → `title`, gambar dikirim sebagai URL lengkap `image_url`, dan isi sebagai `body_html`. Konvensi umum serta objek **ArticleSummary**/**Article** ada di `README.md`.

Artikel tampil di endpoint publik hanya bila `status = published` dan `published_at` ≤ waktu sekarang (artikel terjadwal belum tampil).

---

## ART-01 · Daftar artikel

### Endpoint

`GET /api/v1/articles` · **Publik**

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `q` | string | Tidak | Cari di judul, maks 100 karakter |
| `page` | integer | Tidak | Default 1 |
| `per_page` | integer | Tidak | Default 9, maks 48 |

### User Story

Sebagai pengunjung, saya ingin membaca daftar artikel tentang sepeda listrik dan perawatan baterai, agar saya mendapat informasi sebelum membeli.

### Request Body

Tidak ada.

### Response Success

`200 OK` — daftar **ArticleSummary**, terbaru (`published_at`) di atas.

```json
{
  "data": [
    {
      "id": 4,
      "slug": "cara-merawat-baterai-sepeda-listrik",
      "title": "Cara Merawat Baterai Sepeda Listrik",
      "image_url": "https://<domain>/storage/articles/baterai.jpg",
      "excerpt": "Lima kebiasaan sederhana agar baterai lithium awet hingga 3 tahun.",
      "published_at": "2026-10-10T08:00:00+07:00"
    },
    {
      "id": 3,
      "slug": "memilih-sepeda-listrik-untuk-harian",
      "title": "Memilih Sepeda Listrik untuk Pemakaian Harian",
      "image_url": null,
      "excerpt": "Perhatikan daya motor, kapasitas baterai, dan berat sepeda sebelum membeli.",
      "published_at": "2026-10-01T08:00:00+07:00"
    }
  ],
  "meta": { "current_page": 1, "per_page": 9, "total": 2, "last_page": 1 }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 422 | `VALIDATION_ERROR` | `per_page` > 48 atau `q` > 100 karakter |
| 500 | `SERVER_ERROR` | Error database |

```json
{
  "message": "Data yang dikirim tidak valid.",
  "code": "VALIDATION_ERROR",
  "errors": { "per_page": ["Maksimal 48 per halaman."] }
}
```

### Acceptance Criteria

- [ ] Hanya artikel `published` dengan `published_at` ≤ sekarang.
- [ ] `excerpt` = 160 karakter pertama `body` tanpa tag HTML, dipotong di batas kata, diakhiri "…" bila terpotong.
- [ ] `image_url` berupa URL lengkap ke storage publik, atau `null` bila artikel tanpa gambar.
- [ ] Hasil kosong tetap 200 dengan `"data": []`.

---

## ART-02 · Detail artikel

### Endpoint

`GET /api/v1/articles/{slug}` · **Publik**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `slug` | path | string | mis. `cara-merawat-baterai-sepeda-listrik` |

### User Story

Sebagai pengunjung, saya ingin membaca isi lengkap sebuah artikel, agar mendapat panduan yang saya cari.

### Request Body

Tidak ada.

### Response Success

`200 OK` — objek **Article**.

```json
{
  "data": {
    "id": 4,
    "slug": "cara-merawat-baterai-sepeda-listrik",
    "title": "Cara Merawat Baterai Sepeda Listrik",
    "image_url": "https://<domain>/storage/articles/baterai.jpg",
    "excerpt": "Lima kebiasaan sederhana agar baterai lithium awet hingga 3 tahun.",
    "body_html": "<p>Baterai lithium paling awet bila diisi sebelum di bawah 20%…</p><h2>1. Jangan biarkan kosong</h2><p>…</p>",
    "author": { "name": "Admin Saeful Askar" },
    "published_at": "2026-10-10T08:00:00+07:00",
    "updated_at": "2026-10-11T09:30:00+07:00"
  }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 404 | `ARTICLE_NOT_FOUND` | Slug tidak ada, artikel masih draft, atau terjadwal di masa depan |

```json
{
  "message": "Artikel tidak ditemukan.",
  "code": "ARTICLE_NOT_FOUND"
}
```

### Acceptance Criteria

- [ ] Artikel draft atau terjadwal dibalas 404 di endpoint publik.
- [ ] `body_html` disanitasi saat disimpan (hanya tag teks, heading, list, link, gambar, tabel; tanpa `<script>`/`<iframe>`/atribut `on*`).
- [ ] Halaman Blade `articles/show` memakai `title`, `excerpt`, dan `image_url` untuk meta SEO dan Open Graph.
