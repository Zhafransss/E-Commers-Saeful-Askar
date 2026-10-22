# Panduan client: mendapatkan kredensial Gapura (DANA) dan RajaOngkir

Panduan ini untuk pemilik toko. Isinya langkah-langkah mendapatkan data yang kami butuhkan agar
**pembayaran (Gapura/DANA)** dan **ongkos kirim (RajaOngkir)** berjalan di website.

> **Keamanan.** Data bertanda 🔒 bersifat rahasia. Jangan kirim lewat chat atau email biasa dan jangan difoto layar
> di grup. Gunakan pengelola kata sandi (Bitwarden/1Password) atau sampaikan langsung ke kami lewat jalur yang disepakati.

---

## Bagian A. Gapura (pembayaran DANA)

### Yang kami butuhkan dari Anda

| Data | Keterangan |
|---|---|
| **Merchant ID** | Nomor identitas toko Anda di DANA |
| **Client ID** (disebut juga X-PARTNER-ID) 🔒 | Kode akses toko ke sistem pembayaran DANA |
| **Kunci publik DANA** (file `.pem`) | File dari DANA, dipakai untuk memastikan pemberitahuan pembayaran benar-benar dari DANA |
| **Kode MCC** | Kode 4 digit kategori usaha Anda yang tercatat di DANA |
| **Alamat website (domain) final** | Contoh `https://namatoko.id`. Harus memakai HTTPS dan jangan diganti setelah didaftarkan |
| **Batas waktu bayar** | Berapa menit pelanggan boleh membayar sebelum pesanan otomatis batal (sekarang 10 menit) |

### Langkah 1. Daftar dan verifikasi bisnis di DANA

