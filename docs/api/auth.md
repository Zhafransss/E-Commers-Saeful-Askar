# Auth — Registrasi, Login, Profil, Password

Konvensi umum (header, format error, kode error) ada di `README.md`. Autentikasi memakai session cookie Laravel (Sanctum stateful).

Objek **User** yang dipakai di file ini:

```json
{
  "id": 21,
  "name": "Budi Santoso",
  "email": "budi@example.com",
  "phone": "081234567890",
  "role": "customer",
  "email_verified_at": null,
  "has_default_address": true,
  "created_at": "2026-10-14T21:00:00+07:00"
}
```

`role`: `customer` | `admin`. `jubelio_contact_id` tidak pernah dikirim ke frontend.

AUTH-02, AUTH-03, dan AUTH-06 mengembalikan objek User di `data` beserta `message` untuk ditampilkan ke pengguna (pola sama dengan CART-02 dan ADDR-02). AUTH-05 hanya membaca, jadi tanpa `message`.

---

## AUTH-01 · Ambil cookie CSRF

### Endpoint

`GET /sanctum/csrf-cookie` · **Publik** · bawaan Laravel Sanctum (di luar prefix `/api/v1`)

### User Story

Sebagai frontend, saya ingin mendapatkan cookie `XSRF-TOKEN` sebelum mengirim request POST/PUT/PATCH/DELETE, agar request saya lolos verifikasi CSRF.

### Request Body

Tidak ada.

### Response Success

`204 No Content`, tanpa body. Response memasang cookie `XSRF-TOKEN` dan cookie session.

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 500 | `SERVER_ERROR` | Konfigurasi session/cookie salah |

### Acceptance Criteria

- [ ] Response 204 memasang cookie `XSRF-TOKEN` (bukan HttpOnly) dan cookie session (HttpOnly, `SameSite=Lax`, `Secure` di staging/produksi).
- [ ] `resources/js/api/client.js` memanggil endpoint ini otomatis bila cookie `XSRF-TOKEN` belum ada, lalu mengirim nilainya di header `X-XSRF-TOKEN`.
- [ ] Request non-GET tanpa header `X-XSRF-TOKEN` yang valid ditolak `419 CSRF_TOKEN_MISMATCH`.

---

## AUTH-02 · Registrasi

### Endpoint

`POST /api/v1/auth/register` · **Publik** · rate limit 5/menit

### User Story

Sebagai pengunjung, saya ingin membuat akun dengan nama, email, nomor HP, dan password, agar saya bisa membeli produk dan melacak pesanan.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `name` | string | Ya | 2–100 karakter |
| `email` | string | Ya | Format email, maks 191, unik (tidak peka huruf besar) |
| `phone` | string | Ya | Nomor Indonesia, 10–15 digit, diawali `08` atau `628`; disimpan dalam format `08…`. Satu nomor hanya untuk satu akun: yang sudah dipakai akun lain → `422 VALIDATION_ERROR`, `errors.phone`: "Nomor HP sudah terdaftar di akun lain." |
| `password` | string | Ya | Min 8 karakter, ada huruf dan angka |
| `password_confirmation` | string | Ya | Sama dengan `password` |

```json
{
  "name": "Budi Santoso",
  "email": "budi@example.com",
  "phone": "081234567890",
  "password": "rahasia123",
  "password_confirmation": "rahasia123"
}
```

### Response Success

`201 Created`. User langsung login (session dibuat).

