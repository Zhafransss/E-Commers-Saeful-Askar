@extends('layouts.admin')

@section('title', 'Riwayat Sinkronisasi')

{{-- ADM-14 history (starting a sync is on the Produk page). Behaviour: resources/js/components/adminSync.js --}}
@section('content')
    <div x-data="adminSync">
        <h1 class="text-2xl font-bold">Riwayat Sinkronisasi</h1>
        <p class="mt-1 text-sm text-gray-500">
            Catatan setiap sinkronisasi dengan Jubelio, yang terbaru di atas. Untuk menjalankan sinkron sekarang, buka menu
            <a href="{{ route('admin.products.index') }}" class="font-semibold underline">Produk</a>.
        </p>

        <div class="mt-6 flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-lg font-bold">Riwayat <span x-show="running" class="ml-2 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700">ada yang berjalan…</span></h2>
            <div class="flex gap-2">
                <select x-model="filters.type" @change="fetchPage(1)" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option value="">Semua jenis</option>
                    <option value="categories">Kategori</option><option value="products">Produk</option><option value="stock">Stok</option>
                    <option value="order_push">Kirim pesanan</option><option value="order_paid">Tandai lunas</option><option value="order_cancel">Batalkan pesanan</option>
                </select>
                <select x-model="filters.status" @change="fetchPage(1)" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                    <option value="">Semua status</option>
                    <option value="running">Berjalan</option><option value="success">Berhasil</option><option value="failed">Gagal</option>
                </select>
            </div>
        </div>

        <div x-cloak x-show="error" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            <span x-text="error"></span> <button type="button" class="ml-2 font-semibold underline" @click="fetchPage(1)">Coba lagi</button>
        </div>

        <div class="mt-3 hidden overflow-x-auto rounded-xl border border-gray-200 bg-white xl:block">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr><th class="px-4 py-3">Jenis</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Mulai</th><th class="px-4 py-3">Durasi</th><th class="px-4 py-3 text-right">Item</th><th class="px-4 py-3">Pesan</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr x-show="loading"><td colspan="6" class="px-4 py-8 text-center text-gray-500">Memuat…</td></tr>
                    <tr x-cloak x-show="!loading && logs.length === 0"><td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada riwayat.</td></tr>
                    <template x-for="log in logs" :key="log.id">
                        <tr>
                            <td class="px-4 py-3 font-medium"><span x-text="label(log.type)"></span><span x-show="log.reference_id" class="ml-1 text-xs text-gray-400" x-text="'#' + log.reference_id"></span></td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="badge(log.status)" x-text="log.status"></span></td>
                            <td class="px-4 py-3 text-gray-600" x-text="date(log.started_at)"></td>
                            <td class="px-4 py-3 text-gray-600" x-text="duration(log.duration_seconds)"></td>
                            <td class="px-4 py-3 text-right tabular-nums" x-text="log.items_processed"></td>
                            <td class="max-w-xs break-words px-4 py-3 text-xs text-red-600" x-text="log.message"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="mt-3 space-y-3 xl:hidden">
            <p x-show="loading" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Memuat…</p>
            <p x-cloak x-show="!loading && logs.length === 0" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Belum ada riwayat.</p>
            <template x-for="log in logs" :key="log.id">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <span class="font-medium"><span x-text="label(log.type)"></span><span x-show="log.reference_id" class="ml-1 text-xs text-gray-400" x-text="'#' + log.reference_id"></span></span>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="badge(log.status)" x-text="log.status"></span>
                    </div>
                    <p class="mt-2 text-xs text-gray-500"><span x-text="date(log.started_at)"></span> · <span x-text="duration(log.duration_seconds)"></span> · <span x-text="log.items_processed"></span> item</p>
                    <p x-show="log.message" class="mt-2 break-words text-xs text-red-600" x-text="log.message"></p>
                </div>
            </template>
        </div>

        <div x-cloak x-show="meta.last_page > 1" class="mt-4 flex items-center justify-between gap-2 text-sm">
            <button type="button" :disabled="meta.current_page <= 1" @click="fetchPage(meta.current_page - 1)" class="shrink-0 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-3 py-2 font-semibold disabled:opacity-40 sm:px-4"><span aria-hidden="true">←</span><span class="sr-only min-[400px]:not-sr-only"> Sebelumnya</span></button>
            <span class="min-w-0 text-center text-gray-500"><span class="hidden min-[400px]:inline">Halaman </span><span x-text="meta.current_page"></span><span class="min-[400px]:hidden"> / </span><span class="hidden min-[400px]:inline"> dari </span><span x-text="meta.last_page"></span></span>
            <button type="button" :disabled="meta.current_page >= meta.last_page" @click="fetchPage(meta.current_page + 1)" class="shrink-0 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-3 py-2 font-semibold disabled:opacity-40 sm:px-4"><span class="sr-only min-[400px]:not-sr-only">Berikutnya </span><span aria-hidden="true">→</span></button>
        </div>

        {{-- What the history is, for whoever reads this page --}}
        <section class="mt-8 rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-600">
            <h2 class="text-base font-bold text-gray-900">Tentang Riwayat</h2>
            <p class="mt-2">
                Riwayat mencatat setiap kali website berkomunikasi dengan Jubelio: apa yang diproses, kapan, berhasil atau gagal, dan berapa lama.
                Gunanya untuk memeriksa apakah integrasi berjalan sehat dan menemukan penyebab kalau ada yang tertahan. Ini <span class="font-semibold text-gray-900">bukan</span> riwayat pesanan pelanggan.
                Catatan yang lebih lama dari 90 hari dihapus otomatis. Sinkron manual dijalankan dari menu Produk.
            </p>

            <h3 class="mt-5 font-semibold text-gray-900">Arti tiap kolom</h3>
            <dl class="mt-2 grid gap-x-6 gap-y-2 sm:grid-cols-[8rem_1fr]">
                <dt class="font-medium text-gray-900">Jenis</dt>
                <dd>Apa yang disinkronkan. Angka seperti <span class="font-mono">#12</span> di sampingnya adalah ID pesanan yang bersangkutan.</dd>
                <dt class="font-medium text-gray-900">Status</dt>
                <dd><span class="font-semibold">running</span> sedang berjalan, <span class="font-semibold">success</span> berhasil, <span class="font-semibold">failed</span> gagal.</dd>
                <dt class="font-medium text-gray-900">Mulai</dt>
                <dd>Waktu sinkronisasi dimulai.</dd>
                <dt class="font-medium text-gray-900">Durasi</dt>
                <dd>Lama proses berlangsung. Angka yang tiba-tiba sangat besar biasanya tanda Jubelio lambat.</dd>
                <dt class="font-medium text-gray-900">Item</dt>
                <dd>Jumlah data yang diproses, misalnya jumlah produk atau varian yang diperbarui.</dd>
                <dt class="font-medium text-gray-900">Pesan</dt>
                <dd>Hanya terisi kalau gagal: alasan error dari Jubelio. Kolom kosong berarti tidak ada masalah.</dd>
            </dl>

            <h3 class="mt-5 font-semibold text-gray-900">Arti tiap jenis</h3>
            <dl class="mt-2 grid gap-x-6 gap-y-2 sm:grid-cols-[8rem_1fr]">
                <dt class="font-medium text-gray-900">Kategori</dt>
                <dd>Menyalin kategori produk dari Jubelio ke website.</dd>
                <dt class="font-medium text-gray-900">Produk</dt>
                <dd>Menyalin produk, harga, dan varian dari Jubelio ke website.</dd>
                <dt class="font-medium text-gray-900">Stok</dt>
                <dd>Menyamakan stok di website dengan stok di Jubelio.</dd>
                <dt class="font-medium text-gray-900">Kirim pesanan</dt>
                <dd>Website membuat pesanan di Jubelio saat pelanggan checkout, sehingga stok barangnya langsung terkunci.</dd>
                <dt class="font-medium text-gray-900">Tandai lunas</dt>
                <dd>Website memberi tahu Jubelio bahwa pesanan sudah dibayar, supaya gudang memprosesnya.</dd>
                <dt class="font-medium text-gray-900">Batalkan pesanan</dt>
                <dd>Website membatalkan pesanan di Jubelio saat pesanan kedaluwarsa atau dibatalkan, sehingga stoknya kembali.</dd>
            </dl>

            <p class="mt-5">
                Kalau ada baris <span class="font-semibold">failed</span> pada jenis pesanan (Kirim pesanan, Tandai lunas, atau Batalkan pesanan), pesanan itu belum sampai ke gudang dan perlu dikirim ulang dari halaman Pesanan.
                Kalau tidak ada riwayat baru dalam waktu lama, periksa bahwa cron di hosting masih aktif.
            </p>
        </section>
    </div>
@endsection
