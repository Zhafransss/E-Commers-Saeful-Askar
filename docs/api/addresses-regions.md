# Wilayah dan Buku Alamat

Data wilayah diambil dari endpoint Region Jubelio (INT-JB-08) dan di-cache backend, agar ID provinsi, kota, kecamatan, dan kelurahan di alamat customer sama persis dengan yang dipakai Jubelio saat membuat sales order. Konvensi umum ada di `README.md`.

Objek **Region**:

```json
{ "id": "32", "name": "JAWA BARAT" }
```

ID wilayah selalu string. Panjangnya: provinsi 2 digit, kota 4 digit, kecamatan 6 digit, kelurahan 10 digit.

---

## REG-01 · Daftar provinsi

### Endpoint

`GET /api/v1/regions/provinces` · **Customer**

### User Story

Sebagai customer yang mengisi alamat, saya ingin memilih provinsi dari daftar, agar alamat saya cocok dengan data wilayah pengiriman.

### Request Body

Tidak ada.

### Response Success

`200 OK` — daftar **Region**, urut nama, tanpa paginasi.

```json
{
  "data": [
    { "id": "36", "name": "BANTEN" },
    { "id": "31", "name": "DKI JAKARTA" },
    { "id": "32", "name": "JAWA BARAT" }
  ]
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 502 | `INTEGRATION_ERROR` | Cache kosong dan Jubelio gagal merespons |

```json
{
  "message": "Data wilayah sedang tidak bisa dimuat. Coba lagi beberapa saat.",
  "code": "INTEGRATION_ERROR"
}
```

### Acceptance Criteria

- [ ] Data diambil dari `GET /region/provinces` Jubelio dan di-cache 30 hari.
- [ ] Bila Jubelio gagal tapi cache ada, response tetap 200 dari cache.
- [ ] Panggilan kedua dalam masa cache tidak memanggil Jubelio.

---

## REG-02 · Daftar kota/kabupaten

### Endpoint

`GET /api/v1/regions/cities?province_id={province_id}` · **Customer**

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `province_id` | string | Ya | ID dari REG-01 |

### User Story

Sebagai customer, setelah memilih provinsi saya ingin memilih kota/kabupaten di provinsi itu.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "data": [
    { "id": "3216", "name": "KAB. BEKASI" },
    { "id": "3275", "name": "KOTA BEKASI" }
  ]
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 422 | `VALIDATION_ERROR` | `province_id` kosong |
| 502 | `INTEGRATION_ERROR` | Cache kosong dan Jubelio gagal |

```json
{
  "message": "Data yang dikirim tidak valid.",
  "code": "VALIDATION_ERROR",
  "errors": { "province_id": ["Provinsi wajib dipilih."] }
}
```

### Acceptance Criteria

- [ ] Memanggil `GET /region/cities?province_id=` Jubelio; di-cache 30 hari per `province_id`.
- [ ] `province_id` yang tidak dikenal menghasilkan `"data": []`, bukan error.

---

## REG-03 · Daftar kecamatan

### Endpoint

`GET /api/v1/regions/districts?city_id={city_id}` · **Customer**

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `city_id` | string | Ya | ID dari REG-02 |

### User Story

Sebagai customer, setelah memilih kota saya ingin memilih kecamatan.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "data": [
    { "id": "321605", "name": "TAMBUN UTARA" },
    { "id": "321611", "name": "TAMBUN SELATAN" }
  ]
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 422 | `VALIDATION_ERROR` | `city_id` kosong |
| 502 | `INTEGRATION_ERROR` | Cache kosong dan Jubelio gagal |

### Acceptance Criteria

- [ ] Memanggil `GET /region/districts?city_id=` Jubelio; di-cache 30 hari per `city_id`.
- [ ] `city_id` yang tidak dikenal menghasilkan `"data": []`.

---

## REG-04 · Daftar kelurahan/desa

### Endpoint

`GET /api/v1/regions/subdistricts?district_id={district_id}` · **Customer**

| Query | Tipe | Wajib | Keterangan |
| --- | --- | --- | --- |
| `district_id` | string | Ya | ID dari REG-03 |
| `district_name` | string | Tidak | Nama kecamatan terpilih. Bersama `city_name`, dipakai mencari kode pos kelurahan di RajaOngkir (Jubelio tidak menyediakannya); hanya petunjuk pencarian |
| `city_name` | string | Tidak | Nama kota/kabupaten terpilih |

### User Story

Sebagai customer, setelah memilih kecamatan saya ingin memilih kelurahan/desa, agar alamat saya lengkap sampai tingkat terkecil.

### Request Body

Tidak ada.

### Response Success

`200 OK`. Kode pos diisi bila Jubelio menyediakannya; bila tidak, `null` dan customer mengisi sendiri.

```json
{
  "data": [
    { "id": "3216052001", "name": "Satriajaya", "post_code": null },
    { "id": "3216052006", "name": "Srijaya", "post_code": null }
  ]
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 422 | `VALIDATION_ERROR` | `district_id` kosong |
| 502 | `INTEGRATION_ERROR` | Cache kosong dan Jubelio gagal |

### Acceptance Criteria

- [ ] Memanggil `GET /region/subdistricts?district_id=` Jubelio; di-cache 30 hari per `district_id`.
- [ ] Field `post_code` selalu ada (boleh `null`).

---

## ADDR-01 · Daftar alamat saya

### Endpoint

`GET /api/v1/addresses` · **Customer**

### User Story

Sebagai customer, saya ingin melihat semua alamat yang pernah saya simpan, agar bisa memilih salah satu saat checkout.

### Request Body

Tidak ada.

### Response Success

`200 OK` — daftar **Address**, alamat utama di urutan pertama, lalu terbaru. Maksimal 10 alamat, tanpa paginasi.

```json
{
  "data": [
    {
      "id": 7,
      "label": "Rumah",
      "recipient_name": "Budi Santoso",
      "phone": "081234567890",
      "address_line": "Jl. Melati No. 12, RT 03/RW 05",
      "province": { "id": "32", "name": "JAWA BARAT" },
      "city": { "id": "3216", "name": "KAB. BEKASI" },
      "district": { "id": "321605", "name": "TAMBUN UTARA" },
      "subdistrict": { "id": "3216052001", "name": "Satriajaya" },
      "post_code": "17565",
      "is_default": true,
      "created_at": "2026-10-14T21:00:00+07:00",
      "updated_at": "2026-10-14T21:00:00+07:00"
    }
  ]
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |

### Acceptance Criteria

- [ ] Hanya alamat milik user yang login.
- [ ] Alamat `is_default = true` selalu urutan pertama.
- [ ] User tanpa alamat mendapat `"data": []`.

---

## ADDR-02 · Tambah alamat

### Endpoint

`POST /api/v1/addresses` · **Customer**

### User Story

Sebagai customer, saya ingin menyimpan alamat pengiriman baru, agar bisa dipakai saat checkout sekarang dan berikutnya.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `label` | string | Tidak | Maks 50, mis. "Rumah", "Kantor" |
| `recipient_name` | string | Ya | 2–100 karakter |
| `phone` | string | Ya | Nomor Indonesia 10–15 digit |
| `address_line` | string | Ya | 10–255 karakter (jalan, nomor, RT/RW, patokan) |
| `province_id` | string | Ya | Dari REG-01 |
| `city_id` | string | Ya | Dari REG-02, harus di dalam provinsi |
| `district_id` | string | Ya | Dari REG-03, harus di dalam kota |
| `subdistrict_id` | string | Ya | Dari REG-04, harus di dalam kecamatan |
| `post_code` | string | Ya | 5 digit angka |
| `is_default` | boolean | Tidak | Default `false`; alamat pertama otomatis `true` |

Nama wilayah tidak dikirim frontend; backend mengisinya dari data region.

```json
{
  "label": "Rumah",
  "recipient_name": "Budi Santoso",
  "phone": "081234567890",
  "address_line": "Jl. Melati No. 12, RT 03/RW 05",
  "province_id": "32",
  "city_id": "3216",
  "district_id": "321605",
  "subdistrict_id": "3216052001",
  "post_code": "17565",
  "is_default": true
}
```

### Response Success

`201 Created` — objek **Address** baru.

```json
{
  "data": {
    "id": 7,
    "label": "Rumah",
    "recipient_name": "Budi Santoso",
    "phone": "081234567890",
    "address_line": "Jl. Melati No. 12, RT 03/RW 05",
    "province": { "id": "32", "name": "JAWA BARAT" },
    "city": { "id": "3216", "name": "KAB. BEKASI" },
    "district": { "id": "321605", "name": "TAMBUN UTARA" },
    "subdistrict": { "id": "3216052001", "name": "Satriajaya" },
    "post_code": "17565",
    "is_default": true,
    "created_at": "2026-10-14T21:00:00+07:00",
    "updated_at": "2026-10-14T21:00:00+07:00"
  },
  "message": "Alamat disimpan."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 422 | `REGION_NOT_FOUND` | ID wilayah tidak dikenal atau tidak berurutan (mis. kota bukan di provinsi yang dipilih) |
| 422 | `ADDRESS_LIMIT_REACHED` | Sudah punya 10 alamat |
| 422 | `VALIDATION_ERROR` | Field wajib kosong atau format salah |

```json
{
  "message": "Kecamatan yang dipilih tidak berada di kota tersebut.",
  "code": "REGION_NOT_FOUND",
  "errors": { "district_id": ["Kecamatan tidak sesuai dengan kota."] }
}
```

### Acceptance Criteria

- [ ] Nama provinsi/kota/kecamatan/kelurahan diisi backend dari data region, bukan dari input frontend.
- [ ] Rantai wilayah divalidasi: kota ∈ provinsi, kecamatan ∈ kota, kelurahan ∈ kecamatan.
- [ ] Alamat pertama user otomatis `is_default = true`.
- [ ] Bila `is_default = true`, alamat utama lain milik user menjadi `false` dalam transaksi yang sama.
- [ ] Alamat ke-11 ditolak `ADDRESS_LIMIT_REACHED`.

---

## ADDR-03 · Detail alamat

### Endpoint

`GET /api/v1/addresses/{id}` · **Customer**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `id` | path | integer | ID alamat |

### User Story

Sebagai customer, saya ingin membuka satu alamat untuk disunting, agar form terisi dengan data lama.

### Request Body

Tidak ada.

### Response Success

`200 OK` — objek **Address** (sama dengan contoh di ADDR-02).

```json
{
  "data": {
    "id": 7,
    "label": "Rumah",
    "recipient_name": "Budi Santoso",
    "phone": "081234567890",
    "address_line": "Jl. Melati No. 12, RT 03/RW 05",
    "province": { "id": "32", "name": "JAWA BARAT" },
    "city": { "id": "3216", "name": "KAB. BEKASI" },
    "district": { "id": "321605", "name": "TAMBUN UTARA" },
    "subdistrict": { "id": "3216052001", "name": "Satriajaya" },
    "post_code": "17565",
    "is_default": true,
    "created_at": "2026-10-14T21:00:00+07:00",
    "updated_at": "2026-10-14T21:00:00+07:00"
  }
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 404 | `ADDRESS_NOT_FOUND` | Alamat tidak ada atau milik user lain |

```json
{
  "message": "Alamat tidak ditemukan.",
  "code": "ADDRESS_NOT_FOUND"
}
```

### Acceptance Criteria

- [ ] Alamat milik user lain dibalas 404 (lewat `AddressPolicy`).

---

## ADDR-04 · Ubah alamat

### Endpoint

`PUT /api/v1/addresses/{id}` · **Customer**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `id` | path | integer | ID alamat |

### User Story

Sebagai customer, saya ingin memperbaiki alamat yang salah, agar paket dikirim ke tempat yang benar.

### Request Body

Sama dengan ADDR-02 (semua field dikirim ulang).

```json
{
  "label": "Rumah",
  "recipient_name": "Budi Santoso",
  "phone": "081234567890",
  "address_line": "Jl. Melati No. 14, RT 03/RW 05, dekat masjid",
  "province_id": "32",
  "city_id": "3216",
  "district_id": "321605",
  "subdistrict_id": "3216052001",
  "post_code": "17565",
  "is_default": true
}
```

### Response Success

`200 OK` — objek **Address** terbaru.

```json
{
  "data": {
    "id": 7,
    "label": "Rumah",
    "recipient_name": "Budi Santoso",
    "phone": "081234567890",
    "address_line": "Jl. Melati No. 14, RT 03/RW 05, dekat masjid",
    "province": { "id": "32", "name": "JAWA BARAT" },
    "city": { "id": "3216", "name": "KAB. BEKASI" },
    "district": { "id": "321605", "name": "TAMBUN UTARA" },
    "subdistrict": { "id": "3216052001", "name": "Satriajaya" },
    "post_code": "17565",
    "is_default": true,
    "created_at": "2026-10-14T21:00:00+07:00",
    "updated_at": "2026-10-14T21:20:00+07:00"
  },
  "message": "Alamat diperbarui."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 404 | `ADDRESS_NOT_FOUND` | Alamat tidak ada atau milik user lain |
| 422 | `REGION_NOT_FOUND` | Rantai wilayah tidak valid |
| 422 | `VALIDATION_ERROR` | Field tidak valid |

### Acceptance Criteria

- [ ] Validasi sama dengan ADDR-02.
- [ ] Mengubah alamat tidak mengubah alamat di pesanan yang sudah dibuat (pesanan menyimpan salinan).
- [ ] `is_default = false` pada satu-satunya alamat utama diabaikan (user tetap punya satu alamat utama).

---

## ADDR-05 · Hapus alamat

### Endpoint

`DELETE /api/v1/addresses/{id}` · **Customer**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `id` | path | integer | ID alamat |

### User Story

Sebagai customer, saya ingin menghapus alamat yang tidak dipakai lagi, agar daftar alamat saya rapi.

### Request Body

Tidak ada.

### Response Success

`200 OK`

```json
{
  "message": "Alamat dihapus."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 404 | `ADDRESS_NOT_FOUND` | Alamat tidak ada atau milik user lain |

### Acceptance Criteria

- [ ] Bila yang dihapus adalah alamat utama, alamat terbaru yang tersisa otomatis menjadi utama.
- [ ] Pesanan lama yang memakai alamat ini tidak terpengaruh.

---

## ADDR-06 · Jadikan alamat utama

### Endpoint

`PATCH /api/v1/addresses/{id}/default` · **Customer**

| Parameter | Lokasi | Tipe | Keterangan |
| --- | --- | --- | --- |
| `id` | path | integer | ID alamat |

### User Story

Sebagai customer, saya ingin menetapkan satu alamat sebagai alamat utama, agar otomatis terpilih saat checkout.

### Request Body

Tidak ada.

### Response Success

`200 OK` — daftar **Address** terbaru (bentuk sama dengan ADDR-01).

```json
{
  "data": [
    {
      "id": 8,
      "label": "Kantor",
      "recipient_name": "Budi Santoso",
      "phone": "081234567890",
      "address_line": "Gedung Graha Lt. 3, Jl. Sudirman No. 1",
      "province": { "id": "31", "name": "DKI JAKARTA" },
      "city": { "id": "3171", "name": "KOTA ADM. JAKARTA SELATAN" },
      "district": { "id": "317101", "name": "SETIABUDI" },
      "subdistrict": { "id": "3171011001", "name": "Karet" },
      "post_code": "12920",
      "is_default": true,
      "created_at": "2026-10-14T21:10:00+07:00",
      "updated_at": "2026-10-14T21:25:00+07:00"
    }
  ],
  "message": "Alamat utama diperbarui."
}
```

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 404 | `ADDRESS_NOT_FOUND` | Alamat tidak ada atau milik user lain |

### Acceptance Criteria

- [ ] Tepat satu alamat milik user yang `is_default = true` setelah request.
- [ ] Perubahan dilakukan dalam satu transaksi database.
