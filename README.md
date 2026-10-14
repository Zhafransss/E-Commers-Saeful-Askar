# E-Commers Saeful Askar

Website e-commerce berbasis Laravel 12.

## Tech Stack

| Layer    | Teknologi                              |
|----------|----------------------------------------|
| Backend  | Laravel 12, PHP 8.2+                   |
| Frontend | Blade, TailwindCSS 4, Vite 7           |
| Database | MySQL (XAMPP untuk development lokal)  |
| Testing  | Pest 3                                 |

## Dokumentasi Proyek

- [`docs/SRS.md`](docs/SRS.md) — konteks bisnis & requirement (FR/NFR)
- [`docs/RTM.md`](docs/RTM.md) — status pengerjaan per requirement

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