```json
{
  "data": {
    "id": 21,
    "name": "Budi Santoso",
    "email": "budi@example.com",
    "phone": "081234567890",
    "role": "customer",
    "email_verified_at": null,
    "has_default_address": false,
    "created_at": "2026-10-14T21:00:00+07:00"
  },
  "message": "Pendaftaran berhasil."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 419 | `CSRF_TOKEN_MISMATCH` | AUTH-01 belum dipanggil |
| 422 | `VALIDATION_ERROR` | Field kosong/salah format, email sudah terdaftar, password tidak cocok |
| 429 | `TOO_MANY_REQUESTS` | Lebih dari 5 percobaan/menit |

```json
{
  "message": "Data yang dikirim tidak valid.",
  "code": "VALIDATION_ERROR",
  "errors": {
    "email": ["Email sudah terdaftar."],
    "password": ["Password minimal 8 karakter dan mengandung huruf serta angka."]
  }
}
```

### Acceptance Criteria

- [ ] User baru tersimpan dengan `role = customer`, password di-hash, `jubelio_contact_id = null`.
- [ ] Email disimpan huruf kecil; registrasi kedua dengan email sama (beda huruf besar) ditolak.
- [ ] Nomor `6281234567890` dinormalisasi menjadi `081234567890`.
- [ ] Setelah sukses, `GET /auth/me` mengembalikan user ini tanpa login ulang.
- [ ] Contact Jubelio **tidak** dibuat saat registrasi (dibuat saat checkout pertama).
- [ ] Pesan error dalam Bahasa Indonesia per field.

---

## AUTH-03 · Login

### Endpoint

`POST /api/v1/auth/login` · **Publik** · rate limit 5/menit per IP + email

### User Story

Sebagai customer, saya ingin masuk dengan email dan password, agar bisa mengakses keranjang, alamat, dan pesanan saya.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `email` | string | Ya | Format email |
| `password` | string | Ya | — |
| `remember` | boolean | Tidak | Default `false`; `true` = session diingat 30 hari |

```json
{
  "email": "budi@example.com",
  "password": "rahasia123",
  "remember": true
}
```

### Response Success

`200 OK`. Session ID diregenerasi.

```json
{
  "data": {
    "id": 21,
    "name": "Budi Santoso",
    "email": "budi@example.com",
    "phone": "081234567890",
    "role": "customer",
    "email_verified_at": null,
    "has_default_address": true,
    "created_at": "2026-10-14T21:00:00+07:00"
  },
  "message": "Login berhasil."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 419 | `CSRF_TOKEN_MISMATCH` | Cookie XSRF tidak ada |
| 422 | `INVALID_CREDENTIALS` | Email tidak terdaftar atau password salah |
| 422 | `VALIDATION_ERROR` | Email/password kosong |
| 429 | `TOO_MANY_REQUESTS` | Lebih dari 5 percobaan gagal/menit |

```json
{
  "message": "Email atau password salah.",
  "code": "INVALID_CREDENTIALS"
}
```

### Acceptance Criteria

- [ ] Pesan error sama untuk email tidak terdaftar dan password salah (tidak membocorkan email mana yang terdaftar).
- [ ] Session ID berganti setelah login sukses.
- [ ] `role` di response dipakai frontend untuk menampilkan menu admin.
- [ ] Setelah 5 kali gagal dalam 1 menit, percobaan ke-6 dibalas 429 dengan header `Retry-After`.
- [ ] Bila pengunjung datang dari tombol "Tambah ke keranjang", frontend kembali ke halaman produk setelah login.

---

## AUTH-04 · Logout

### Endpoint

`POST /api/v1/auth/logout` · **Customer**

### User Story

Sebagai customer, saya ingin keluar dari akun, agar perangkat yang saya pakai tidak lagi bisa mengakses akun saya.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "message": "Berhasil keluar."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Session sudah habis |
| 419 | `CSRF_TOKEN_MISMATCH` | Cookie XSRF tidak ada |

### Acceptance Criteria

- [ ] Session dihapus dan token CSRF diregenerasi.
- [ ] Setelah logout, `GET /auth/me` dibalas 401.
- [ ] Isi keranjang tetap tersimpan di database dan muncul lagi saat login berikutnya.

---

## AUTH-05 · Data user yang sedang login

### Endpoint

`GET /api/v1/auth/me` · **Customer**

### User Story

Sebagai frontend, saya ingin mengetahui siapa yang sedang login saat halaman dimuat, agar header menampilkan nama user, badge keranjang, dan menu admin bila perlu.

### Request Body

Tidak ada.

### Response Success

`200 OK` — objek **User**.

```json
{
  "data": {
    "id": 21,
    "name": "Budi Santoso",
    "email": "budi@example.com",
    "phone": "081234567890",
    "role": "customer",
    "email_verified_at": null,
    "has_default_address": true,
    "created_at": "2026-10-14T21:00:00+07:00"
  }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |

```json
{
  "message": "Silakan masuk terlebih dahulu.",
  "code": "UNAUTHENTICATED"
}
```

### Acceptance Criteria

- [ ] Pengunjung yang belum login mendapat 401 (frontend menganggapnya "tamu", bukan error yang ditampilkan).
- [ ] Response tidak pernah memuat `password`, `remember_token`, atau `jubelio_contact_id`.
- [ ] `has_default_address` benar sesuai data `addresses`.

---

## AUTH-06 · Ubah profil

### Endpoint

`PUT /api/v1/auth/profile` · **Customer**

### User Story

Sebagai customer, saya ingin memperbarui nama dan nomor HP, agar data pemesan di pesanan berikutnya benar.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `name` | string | Ya | 2–100 karakter |
| `phone` | string | Ya | Sama dengan aturan registrasi |

Email tidak bisa diubah lewat endpoint ini.

```json
{
  "name": "Budi Santoso",
  "phone": "081298765432"
}
```

### Response Success

`200 OK` — objek **User** terbaru.

```json
{
  "data": {
    "id": 21,
    "name": "Budi Santoso",
    "email": "budi@example.com",
    "phone": "081298765432",
    "role": "customer",
    "email_verified_at": null,
    "has_default_address": true,
    "created_at": "2026-10-14T21:00:00+07:00"
  },
  "message": "Profil diperbarui."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 419 | `CSRF_TOKEN_MISMATCH` | Cookie XSRF tidak ada |
| 422 | `VALIDATION_ERROR` | Nama/nomor tidak valid |

```json
{
  "message": "Data yang dikirim tidak valid.",
  "code": "VALIDATION_ERROR",
  "errors": { "phone": ["Nomor HP tidak valid."] }
}
```

### Acceptance Criteria

- [ ] Nama dan nomor tersimpan; email tidak berubah walau dikirim.
- [ ] Bila user sudah punya `jubelio_contact_id`, backend mengantrekan update contact di Jubelio (INT-JB-09) dengan nama/nomor baru.
- [ ] Pesanan lama tidak berubah (alamat dan nama di order adalah salinan).

---

## AUTH-07 · Ganti password

### Endpoint

`PUT /api/v1/auth/password` · **Customer**

### User Story

Sebagai customer, saya ingin mengganti password, agar akun saya tetap aman.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `current_password` | string | Ya | Harus cocok dengan password sekarang |
| `password` | string | Ya | Min 8 karakter, huruf dan angka, beda dari password lama |
| `password_confirmation` | string | Ya | Sama dengan `password` |

```json
{
  "current_password": "rahasia123",
  "password": "rahasiaBaru456",
  "password_confirmation": "rahasiaBaru456"
}
```

### Response Success

`200 OK`

```json
{
  "message": "Password berhasil diubah."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 422 | `INVALID_CURRENT_PASSWORD` | Password lama salah |
| 422 | `VALIDATION_ERROR` | Password baru tidak memenuhi aturan atau konfirmasi tidak cocok |

```json
{
  "message": "Password saat ini salah.",
  "code": "INVALID_CURRENT_PASSWORD"
}
```

### Acceptance Criteria

- [ ] Password baru di-hash dan login berikutnya memakai password baru.
- [ ] Session lain milik user yang sama (perangkat lain) dikeluarkan; session saat ini tetap aktif.

---

## AUTH-08 · Lupa password

### Endpoint

`POST /api/v1/auth/forgot-password` · **Publik** · rate limit 5/menit

### User Story

Sebagai customer yang lupa password, saya ingin menerima tautan reset lewat email, agar bisa masuk lagi ke akun saya.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `email` | string | Ya | Format email |

```json
{
  "email": "budi@example.com"
}
```

### Response Success

`200 OK` — pesan yang sama baik email terdaftar maupun tidak.

```json
{
  "message": "Jika email terdaftar, tautan reset password sudah dikirim."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 419 | `CSRF_TOKEN_MISMATCH` | Cookie XSRF tidak ada |
| 422 | `VALIDATION_ERROR` | Email kosong/salah format |
| 429 | `TOO_MANY_REQUESTS` | Terlalu sering |

### Acceptance Criteria

- [ ] Email berisi tautan `https://<domain>/reset-password?token=…&email=…` yang berlaku 60 menit.
- [ ] Response tidak membedakan email terdaftar dan tidak terdaftar.
- [ ] Email dikirim lewat queue, bukan di dalam request.

---

## AUTH-09 · Reset password

### Endpoint

`POST /api/v1/auth/reset-password` · **Publik**

### User Story

Sebagai customer yang membuka tautan reset, saya ingin menetapkan password baru, agar bisa masuk lagi.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `token` | string | Ya | Dari tautan email |
| `email` | string | Ya | Dari tautan email |
| `password` | string | Ya | Min 8 karakter, huruf dan angka |
| `password_confirmation` | string | Ya | Sama dengan `password` |

```json
{
  "token": "3f1c9a…",
  "email": "budi@example.com",
  "password": "rahasiaBaru456",
  "password_confirmation": "rahasiaBaru456"
}
```

### Response Success

`200 OK`

```json
{
  "message": "Password berhasil diatur ulang. Silakan masuk."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 422 | `INVALID_RESET_TOKEN` | Token salah, sudah dipakai, atau lebih dari 60 menit |
| 422 | `VALIDATION_ERROR` | Password tidak memenuhi aturan |
| 429 | `TOO_MANY_REQUESTS` | Lebih dari 5 percobaan/menit (sama seperti AUTH-02/03/08) |

```json
{
  "message": "Tautan reset password tidak valid atau sudah kedaluwarsa.",
  "code": "INVALID_RESET_TOKEN"
}
```

### Acceptance Criteria

- [ ] Token hanya bisa dipakai sekali.
- [ ] Semua session lama milik user dikeluarkan setelah reset.
- [ ] User tidak otomatis login; frontend mengarahkan ke halaman login.
