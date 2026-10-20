# E-Commers Saeful Askar

Website e-commerce Toko Sepeda Listrik Saeful Askar, terhubung ke Jubelio (katalog, stok, order) dan Gapura / DANA (pembayaran).

## Tech Stack

| Layer    | Teknologi                                                    |
|----------|--------------------------------------------------------------|
| Backend  | Laravel 12, PHP 8.2+, Sanctum (session cookie stateful)      |
| Frontend | Blade, Alpine.js 3, TailwindCSS 4, Vite 7                     |
| Database | MySQL 8 (lokal: MariaDB XAMPP) — juga untuk cache, queue, dan lock (tanpa Redis) |
| Testing  | Pest 3                                                       |

## Dokumentasi Proyek

- [`docs/PRD-Website-ECommerce-Saeful-Askar.md`](docs/PRD-Website-ECommerce-Saeful-Askar.md) — kebutuhan produk, aturan bisnis, tahapan (acuan utama)
- [`docs/api/`](docs/api/README.md) — API Contract v1 per modul (titik temu backend & frontend)
- [`docs/arsitektur-sistem.md`](docs/arsitektur-sistem.md) — diagram arsitektur, alur checkout, struktur folder
- [`docs/erd.md`](docs/erd.md) — ERD v2: kolom, relasi, index

## Alur Branch

| Branch | Isi |
|--------|-----|
| `main` | Versi stabil siap rilis |
| `development` | Integrasi backend + frontend; perubahan bersama (kontrak API, fondasi) |
| `backend` | Pekerjaan tim backend |
| `frontend` | Pekerjaan tim frontend |

`backend` dan `frontend` di-merge ke `development` setelah modul selesai dan test lulus; `development` di-merge ke `main` saat rilis. Perubahan bersama dibuat di `development` lalu di-merge ke kedua branch. Rincian di PRD §8.

## Setup Lokal

Prasyarat: PHP 8.2+ (ekstensi `pdo_mysql`), Composer, Node.js, XAMPP dengan MySQL aktif.

```bash
composer install
cp .env.example .env
php artisan key:generate

# Buat dua database (utf8mb4_unicode_ci) lewat phpMyAdmin atau CLI:
#   e_commers_saeful_askar          → aplikasi
#   e_commers_saeful_askar_testing  → dipakai `composer test`
php artisan migrate --seed

npm install
npm run build
```

`--seed` membuat akun admin dan katalog contoh. Admin lokal: `admin@saefulaskar.test` / `password` (atur `ADMIN_EMAIL` dan `ADMIN_PASSWORD` di `.env` untuk menggantinya; di luar lokal keduanya wajib diisi).

## Menjalankan

```bash
composer dev      # server + queue + scheduler + vite sekaligus
```

Buka http://localhost:8000.

## Cara kerja paralel

### Tim frontend (branch `frontend`)

1. Set `VITE_API_MOCK=true` di `.env`, lalu jalankan `composer dev`. Semua request ke `/api/v1` dijawab dari contoh response di API Contract, dan halaman akun/admin bisa dibuka tanpa login. Pita kuning di atas halaman menandakan mode mock aktif.
2. Panggil backend hanya lewat `resources/js/api/client.js` + `endpoints.js`:
   ```js
   import { api } from './api/client';
   import { endpoints } from './api/endpoints';

   const { data, meta } = await api.get(endpoints.products.index, { query: { category: 'baterai' } });
   ```
   Error selalu berupa `ApiError` dengan `code` dari kontrak (`OUT_OF_STOCK`, `VALIDATION_ERROR`, …) dan `fieldError('email')` untuk pesan per field.
3. Untuk mencoba kondisi gagal atau mock yang bereaksi terhadap input, tulis di `resources/js/api/mocks/overrides.js`. Jangan mengedit `contract.json`, karena file itu di-generate.
4. Halaman ada di `resources/views/pages/**`. Setiap placeholder menyebutkan kode endpoint yang dipakainya. Komponen Alpine ditaruh di `resources/js/components/` lalu didaftarkan di `app.js`.
5. Set kembali `VITE_API_MOCK=false` untuk mencoba endpoint asli dari backend. **Setiap kali nilai ini diubah, jalankan ulang `composer dev`** (atau `npm run build`); bila server dan bundle JS berbeda, console browser menampilkan peringatan `VITE_API_MOCK berbeda`.
6. Boolean di query string dan form-data dikirim `1`/`0` — `client.js` mengubah `true`/`false` di `query` secara otomatis; untuk `FormData` isi sendiri dengan `'1'`/`'0'`.

### Tim backend (branch `backend`)

1. Semua route v1 sudah terdaftar (`routes/api.php`, `routes/webhooks.php`). Endpoint yang belum dibangun membalas `501 NOT_IMPLEMENTED` beserta kode endpointnya. Ganti `throw new NotImplementedException(...)` di controller dengan implementasi sesuai `docs/api/*.md`.
2. Setiap Resource meng-extend `App\Http\Resources\ApiResource` (bukan `JsonResource`), sehingga `XResource::collection($paginator)` otomatis berbentuk `{ data, meta: { current_page, per_page, total, last_page } }` seperti kontrak. Tanggal otomatis diformat ISO 8601 `+07:00`.
3. Error domain dilempar sebagai `App\Exceptions\ApiException` (atau turunannya) dengan `code` dari kontrak; error Laravel (auth, validasi, 404, 429, …) sudah otomatis diubah ke format kontrak.
4. Panggilan ke Jubelio, Jubelio Shipment, dan Gapura hanya lewat `app/Services/*/…Client.php`. Test memakai `Http::fake()` + fixture di `tests/Fixtures/`; Gapura memakai SDK DANA, jadi testnya memakai Guzzle mock handler.
5. Setiap endpoint wajib punya feature test; helper `assertApiError($response, 409, 'OUT_OF_STOCK')` tersedia di `tests/Pest.php`.

### Saat kontrak API berubah

Ubah `docs/api/*.md` di branch `development` (disepakati kedua tim), jalankan `npm run mocks:generate`, commit keduanya bersama, lalu merge `development` ke `backend` dan `frontend`.

Dua pemeriksaan menjaga BE dan FE tetap sejalan — **wajib lulus sebelum merge ke `development`**:

| Perintah | Memeriksa |
|---|---|
| `composer test` (`ContractRoutesTest`) | Setiap endpoint kontrak punya route, dan tidak ada route API yang tidak tercatat di kontrak |
| `npm run contract:check` | `contract.json` tidak basi, `endpoints.js` ↔ kontrak cocok dua arah, field yang dibaca store FE ada di response kontrak |

## Testing

```bash
composer test
```

Test memakai database `e_commers_saeful_askar_testing` (lihat `phpunit.xml`), bukan database aplikasi.
