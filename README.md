# E-Commers Saeful Askar

Website e-commerce berbasis Laravel 12.

## Tech Stack

| Layer    | Teknologi                              |
|----------|----------------------------------------|
| Backend  | Laravel 12, PHP 8.2+                   |
| Frontend | Blade, TailwindCSS 4, Vite 7           |
| Database | MySQL 8 (lokal: MariaDB XAMPP)          |
| Testing  | Pest 3                                 |

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

`backend` dan `frontend` di-merge ke `development` setelah modul selesai dan test lulus; `development` di-merge ke `main` saat rilis. Rincian di PRD §8.

## Setup Lokal

Prasyarat: PHP 8.2+, Composer, Node.js, XAMPP (MySQL aktif).

```bash
composer install
cp .env.example .env
php artisan key:generate

# Buat database `e_commers_saeful_askar` di MySQL (phpMyAdmin / CLI), lalu:
php artisan migrate

npm install
npm run build
```

## Menjalankan

```bash
composer dev      # server + queue + vite sekaligus
# atau terpisah:
php artisan serve
npm run dev
```

Buka http://localhost:8000.

## Testing

```bash
composer test
```
