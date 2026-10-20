@extends('layouts.admin')

@section('title', 'Sinkron')

{{-- ADM-13 start a sync, ADM-14 history. Behaviour: resources/js/components/adminSync.js --}}
@section('content')
    <div x-data="adminSync">
        <h1 class="text-2xl font-bold">Sinkron Jubelio</h1>
        <p class="mt-1 text-sm text-gray-500">Jalankan sinkron sekarang tanpa menunggu jadwal. Satu sinkron per jenis berjalan pada satu waktu.</p>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <template x-for="kind in kinds" :key="kind.type">
                <div class="flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-4">
                    <div>
                        <h2 class="font-semibold" x-text="kind.label"></h2>
                        <p class="mt-1 text-xs text-gray-500" x-text="kind.hint"></p>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" @click="start(kind.type)" :disabled="starting !== null"
                                class="rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white disabled:opacity-40"
                                x-text="starting === kind.type ? 'Memulai…' : 'Jalankan'"></button>
                        <button type="button" x-show="kind.type === 'products'" @click="start('products', true)" :disabled="starting !== null"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold disabled:opacity-40">Sinkron semua produk</button>
                    </div>
                </div>
            </template>
        </div>

        <p x-cloak x-show="notice" class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-700" x-text="notice"></p>
        <p x-cloak x-show="actionError" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700" x-text="actionError"></p>

        <div class="mt-8 flex flex-wrap items-end justify-between gap-3">
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

        <div class="mt-3 overflow-x-auto rounded-xl border border-gray-200 bg-white">
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

        <div x-cloak x-show="meta.last_page > 1" class="mt-4 flex items-center justify-between text-sm">
            <button type="button" :disabled="meta.current_page <= 1" @click="fetchPage(meta.current_page - 1)" class="rounded-lg border border-gray-300 bg-white px-4 py-2 font-semibold disabled:opacity-40">← Sebelumnya</button>
            <span class="text-gray-500">Halaman <span x-text="meta.current_page"></span> dari <span x-text="meta.last_page"></span></span>
            <button type="button" :disabled="meta.current_page >= meta.last_page" @click="fetchPage(meta.current_page + 1)" class="rounded-lg border border-gray-300 bg-white px-4 py-2 font-semibold disabled:opacity-40">Berikutnya →</button>
        </div>
    </div>
@endsection
