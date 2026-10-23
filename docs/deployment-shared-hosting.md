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

## Rilis lewat `git pull` (tanpa upload manual)

Folder project di server adalah salinan Git dari branch `deployment`. Satu rilis:

1. **Di laptop:** commit perubahan, lalu bila ada perubahan di `resources/js`, `resources/css`, atau kelas Tailwind di Blade: `npm run build` dan commit hasilnya (`git add public/build`). Di branch ini `public/build/` ikut dilacak Git karena server tidak punya Node. Lalu `git push origin deployment`.
2. **Di server (SSH):** `cd ~/domains/<domain>/app && bash scripts/deploy.sh`.

`scripts/deploy.sh` mengambil commit terbaru, menjalankan `composer install --no-dev -o`, `php artisan migrate --force`, dan `php artisan optimize:clear`. Skrip berhenti dengan pesan jelas kalau ada file berlacak di server yang berbeda dari GitHub (misalnya hasil upload manual atau edit langsung), supaya tidak ada yang tertimpa diam-diam. `.env`, `storage/`, dan `vendor/` tidak dilacak Git dan tidak tersentuh.

Server mengambil kode lewat HTTPS dari repo publik, tanpa kunci atau password (hanya bisa membaca). Karena repo publik, jangan pernah meng-commit rahasia (`.env`, kunci `.pem`, kredensial): semuanya sudah diabaikan di `.gitignore`. Kalau repo suatu saat dijadikan privat, pakai *deploy key* hanya-baca (Settings → Deploy keys) dan ubah alamat `origin` ke bentuk SSH; jangan menyimpan password atau token GitHub pribadi di server.

Pertama kali (folder di server belum berupa repo Git): `git init -b deployment`, `git remote add origin https://github.com/Zhafransss/E-Commers-Saeful-Askar.git`, `git fetch origin deployment`, `git reset --hard origin/deployment`, lalu `bash scripts/deploy.sh`. `reset --hard` hanya mengganti file yang dilacak Git; `.env`, `storage/`, dan `vendor/` tetap utuh.

## Catatan
- **Antrean tidak bergantung pada cron.** Setelah sebuah request web yang menambah job ke antrean `database` selesai dijawab, aplikasi memproses antrean sampai 25 detik (`App\Support\QueueKicker`, `QUEUE_KICK_ON_REQUEST`, default `true`). Notifikasi pembayaran DANA, email (reset password, invoice), dan panggilan ke Jubelio karenanya selesai dalam hitungan detik. Jadwal (sinkron katalog dan stok Jubelio, pemeriksaan pesanan kedaluwarsa) dijalankan dengan cara yang sama: setelah sebuah request web dijawab, tugas yang jatuh tempo dijalankan di dalam proses itu, paling banyak sekali per menit (`App\Support\ScheduleKicker`, `SCHEDULE_KICK_ON_REQUEST`, default `true`). Tugas yang terlewat karena tidak ada kunjungan pada menit jatuh temponya dikejar pada kunjungan berikutnya, satu kali per tugas (sampai 24 jam ke belakang), jadi sinkron tetap jalan selama ada kunjungan, walau cron mati. Cron `schedule:run` tetap dipasang sebagai cadangan; di Hostinger cron terbukti hanya berjalan sekali lalu berhenti (diuji dengan `date >> file` tiap menit), jadi jangan mengandalkannya untuk antrean.
- Job tertunda hingga ±1 menit (email, sinkronisasi Jubelio, pembatalan). Retry/backoff dan `failed_jobs` tetap bekerja.
- Lokal: `composer dev` sudah menjalankan `queue:listen`; worker dari scheduler hanya menambah, tidak merusak.
- Cek `php artisan queue:failed` berkala.
