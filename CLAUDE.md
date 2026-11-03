# Panduan untuk Claude: E-Commers Saeful Askar

Dokumen ini dibaca otomatis oleh Claude di awal percakapan. Isinya konteks proyek, cara deploy, dan jebakan
yang sudah pernah terjadi. Perbarui bila ada hal penting yang berubah. **Jangan pernah menulis rahasia di sini**
(repo ini publik): password, API key, isi `.env`, isi file `.pem`.

## Aturan kerja dengan pemilik proyek

- **Balas dalam bahasa Indonesia.**
- **Commit/push hanya saat diminta.** Pesan commit **tanpa baris `Co-Authored-By`** (pemilik proyek selalu meminta "tanpa co author").
- Pemilik biasanya menempel output terminal/screenshot lalu bertanya langkah berikutnya.
- Terminal cPanel memotong baris panjang yang ditempel: berikan **satu perintah per baris**, pendek, dan satu blok kode per perintah.
- Jangan minta pemilik menempel password, `.env`, atau isi kunci di chat. Minta hanya nama variabel atau hasil `OK`/`ada`.
- Pembayaran DANA produksi memakai **uang asli**. Jangan berspekulasi soal penyebab masalah: verifikasi dengan bukti (log, `gapura:diagnose`, query) sebelum menyimpulkan atau menyuruh deploy.
- Pemilik mengingatkan: periksa kode dulu sebelum berargumen.

## Gambaran proyek

Toko sepeda listrik. Laravel 12, PHP 8.2+, Blade + Alpine.js + Tailwind 4 + Vite, Sanctum (cookie sesi), MySQL untuk
data/cache/antrean/sesi/lock (tanpa Redis), tes Pest (±950 lulus), Pint untuk gaya kode.

Integrasi:
- **Jubelio**: katalog, stok, dan pengiriman pesanan (sales order). `app/Services/Jubelio/`, webhook di `/webhooks/jubelio/{product,price,stock,salesorder}`.
- **Gapura / DANA** (SNAP, Hosted Checkout): pembayaran. `app/Services/Gapura/`. Permintaan ke DANA ditandatangani kunci privat kita; notifikasi `Finish Notify` (`/v1.0/debit/notify`) diverifikasi dengan kunci publik DANA.
- **RajaOngkir**: ongkir dan kode pos. `SHIPPING_DRIVER=rajaongkir`, kurir produksi hanya Wahana (`RAJAONGKIR_COURIERS=wahana`). Isi otomatis kode pos hanya jalan dengan driver rajaongkir.
- **Email**: SMTP Gmail (`smtp.gmail.com:465`, `smtps`, app password).

Dokumen lain: `README.md`, `docs/PRD-Website-ECommerce-Saeful-Askar.md` (acuan utama), `docs/api/`,
`docs/arsitektur-sistem.md`, `docs/erd.md`, `docs/deployment-shared-hosting.md`, `docs/kebutuhan-client-gapura-rajaongkir.md`.

## Branch

| Branch | Fungsi |
|---|---|
| `main` | Versi stabil untuk VPS (worker Supervisor) |
| `deployment` | **Dipakai untuk shared hosting dan semua pekerjaan saat ini.** `public/build/` ikut dilacak Git karena server tanpa Node |
| `development`, `backend`, `frontend` | Alur tim lama |

Jangan merge `deployment` ke `main`; ambil perubahan dari `main` lewat `git merge main` ke `deployment`.
Remote: `https://github.com/Zhafransss/E-Commers-Saeful-Askar` (publik).

## Menjalankan lokal

```
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer dev          # server + queue:listen + vite
php artisan test      # atau ./vendor/bin/pest
vendor/bin/pint       # gaya kode
```

Bila mengubah `resources/js`, `resources/css`, atau kelas Tailwind di Blade: jalankan `npm run build` lalu **commit `public/build`**
(server tidak punya Node).

## Server produksi (Rumahweb, cPanel) — target utama

| Hal | Nilai |
|---|---|
| Domain | `store.saefulaskar.com` (SSL aktif, DNS Cloudflare "DNS only") |
| Akun cPanel | `dmkd8164` (host "kampar") |
| Folder aplikasi | `~/saefulaskar-app` (salinan Git branch `deployment`) |
| Web root (document root tetap) | `~/public_html/store.saefulaskar.com` |
| `.env` server | `DEPLOY_WEB_ROOT=/home/dmkd8164/public_html/store.saefulaskar.com` |

Aplikasi berada di luar `public_html`; web root berisi salinan `public/` dan `index.php` yang menunjuk ke folder aplikasi.
Pengerjaan lewat **Terminal di cPanel** (pemilik menempelkan perintah dari Claude).

### Cara deploy (rilis)

