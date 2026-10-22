# Ongkos Kirim

Tarif dihitung lewat RajaOngkir (INT-RO-01, INT-RO-02) dari gudang Tambun Utara ke alamat customer, berdasarkan total berat varian di keranjang. Bentuk response di bawah adalah kontrak website sendiri: backend memetakan jawaban Jubelio Shipment ke bentuk ini, sehingga frontend tidak bergantung pada format Jubelio Shipment. Konvensi umum ada di `README.md`.

Gudang asal (dari konfigurasi `config/shipping.php`):

> Jl. Raya Pisangan No.1, RT.02/RW.02, Satriajaya, Kec. Tambun Utara, Kabupaten Bekasi, Jawa Barat 17566

---

## SHIP-01 · Hitung pilihan kurir dan ongkir

### Endpoint

`POST /api/v1/shipping/rates` · **Customer** · rate limit 20/menit per user

### User Story

Sebagai customer di halaman checkout, saya ingin melihat pilihan kurir beserta ongkir dan perkiraan waktu sampai untuk alamat yang saya pilih, agar bisa memilih pengiriman yang paling cocok untuk sepeda atau baterai saya.

### Request Body

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `address_id` | integer | Ya | Alamat milik user |
| `item_ids` | integer[] | Tidak | ID baris keranjang yang dibeli (yang dicentang customer). Tanpa field ini seluruh keranjang dihitung. Harus sama dengan `item_ids` saat ORD-01, kalau tidak `rate_id` dianggap `RATE_EXPIRED` |
| `service_types` | array of string | Tidak | Saring jenis layanan: `instant`, `same_day`, `regular`, `cargo`. Default semua |

Isi paket diambil backend dari keranjang user (tidak dikirim frontend).

```json
{
  "address_id": 7,
  "service_types": ["regular", "cargo"]
}
```

### Response Success

`200 OK`. `rates` urut dari ongkir termurah. Setiap `rate_id` berlaku 30 menit (`expires_at`) dan wajib dikirim ulang di checkout (ORD-01).

```json
{
  "data": {
    "origin": {
      "name": "Gudang Tambun Utara",
      "district": "TAMBUN UTARA",
      "city": "KAB. BEKASI",
      "post_code": "17566"
    },
    "destination": {
      "address_id": 7,
      "district": "TAMBUN UTARA",
      "city": "KAB. BEKASI",
      "province": "JAWA BARAT",
      "post_code": "17565"
    },
    "package": {
      "total_weight": 32000,
      "item_count": 1,
      "subtotal": 6750000
    },
    "rates": [
      {
        "rate_id": "rt_9f2c1a7e",
        "courier_code": "jne",
        "courier_name": "JNE",
        "service_code": "JTR",
        "service_name": "JNE Trucking",
        "service_type": "cargo",
        "cost": 185000,
        "insurance_cost": 0,
        "etd_min_days": 3,
        "etd_max_days": 5,
        "etd_text": "3–5 hari"
      },
      {
        "rate_id": "rt_1b77d0c2",
        "courier_code": "sap",
        "courier_name": "SAP Express",
        "service_code": "CARGO",
        "service_name": "SAP Cargo",
        "service_type": "cargo",
        "cost": 210000,
        "insurance_cost": 0,
        "etd_min_days": 2,
        "etd_max_days": 4,
        "etd_text": "2–4 hari"
      }
    ],
    "expires_at": "2026-10-14T22:00:00+07:00"
  }
}
```

Angka di contoh adalah ilustrasi; nilai nyata berasal dari RajaOngkir.

### Response Failed

| HTTP | `code` | Kapan |
| --- | --- | --- |
| 401 | `UNAUTHENTICATED` | Belum login |
| 404 | `ADDRESS_NOT_FOUND` | Alamat tidak ada atau milik user lain |
| 422 | `CART_EMPTY` | Keranjang kosong |
| 422 | `NO_SHIPPING_SERVICE` | Tidak ada kurir yang melayani rute/berat ini |
| 422 | `VALIDATION_ERROR` | `address_id` kosong, `service_types` berisi nilai tak dikenal |
| 429 | `TOO_MANY_REQUESTS` | Lebih dari 20 request/menit |
| 502 | `INTEGRATION_ERROR` | RajaOngkir gagal/timeout (> 10 detik) atau API key salah |

```json
{
  "message": "Belum ada kurir yang bisa mengirim ke alamat ini untuk berat 32 kg. Silakan hubungi toko.",
  "code": "NO_SHIPPING_SERVICE"
}
```

```json
{
  "message": "Ongkir sedang tidak bisa dihitung. Coba lagi beberapa saat.",
  "code": "INTEGRATION_ERROR"
}
```

### Acceptance Criteria

- [ ] Berat paket = jumlah `qty × package_weight` varian di keranjang (gram), dikirim ke RajaOngkir dalam gram.
- [ ] Item keranjang ber-`issue = PRODUCT_INACTIVE` tidak ikut dihitung.
- [ ] Asal pengiriman selalu gudang Tambun Utara dari konfigurasi, bukan dari input frontend.
- [ ] Setiap rate disimpan di cache server dengan kunci `rate_id` selama 30 menit, berisi user, alamat, berat, kurir, layanan, dan ongkir, agar checkout tidak bisa memalsukan ongkir.
- [ ] Response dengan alamat + berat yang sama dalam 30 menit diambil dari cache (tidak memanggil RajaOngkir lagi), karena paket gratis RajaOngkir dibatasi sekitar 100 pengecekan per hari.
- [ ] Timeout ke RajaOngkir 10 detik; gagal → `502 INTEGRATION_ERROR`, tanpa pesan teknis ke customer.
- [ ] `SHIPPING_DRIVER=stub` mengembalikan tarif contoh dengan bentuk response yang sama, agar frontend dan test tetap bisa bekerja tanpa API key.
