@extends('layouts.admin')

@section('title', 'Produk')

{{-- ADM-20 list with variants, ADM-13 start a sync (the cards). Behaviour: resources/js/components/adminProducts.js --}}
@section('content')
    <div x-data="adminProducts">
        <h1 class="text-2xl font-bold">Produk</h1>
        <p class="mt-1 text-sm text-gray-500">Produk dan variannya, disalin dari Jubelio. Hanya untuk dilihat; ubah produk, harga, dan stok di Jubelio.</p>

        {{-- Sync from Jubelio --}}
        <h2 class="mt-6 text-sm font-bold uppercase tracking-wide text-gray-500">Sinkronisasi dengan Jubelio</h2>
        <p class="mt-1 text-sm text-gray-500">Jalankan sinkron sekarang tanpa menunggu jadwal. Satu sinkron per jenis berjalan pada satu waktu. Hasilnya bisa dilihat di <a href="{{ route('admin.sync.index') }}" class="font-semibold underline">Riwayat Sinkronisasi</a>.</p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <template x-for="kind in kinds" :key="kind.type">
                <div class="flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-4">
                    <div>
                        <h3 class="font-semibold" x-text="kind.label"></h3>
                        <p class="mt-1 text-xs text-gray-500" x-text="kind.hint"></p>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" @click="start(kind.type)" :disabled="starting !== null"
                                class="rounded-lg bg-gold px-3 py-2 text-xs font-semibold text-ink hover:bg-gold-hover disabled:opacity-40"
                                x-text="starting === kind.type ? 'Memulai…' : 'Jalankan'"></button>
                    </div>
                </div>
            </template>
        </div>

        <p x-cloak x-show="notice" class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-700" x-text="notice"></p>
        <p x-cloak x-show="actionError" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700" x-text="actionError"></p>

        {{-- Product list --}}
        <div class="mt-8 flex flex-wrap items-end justify-between gap-2">
            <h2 class="text-lg font-bold">Daftar produk</h2>
            <p class="text-sm text-gray-500"><span x-text="meta.total"></span> produk</p>
        </div>

        <form class="mt-3 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="fetchPage(1)">
            <label class="text-xs font-semibold text-gray-500 lg:col-span-2">Cari
                <input type="search" x-model="filters.q" @input="search()" placeholder="Nama produk, merek, SKU, atau varian"
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
            </label>
            <label class="text-xs font-semibold text-gray-500">Status
                <select x-model="filters.status" @change="fetchPage(1)" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                    <option value="">Semua</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </label>
            <div class="flex items-end">
                <button type="button" @click="reset()" class="pb-2 text-sm font-semibold text-gray-500 underline hover:text-gray-900">Reset filter</button>
            </div>
        </form>

        <div x-cloak x-show="error" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            <span x-text="error"></span> <button type="button" class="ml-2 font-semibold underline" @click="fetchPage(1)">Coba lagi</button>
        </div>

        @php($cols = 'grid grid-cols-[minmax(0,2fr)_1fr_1.2fr_5rem_6rem_5rem] items-center')
        <div class="mt-4 hidden overflow-x-auto rounded-xl border border-gray-200 bg-white xl:block">
            <div class="min-w-[52rem]">
                <div class="{{ $cols }} border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <span class="px-4 py-3">Produk</span><span class="px-4 py-3">Kategori</span><span class="px-4 py-3 text-right">Harga</span>
                    <span class="px-4 py-3 text-right">Stok</span><span class="px-4 py-3">Status</span><span class="px-4 py-3 text-right">Varian</span>
                </div>

                <p x-show="loading" class="px-4 py-8 text-center text-sm text-gray-500">Memuat…</p>
                <p x-cloak x-show="!loading && items.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada produk yang cocok.</p>

                <template x-for="product in items" :key="product.id">
                    <div class="border-b border-gray-100 text-sm last:border-b-0">
                        <button type="button" class="{{ $cols }} w-full text-left hover:bg-gray-50" @click="toggle(product.id)" :aria-expanded="!!expanded[product.id]">
                            <span class="flex min-w-0 items-center gap-3 px-4 py-3">
                                <span class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                                    <img x-show="product.thumbnail_url" :src="product.thumbnail_url" alt="" class="h-full w-full object-cover">
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate font-medium" x-text="product.name"></span>
                                    <span class="block truncate text-xs text-gray-500" x-text="product.brand_name ?? ''"></span>
                                </span>
                            </span>
                            <span class="px-4 py-3 text-gray-600" x-text="product.category ?? '-'"></span>
                            <span class="px-4 py-3 text-right tabular-nums">
                                <span x-show="product.price_min !== null" x-text="product.price_min === product.price_max ? $format.formatRupiah(product.price_min) : $format.formatRupiah(product.price_min) + ' – ' + $format.formatRupiah(product.price_max)"></span>
                                <span x-show="product.price_min === null" class="text-gray-400">-</span>
                            </span>
                            <span class="px-4 py-3 text-right tabular-nums" x-text="stockLabel(product.total_available_qty)"></span>
                            <span class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="product.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'" x-text="product.is_active ? 'Aktif' : 'Nonaktif'"></span></span>
                            <span class="px-4 py-3 text-right text-xs font-semibold text-gray-600"><span x-text="product.variants.length"></span> <span x-text="expanded[product.id] ? '▲' : '▼'"></span></span>
                        </button>

                        <div x-cloak x-show="expanded[product.id]" class="border-t border-gray-100 bg-gray-50 px-4 py-3">
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[40rem] text-left text-xs">
                                    <thead class="text-gray-500">
                                        <tr><th class="py-1.5 pr-3 font-semibold">SKU</th><th class="py-1.5 pr-3 font-semibold">Varian</th><th class="py-1.5 pr-3 text-right font-semibold">Harga</th><th class="py-1.5 pr-3 text-right font-semibold">Stok</th><th class="py-1.5 pr-3 text-right font-semibold">Berat</th><th class="py-1.5 font-semibold">Status</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <template x-for="variant in product.variants" :key="variant.id">
                                            <tr :class="variant.is_active ? '' : 'text-gray-400'">
                                                <td class="py-1.5 pr-3 font-mono" x-text="variant.sku"></td>
                                                <td class="py-1.5 pr-3" x-text="variant.name"></td>
                                                <td class="py-1.5 pr-3 text-right tabular-nums" x-text="$format.formatRupiah(variant.price)"></td>
                                                <td class="py-1.5 pr-3 text-right tabular-nums" x-text="variant.available_qty > 0 ? variant.available_qty : 'Habis'"></td>
                                                <td class="py-1.5 pr-3 text-right tabular-nums" x-text="variant.package_weight ? $format.formatWeight(variant.package_weight) : '-'"></td>
                                                <td class="py-1.5" x-text="variant.is_active ? 'Aktif' : 'Nonaktif'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="mt-4 space-y-3 xl:hidden">
            <p x-show="loading" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Memuat…</p>
            <p x-cloak x-show="!loading && items.length === 0" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Tidak ada produk yang cocok.</p>
            <template x-for="product in items" :key="product.id">
                <div class="rounded-xl border border-gray-200 bg-white">
                    <button type="button" class="flex w-full items-start gap-3 p-4 text-left" @click="toggle(product.id)" :aria-expanded="!!expanded[product.id]">
                        <span class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                            <img x-show="product.thumbnail_url" :src="product.thumbnail_url" alt="" class="h-full w-full object-cover">
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium" x-text="product.name"></span>
                            <span class="block truncate text-xs text-gray-500"><span x-text="product.brand_name ?? ''"></span><span x-show="product.brand_name && product.category"> · </span><span x-text="product.category ?? ''"></span></span>
                            <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                <span class="font-semibold tabular-nums text-gray-900">
                                    <span x-show="product.price_min !== null" x-text="product.price_min === product.price_max ? $format.formatRupiah(product.price_min) : $format.formatRupiah(product.price_min) + ' – ' + $format.formatRupiah(product.price_max)"></span>
                                    <span x-show="product.price_min === null" class="text-gray-400">-</span>
                                </span>
                                <span class="text-gray-500">Stok <span x-text="stockLabel(product.total_available_qty)"></span></span>
                                <span class="rounded-full px-2 py-0.5 font-semibold" :class="product.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'" x-text="product.is_active ? 'Aktif' : 'Nonaktif'"></span>
                            </span>
                        </span>
                        <span class="shrink-0 self-center text-xs font-semibold text-gray-600"><span x-text="product.variants.length"></span> <span x-text="expanded[product.id] ? '▲' : '▼'"></span></span>
                    </button>

                    <ul x-cloak x-show="expanded[product.id]" class="divide-y divide-gray-200 border-t border-gray-100 bg-gray-50 px-4 text-xs">
                        <template x-for="variant in product.variants" :key="variant.id">
                            <li class="py-2.5" :class="variant.is_active ? '' : 'text-gray-400'">
                                <span class="flex items-start justify-between gap-3">
                                    <span class="min-w-0"><span class="block font-medium" x-text="variant.name"></span><span class="block break-all font-mono" x-text="variant.sku"></span></span>
                                    <span class="shrink-0 text-right"><span class="block font-semibold tabular-nums" x-text="$format.formatRupiah(variant.price)"></span><span class="block" x-text="'Stok ' + (variant.available_qty > 0 ? variant.available_qty : 'habis')"></span></span>
                                </span>
                                <span class="mt-1 block" x-text="(variant.package_weight ? $format.formatWeight(variant.package_weight) : '-') + ' · ' + (variant.is_active ? 'Aktif' : 'Nonaktif')"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>
        </div>

        <x-admin-pager />
    </div>
@endsection