1. **Di laptop**: perubahan selesai, tes lulus, `npm run build` bila perlu, commit, lalu `git push origin deployment` (hanya saat pemilik meminta commit/push).
2. **Di server (Terminal cPanel)**, satu per satu:

```
cd ~/saefulaskar-app
```
```
bash scripts/deploy.sh
```
```
git log --oneline -1
```

Baris terakhir harus menunjukkan commit terbaru yang baru di-push. Bila ada perintah artisan baru, jalankan setelah deploy.

`scripts/deploy.sh` melakukan, berurutan:
1. `git fetch origin deployment`; **berhenti** bila ada file berlacak di server yang berbeda dari Git (kemungkinan edit manual). Bila file itu boleh ditimpa: `git checkout -- .` lalu ulangi.
2. `git merge --ff-only origin/deployment`.
3. `composer install --no-dev --optimize-autoloader --no-scripts` (pakai `composer` global atau `~/composer.phar`), lalu `php artisan package:discover`.
4. `php artisan migrate --force`.
5. `php artisan optimize:clear`.
6. Bila `.env` punya `DEPLOY_WEB_ROOT`: salin `public/build`, `public/images`, favicon ke web root. `index.php` dan `.htaccess` di sana **tidak disentuh**.

`.env`, `storage/`, dan `vendor/` tidak dilacak Git dan tidak tersentuh. Setelah deploy yang mengubah `.env`: `php artisan optimize:clear`.
Server memakai `config` tanpa cache (`optimize:clear`), jadi perubahan `.env` langsung berlaku setelah itu.

Setup pertama (folder server bukan repo Git): `git init -b deployment`, `git remote add origin <repo>`, `git fetch origin deployment`,
`git reset --hard origin/deployment`, lalu `bash scripts/deploy.sh`. Detail: `docs/deployment-shared-hosting.md`.

