@extends('layouts.admin')

@section('title', 'Manajemen Resi')

{{-- ADM-21 list, ADM-22 take the resi from Jubelio. Behaviour: resources/js/components/adminShipments.js --}}
@section('content')
    <div x-data="adminShipments">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Manajemen Resi</h1>
                <p class="mt-1 text-sm text-gray-500">Pesanan yang sudah dibayar beserta nomor resinya. Resi diambil dari Jubelio, bukan diketik di sini.</p>
            </div>
            <button type="button" @click="refreshFromJubelio()" :disabled="refreshing"
                    class="rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-ink hover:bg-gold-hover disabled:opacity-40" x-text="refreshing ? 'Mengambil dari Jubelio…' : 'Ambil resi dari Jubelio'"></button>
        </div>

        <p x-cloak x-show="notice" class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-700" x-text="notice"></p>
        <p x-cloak x-show="actionError" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700" x-text="actionError"></p>

        <form class="mt-4 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="fetchPage(1)">
            <label class="text-xs font-semibold text-gray-500 lg:col-span-2">Cari
                <input type="search" x-model="filters.q" @input="search()" placeholder="No. pesanan, resi, penerima, pelanggan, HP"
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
            </label>
            <label class="text-xs font-semibold text-gray-500">Resi
                <select x-model="filters.resi" @change="fetchPage(1)" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                    <option value="">Semua</option>
                    <option value="ada">Sudah ada resi</option>
                    <option value="belum">Belum ada resi</option>
                </select>
            </label>
            <div class="flex items-end">
                <button type="button" @click="reset()" class="pb-2 text-sm font-semibold text-gray-500 underline hover:text-gray-900">Reset filter</button>
            </div>
        </form>

        <div x-cloak x-show="error" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            <span x-text="error"></span> <button type="button" class="ml-2 font-semibold underline" @click="fetchPage(1)">Coba lagi</button>
        </div>

        <div class="mt-4 hidden overflow-x-auto rounded-xl border border-gray-200 bg-white xl:block">
            <table class="w-full min-w-[56rem] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Pesanan</th><th class="px-4 py-3">Penerima</th><th class="px-4 py-3">Kurir pilihan</th>
                        <th class="px-4 py-3">Status</th><th class="px-4 py-3">Sales order</th><th class="px-4 py-3">Resi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr x-show="loading"><td colspan="6" class="px-4 py-8 text-center text-gray-500">Memuat…</td></tr>
                    <tr x-cloak x-show="!loading && items.length === 0"><td colspan="6" class="px-4 py-8 text-center text-gray-500">Tidak ada pesanan yang cocok.</td></tr>
                    <template x-for="order in items" :key="order.order_number">
                        <tr>
                            <td class="px-4 py-3">
                                <a :href="`{{ route('admin.orders.index') }}?order=${order.order_number}`" class="block font-mono font-semibold underline decoration-gray-300 hover:decoration-gray-900" x-text="order.order_number"></a>
                                <span class="block text-xs text-gray-500" x-text="date(order.paid_at ?? order.created_at)"></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="block font-medium" x-text="order.recipient_name"></span>
                                <span class="block text-xs text-gray-500" x-text="order.destination"></span>
                            </td>
                            <td class="px-4 py-3 text-gray-600"><span x-text="order.courier"></span> <span class="block text-xs text-gray-500" x-text="order.service"></span></td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="badge(order.status)" x-text="order.status_label"></span></td>
                            <td class="px-4 py-3 text-xs text-gray-600" x-text="order.jubelio_salesorder_no ?? '-'"></td>
                            <td class="px-4 py-3">
                                <template x-if="order.tracking_no">
                                    <span><span class="block font-mono font-semibold" x-text="order.tracking_no"></span><span class="block text-xs text-gray-500" x-text="order.jubelio_courier"></span></span>
                                </template>
                                <span x-show="! order.tracking_no" class="text-xs text-gray-400">Belum ada resi</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="mt-4 space-y-3 xl:hidden">
            <p x-show="loading" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Memuat…</p>
            <p x-cloak x-show="!loading && items.length === 0" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Tidak ada pesanan yang cocok.</p>
            <template x-for="order in items" :key="order.order_number">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <a :href="`{{ route('admin.orders.index') }}?order=${order.order_number}`" class="block font-mono font-semibold underline decoration-gray-300" x-text="order.order_number"></a>
                            <span class="block text-xs text-gray-500" x-text="date(order.paid_at ?? order.created_at)"></span>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="badge(order.status)" x-text="order.status_label"></span>
                    </div>
                    <p class="mt-3 text-sm font-medium" x-text="order.recipient_name"></p>
                    <p class="text-xs text-gray-500" x-text="order.destination"></p>
                    <p class="mt-2 text-xs text-gray-600"><span x-text="order.courier"></span> <span x-text="order.service"></span> · <span x-text="order.jubelio_salesorder_no ?? 'belum ada sales order'"></span></p>
                    <div class="mt-3 rounded-lg bg-gray-50 px-3 py-2">
                        <p class="text-xs text-gray-500">Resi</p>
                        <template x-if="order.tracking_no">
                            <p class="break-all font-mono text-sm font-semibold"><span x-text="order.tracking_no"></span> <span class="font-sans text-xs font-normal text-gray-500" x-text="order.jubelio_courier"></span></p>
                        </template>
                        <p x-show="! order.tracking_no" class="text-sm text-gray-400">Belum ada resi</p>
                    </div>
                </div>
            </template>
        </div>

        <x-admin-pager />

        <section class="mt-8 rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-600">
            <h2 class="text-base font-bold text-gray-900">Tentang halaman ini</h2>
            <p class="mt-2">
                Tombol <span class="font-semibold text-gray-900">Ambil resi dari Jubelio</span> membaca nomor resi dari sales order tiap pesanan di Jubelio dan menyimpannya di sini.
                Kalau resi belum diisi di Jubelio, kolom Resi tetap <span class="font-semibold text-gray-900">Belum ada resi</span>.
                Setiap klik memeriksa paling banyak 30 pesanan; tekan lagi bila masih ada yang tersisa.
            </p>
        </section>
    </div>
@endsection
