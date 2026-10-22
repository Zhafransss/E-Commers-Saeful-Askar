# Deploy ke shared hosting (branch `deployment`)

Branch `main` untuk VPS (worker lewat Supervisor). Branch `deployment` untuk shared hosting:
selisihnya hanya di `routes/console.php`, yaitu scheduler juga menjalankan
`queue:work --stop-when-empty --max-time=50` tiap menit. Jangan merge `deployment` ke `main`;
ambil perubahan dari `main` dengan `git merge main` ke `deployment`.

## Persyaratan hosting
- PHP 8.2+ dengan `openssl`, `curl`, `mbstring`, `pdo_mysql`, `bcmath`, `intl`; MySQL/MariaDB.
- Cron per 1 menit (kalau hanya 5 menit, kedaluwarsa pesanan dan antrean ikut terlambat).
- Document root menunjuk ke `public/`. SSH sangat disarankan.
- `allow_url_fopen`/cURL keluar ke Jubelio, DANA (Gapura), dan RajaOngkir tidak diblokir.

## Langkah
1. `composer install --no-dev -o` (di server, atau di laptop lalu upload `vendor/`).
2. `npm run build` di laptop, upload `public/build`.
3. Isi `.env` produksi: `APP_ENV=production`, `APP_DEBUG=false`, `QUEUE_CONNECTION=database`,
   `MAIL_MAILER=smtp`, kredensial Jubelio/Gapura/RajaOngkir, `SANCTUM_STATEFUL_DOMAINS` dan `SESSION_DOMAIN`
   sesuai domain. Letakkan `storage/app/gapura/private_key.pem` di luar akses web (chmod 600).
4. `php artisan key:generate` (sekali), `php artisan migrate --force`, `php artisan storage:link`,
   lalu `php artisan config:cache route:cache view:cache`.
5. Cron (satu-satunya yang dibutuhkan):
   `* * * * * cd /path/ke/project && php artisan schedule:run >> /dev/null 2>&1`

## Catatan
- **Antrean tidak bergantung pada cron.** Setelah sebuah request web yang menambah job ke antrean `database` selesai dijawab, aplikasi memproses antrean sampai 25 detik (`App\Support\QueueKicker`, `QUEUE_KICK_ON_REQUEST`, default `true`). Notifikasi pembayaran DANA, email (reset password, invoice), dan panggilan ke Jubelio karenanya selesai dalam hitungan detik. Jadwal (sinkron katalog dan stok Jubelio, pemeriksaan pesanan kedaluwarsa) dijalankan dengan cara yang sama: setelah sebuah request web dijawab, tugas yang jatuh tempo dijalankan di dalam proses itu, paling banyak sekali per menit (`App\Support\ScheduleKicker`, `SCHEDULE_KICK_ON_REQUEST`, default `true`). Tugas yang terlewat karena tidak ada kunjungan pada menit jatuh temponya dikejar pada kunjungan berikutnya, satu kali per tugas (sampai 24 jam ke belakang), jadi sinkron tetap jalan selama ada kunjungan, walau cron mati. Cron `schedule:run` tetap dipasang sebagai cadangan; di Hostinger cron terbukti hanya berjalan sekali lalu berhenti (diuji dengan `date >> file` tiap menit), jadi jangan mengandalkannya untuk antrean.
- Job tertunda hingga ±1 menit (email, sinkronisasi Jubelio, pembatalan). Retry/backoff dan `failed_jobs` tetap bekerja.
- Lokal: `composer dev` sudah menjalankan `queue:listen`; worker dari scheduler hanya menambah, tidak merusak.
- Cek `php artisan queue:failed` berkala.