### Variabel `.env` server yang penting (nilai rahasia tidak dicatat)

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS` = `store.saefulaskar.com`
- `QUEUE_CONNECTION=database`
- `GAPURA_ENV=production`, `GAPURA_MCC=5940`, `GAPURA_ORIGIN`, ID merchant/client; `GAPURA_PRIVATE_KEY_PATH=storage/app/gapura/private_key.pem`, `GAPURA_DANA_PUBLIC_KEY_PATH=storage/app/gapura/dana_public_key.pem` (path relatif ke root aplikasi)
- `ORDER_NUMBER_PREFIX=WS` (nomor pesanan `WS-000022`, dikirim ke Jubelio sebagai `ref_no`), `JUBELIO_USE_ORDER_NUMBER=true` (nomor pesanan jadi `salesorder_no` di Jubelio, bukan `[auto]`)
- `JUBELIO_WEBHOOK_SECRET` harus sama dengan Secret Key di Jubelio
- `MAIL_*` Gmail SMTP, `RAJAONGKIR_*`, `SHIPPING_DRIVER=rajaongkir`
- Kunci di `storage/app/gapura/`: `private_key.pem` (chmod 600, PKCS#8), `rsa_public_key.pem` (kunci publik kita, sudah didaftarkan ke DANA), `dana_public_key.pem` (kunci publik DANA). Jangan pernah di-commit.

Override satu kali tanpa mengubah `.env`: `GAPURA_MCC=5571 php artisan gapura:diagnose`.

### Server kedua (Hostinger) — sengaja DIABAIKAN

Ada server Hostinger lama (`~/domains/saefulaskar.veloradigitalworks.com/app`, SSH port 65002). Pemilik meminta fokus ke Rumahweb;
Hostinger tertinggal di commit lama dan belum menerima perbaikan terbaru. Pastikan perintah ditujukan ke server yang benar
(pernah tertukar): Rumahweb berfolder `~/saefulaskar-app`.

## Antrean dan penjadwal di shared hosting (penting)

Tidak ada worker permanen dan `proc_open`/`shell_exec` **dinonaktifkan** di Rumahweb (web dan CLI):
- `App\Support\QueueKicker`: setelah request web yang menambah job `database`, antrean dikerjakan di proses yang sama (≤25 dtk). Notifikasi DANA, email, dan push ke Jubelio bergantung pada ini.
- `App\Support\ScheduleKicker`: tugas terjadwal yang jatuh tempo dijalankan setelah request web (maks sekali per menit, mengejar yang terlewat). **Melewati `jubelio:sync-products`** (terlalu lama untuk request web; diserahkan ke cron).
- **Cron** (ada di Rumahweb): `* * * * * cd /home/dmkd8164/saefulaskar-app && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1`.
- `routes/console.php` memakai `Schedule::call(fn () => Artisan::call(...))` agar tugas berjalan **di dalam proses** (bukan `Schedule::command()` yang butuh proses anak/`proc_open`). Jangan kembalikan ke `Schedule::command()`; ada tes yang menjaganya.
- Sinkron manual dari admin masuk antrean `sync` pada koneksi `database-long` (`retry_after` 1860) dan hanya dikerjakan worker cron. Log sinkron `running` >30 menit ditutup `failed` oleh `SyncLog::failStale()`.

Tugas terjadwal: kategori 02:00, produk penuh 02:30, produk perubahan tiap 30 menit, rekonsiliasi stok tiap 15 menit, `orders:expire-unpaid` tiap 5 menit, `sync-logs:prune` harian.

## Pembayaran DANA (Gapura)

- Create Order idempoten per `partnerReferenceNo` (= nomor pesanan). DANA mengulang `Finish Notify` hingga 7 hari bila tidak dijawab sukses.
- Pengujian produksi DANA ("pilot") dibatasi sekitar 5 transaksi / Rp50.000 per hari: jangan buka toko ke publik sebelum batas dicabut.
- **Pelajaran penting**: SDK `danaid/dana-php` (`WebhookParser::create`) memakai `file_exists()` terhadap *working directory PHP*. Di web itu document root (`public_html/...`), di CLI itu root aplikasi. Path kunci publik DANA harus di-resolve dengan `base_path()` (sudah diperbaiki di `GapuraClient::parser()`, commit `98b115d`). Gejala bila rusak: pesanan tertahan `pending_payment`, halaman "Memeriksa status pembayaran…", log "Signature webhook tidak valid" padahal uang sudah terpotong.
- `php artisan gapura:diagnose` mengirim satu Create Order uji (`DIAG-<waktu>`, `--amount=`) atau `--status=<nomor-pesanan>` (Query Payment) dan mencetak request/respons lengkap. **Keluarannya bisa memuat tautan checkout bertanda tangan: jangan dibagikan.** Header `X-SIGNATURE` sudah dipotong.
- Admin punya aksi "segarkan pembayaran" di detail pesanan (rekonsiliasi lewat Query Payment) untuk pesanan yang notifikasinya terlewat.
- Cek notifikasi: `SELECT id, provider, signature_valid, error, created_at FROM webhook_events ORDER BY id DESC LIMIT 3;` (`signature_valid = 1` = sehat).
- Error DANA umum: HTTP 500 `5005401/5005501` dengan body generik (masalah di sisi DANA, pernah pulih sendiri), XML `QpsLimitExceeded` dari halaman hosted DANA (coba lagi).

## Log dan diagnosis cepat

```
tail -n 80 storage/logs/laravel.log
php artisan queue:failed
php artisan schedule:list
php artisan mail:test <alamat-asli>
php artisan about
```

Tabel berguna: `orders`, `webhook_events`, `sync_logs`, `jobs`, `failed_jobs`.

## Status pekerjaan / sisa tugas (per 3 Nov 2026)

- Deploy `98b115d` ke Rumahweb lalu uji satu pembayaran kecil baru: pesanan harus otomatis `paid` dan `webhook_events.signature_valid = 1`. Pesanan `WS-000022` (Rp 6.500) sudah SUCCESS di DANA tetapi masih `pending_payment`; selesaikan lewat "segarkan pembayaran". `WEBSTORE-000011` seharusnya ditutup `orders:expire-unpaid`.
- Webhook Jubelio ke `store.saefulaskar.com` (isi `JUBELIO_WEBHOOK_SECRET`, 4 URL di atas) belum dipindah dari Hostinger.
- Uji email ke alamat asli (`mail:test`) dan alur lupa password.
- Kebersihan keamanan: hapus `env.production.txt` dan `saefulaskar-app.zip` di `~/Downloads/saefulaskar-deploy/`, hapus cadangan `~/env-backup-*` di server, rotasi password yang dipakai saat setup, hapus domain staging `fachrullahzhafran.space`, aktifkan Force HTTPS Redirect di cPanel, hapus file uji sementara (`zz-cwd-*.php`) bila masih ada.
- Bug halaman detail produk: "Trying to access array offset on null" di `resources/views/pages/products/show.blade.php` (kemungkinan produk tanpa varian/kategori), belum diselidiki.
- Placeholder kode pos "Terisi otomatis" menyesatkan bila Jubelio tidak memberi kode pos (opsional diubah).
- `docs/pindah-ke-domain-produksi.md` belum di-commit (pemilik belum meminta).
- Batalkan/tandai pesanan uji (`WEBSTORE-*`, `WS-*`) di Jubelio.
- File daftar MCC DANA (`Merchant Category Code ... Confidential.xlsx`) bersifat rahasia: jangan di-commit atau dibagikan.