1. Buka [Merchant Portal DANA](https://dashboard.dana.id) dan daftar (gratis). Siapkan email dan nomor HP aktif.
2. Isi informasi bisnis dasar. Anda akan masuk ke dashboard onboarding.
3. Klik **Upload Documents**, lalu unggah dokumen legal usaha. Daftar dokumen yang diminta muncul di layar dan
   tergantung jenis usaha (ada tombol **View Document Example** sebagai contoh).
4. Lengkapi profil bisnis, **pilih produk "Gapura Hosted Checkout"**, isi alamat bisnis (harus sama persis dengan dokumen),
   dan isi **rekening bank untuk pencairan dana**.
5. Tunggu persetujuan DANA, sampai 2 hari kerja.

Panduan DANA: [Getting Started Onboarding](https://dashboard.dana.id/api-docs-v2/guide/getting-started/getting-started-onboarding) ·
[Business Verification Overview](https://dashboard.dana.id/api-docs-v2/guide/getting-started/business-verification-overview)

### Langkah 2. Kirim form produksi

Setelah akun terverifikasi dan kami selesai menyiapkan integrasi, kami akan memberi Anda satu file bernama
**`rsa_public_key.pem`** (kunci publik toko; ini aman dibagikan, berbeda dengan kunci privat yang hanya ada di server kami).

1. Masuk ke [Merchant Portal](https://dashboard.dana.id) dan buka menu **Production Submission**.
2. Unggah file `rsa_public_key.pem` dari kami.
3. Isi alamat berikut (ganti `namatoko.id` dengan domain Anda):
   - **Notify URL:** `https://namatoko.id/v1.0/debit/notify` (tulisan setelah domain harus persis seperti ini)
   - **Redirect URL:** `https://namatoko.id/checkout/finish`
4. Kirim untuk ditinjau. DANA mendaftarkan kedua alamat itu dan menerbitkan kredensial produksi setelah disetujui.

Panduan DANA: [Integration Overview](https://dashboard.dana.id/api-docs-v2/guide/getting-started/integration-overview) ·
[Authentication (kunci)](https://dashboard.dana.id/api-docs-v2/guide/authentication/authentication-asymmetric) ·
[Gapura Hosted Checkout](https://dashboard.dana.id/api-docs-v2/guide/payment-gateway/hosted-checkout)

### Langkah 3. Ambil Merchant ID dan Client ID

Setelah disetujui, DANA mengirim email ke alamat login Merchant Portal Anda (dan email UAT Sign-Off).
Catat **Merchant ID** dan **Client ID** dari sana, lalu serahkan ke kami. DANA juga memberi *Client Secret*,
tetapi **tidak kami perlukan**, jadi tidak perlu dikirim.

### Langkah 4. Minta kunci publik DANA dan kode MCC

Dua hal ini tidak tertulis di dokumentasi DANA, jadi diminta langsung ke tim integrasi DANA:

1. Gabung ke Discord tim integrasi DANA: [s.dana.id/dana-discord-dev](https://s.dana.id/dana-discord-dev).
2. Minta **kunci publik DANA untuk produksi** (file `.pem`).
3. Tanyakan **kode MCC** yang terdaftar untuk usaha Anda.
4. Sekalian tanyakan: apakah kunci publik yang sama dipakai untuk memverifikasi *Finish Notify* (pemberitahuan pembayaran).
5. Serahkan file dan kodenya ke kami.

### Langkah 5. Tanda tangan perjanjian dan uji pilot

1. **Perjanjian kerja sama (PKS)** dikirim DANA lewat email **DocuSign**. Penandatangan sebaiknya pemilik usaha atau PIC administrasi.
2. Setelah kredensial produksi terbit, DANA meminta **uji pilot produksi** (kami yang menjalankan pengujiannya).
3. Selama PKS dan uji pilot belum selesai, DANA membatasi transaksi: **maksimal 5 transaksi dan Rp50.000 per hari**.
4. Setelah keduanya selesai, muncul pemberitahuan bahwa akun bisnis aktif. Toko baru bisa menerima pembayaran penuh.

Panduan DANA: [Production Testing and Soft Go Live](https://dashboard.dana.id/api-docs-v2/guide/getting-started/go-live-preparation)

### Keputusan yang perlu dari Anda

| Pertanyaan | Catatan |
|---|---|
| Berapa menit batas waktu bayar? | Sekarang 10 menit. Terlalu singkat membuat pelanggan gagal bayar, terlalu lama menahan stok. |
| Metode pembayaran apa yang ingin ditampilkan? (Virtual Account bank, QRIS, e-wallet DANA, dll.) | Sampaikan ke kami agar sesuai dengan yang diaktifkan di DANA. |

---

## Bagian B. RajaOngkir (ongkos kirim)

### Yang kami butuhkan dari Anda

| Data | Keterangan |
|---|---|
| **API Key Shipping Cost** 🔒 | Kunci akses ke layanan hitung ongkir |
| **Alamat gudang pengirim yang pasti** | Kelurahan, kecamatan, kota/kabupaten, provinsi, dan kode pos tempat kurir menjemput barang. Kami memakai alamat ini sebagai titik asal perhitungan. |
| **Daftar kurir yang dilayani toko** | Pilih dari: JNE, SiCepat, J&T, Ninja, TIKI, POS Indonesia, SAP, Lion Parcel, SPX, ID Express, Wahana, Sentral Cargo, Rex |
| **Paket langganan** | Pilih sesuai jumlah pengunjung (lihat langkah 3) |

### Langkah 1. Daftar akun Komerce/RajaOngkir

1. Buka [collaborator.komerce.id](https://collaborator.komerce.id/login) dan daftar atau masuk dengan akun toko.
2. Lengkapi data akun bila diminta.

### Langkah 2. Ambil API Key

1. Setelah masuk ke dashboard, cari bagian **API Key** untuk layanan **Shipping Cost**
   (di menu profil bagian APIKEY, atau menu *Integration* → *Api Key*; tampilan dashboard bisa berbeda).
2. Salin **Shipping Cost API Key** dan serahkan ke kami. Jangan memakai key untuk layanan lain (misalnya Shipping Delivery).

Panduan: [Cara mendapatkan API key](https://rajaongkir.com/docs/shipping-cost/getting_started/apikey) ·
[Tentang Shipping Cost](https://rajaongkir.com/docs/shipping-cost/getting_started/about)

### Langkah 3. Pilih paket

Paket gratis kira-kira **100 pengecekan ongkir per hari**. Hasil ongkir sudah kami simpan sementara (30 menit per alamat dan berat),
tetapi toko yang ramai tetap bisa melewati kuota, dan ongkir tidak tampil saat kuota habis.
Cek pilihan dan harga paket langsung di dashboard Komerce, lalu pilih sesuai perkiraan pengunjung per hari.

### Langkah 4. Konfirmasi alamat gudang dan kurir

1. Pastikan alamat gudang yang kami tulis di bawah benar. Jika berbeda, kirim alamat yang benar:
   *Gudang Tambun Utara, Kelurahan Satriajaya, Kecamatan Tambun Utara, Kab. Bekasi, Jawa Barat, 17566.*
2. Sampaikan kurir mana saja yang benar-benar melayani toko Anda. Pelanggan hanya akan melihat kurir yang Anda pilih.
3. Untuk barang besar dan berat seperti sepeda listrik (sekitar 32 kg), pastikan ada kurir dengan layanan kargo.
4. Beri tahu juga bila toko ingin menawarkan asuransi pengiriman. Saat ini biaya asuransi `0`.

---

## Ringkasan: yang diserahkan ke kami

| # | Data | Dari mana |
|---|---|---|
| 1 | Merchant ID | Email DANA setelah form produksi disetujui |
| 2 | Client ID 🔒 | Email DANA yang sama |
| 3 | Kunci publik DANA (file `.pem`) | Tim integrasi DANA (Discord) |
| 4 | Kode MCC | Tim integrasi DANA (Discord) |
| 5 | Domain website final (HTTPS) | Keputusan Anda |
| 6 | Batas waktu bayar dan metode pembayaran | Keputusan Anda |
| 7 | API Key Shipping Cost 🔒 | Dashboard Komerce/RajaOngkir |
| 8 | Alamat gudang yang pasti | Konfirmasi Anda |
| 9 | Daftar kurir yang dilayani | Keputusan Anda |
| 10 | Paket RajaOngkir yang dipilih | Keputusan Anda |
