@extends('layouts.admin')

@section('title', 'Manajemen User')

{{-- ADM-18 list, ADM-19 detail panel. Read-only: customers are shown, never edited. Behaviour: resources/js/components/adminUsers.js --}}
@section('content')
    <div x-data="adminUsers" @keydown.escape.window="close()">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h1 class="text-2xl font-bold">Manajemen User</h1>
                <p class="mt-1 text-sm text-gray-500">Data pelanggan yang terdaftar di website. Hanya untuk dilihat, tidak bisa diubah.</p>
            </div>
            <p class="text-sm text-gray-500"><span x-text="meta.total"></span> pelanggan</p>
        </div>

        <form class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4" @submit.prevent="fetchPage(1)">
            <label class="min-w-64 flex-1 text-xs font-semibold text-gray-500">Cari
                <input type="search" x-model="filters.q" @input="search()" placeholder="Nama, email, atau HP"
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
            </label>
            <button type="button" @click="reset()" class="pb-2 text-sm font-semibold text-gray-500 underline hover:text-gray-900">Reset</button>
        </form>

        <div x-cloak x-show="error" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            <span x-text="error"></span> <button type="button" class="ml-2 font-semibold underline" @click="fetchPage(1)">Coba lagi</button>
        </div>

        <div class="mt-4 hidden overflow-x-auto rounded-xl border border-gray-200 bg-white xl:block">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Pelanggan</th><th class="px-4 py-3">HP</th><th class="px-4 py-3 text-right">Pesanan</th>
                        <th class="px-4 py-3 text-right">Total belanja</th><th class="px-4 py-3">Terdaftar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr x-show="loading"><td colspan="5" class="px-4 py-8 text-center text-gray-500">Memuat…</td></tr>
                    <tr x-cloak x-show="!loading && items.length === 0"><td colspan="5" class="px-4 py-8 text-center text-gray-500">Tidak ada pelanggan yang cocok.</td></tr>
                    <template x-for="user in items" :key="user.id">
                        <tr class="cursor-pointer hover:bg-gray-50" :class="detail?.id === user.id && 'bg-yellow-50'" @click="open(user.id)">
                            <td class="px-4 py-3">
                                <span class="block font-medium" x-text="user.name"></span>
                                <span class="block text-xs text-gray-500" x-text="user.email"></span>
                            </td>
                            <td class="px-4 py-3 text-gray-600" x-text="user.phone ?? '-'"></td>
                            <td class="px-4 py-3 text-right tabular-nums"><span x-text="user.orders_count"></span> <span class="text-xs text-gray-400" x-show="user.orders_count > 0">(<span x-text="user.paid_orders_count"></span> dibayar)</span></td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums" x-text="$format.formatRupiah(user.paid_total)"></td>
                            <td class="px-4 py-3 text-gray-600" x-text="day(user.registered_at)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="mt-4 space-y-3 xl:hidden">
            <p x-show="loading" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Memuat…</p>
            <p x-cloak x-show="!loading && items.length === 0" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Tidak ada pelanggan yang cocok.</p>
            <template x-for="user in items" :key="user.id">
                <button type="button" @click="open(user.id)" class="block w-full rounded-xl border border-gray-200 bg-white p-4 text-left" :class="detail?.id === user.id && 'bg-yellow-50'">
                    <span class="block font-medium" x-text="user.name"></span>
                    <span class="block break-all text-xs text-gray-500" x-text="user.email"></span>
                    <span class="mt-1 block text-sm text-gray-600" x-text="user.phone ?? '-'"></span>
                    <span class="mt-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs text-gray-500">
                        <span><span x-text="user.orders_count"></span> pesanan<span x-show="user.orders_count > 0"> (<span x-text="user.paid_orders_count"></span> dibayar)</span> · terdaftar <span x-text="day(user.registered_at)"></span></span>
                        <span class="text-sm font-semibold tabular-nums text-gray-900" x-text="$format.formatRupiah(user.paid_total)"></span>
                    </span>
                </button>
            </template>
        </div>

        <x-admin-pager />

        {{-- Detail panel (read-only) --}}
        <div x-cloak x-show="detail || detailLoading || detailError" class="fixed inset-0 z-40 flex justify-end bg-black/30" @click.self="close()">
            <aside class="h-full w-full max-w-xl overflow-y-auto bg-white p-5 shadow-xl sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg font-bold" x-text="detail?.name ?? 'Detail pelanggan'"></h2>
                    <button type="button" @click="close()" class="rounded-lg px-2 py-1 text-sm font-semibold text-gray-500 hover:bg-gray-100" aria-label="Tutup">Tutup ✕</button>
                </div>

                <p x-show="detailLoading" class="mt-6 text-gray-500">Memuat…</p>
                <p x-cloak x-show="detailError" class="mt-6 rounded-lg bg-red-50 p-3 text-sm text-red-700" x-text="detailError"></p>

                <template x-if="detail">
                    <div class="mt-4 space-y-5 text-sm">
                        <section>
                            <h3 class="font-semibold">Akun</h3>
                            <dl class="mt-2 grid grid-cols-[8rem_1fr] gap-y-1">
                                <dt class="text-gray-500">Email</dt><dd class="break-all" x-text="detail.email"></dd>
                                <dt class="text-gray-500">HP</dt><dd x-text="detail.phone ?? '-'"></dd>
                                <dt class="text-gray-500">Terdaftar</dt><dd x-text="date(detail.registered_at)"></dd>
                                <dt class="text-gray-500">Email terverifikasi</dt><dd x-text="detail.email_verified_at ? date(detail.email_verified_at) : 'Belum'"></dd>
                                <dt class="text-gray-500">Pesanan</dt><dd><span x-text="detail.orders_count"></span> (<span x-text="detail.paid_orders_count"></span> dibayar)</dd>
                                <dt class="text-gray-500">Total belanja</dt><dd class="font-semibold tabular-nums" x-text="$format.formatRupiah(detail.paid_total)"></dd>
                            </dl>
                        </section>

                        <section>
                            <h3 class="font-semibold">Alamat (<span x-text="detail.addresses.length"></span>)</h3>
                            <p x-show="detail.addresses.length === 0" class="mt-1 text-gray-500">Belum menyimpan alamat.</p>
                            <ul class="mt-2 space-y-3">
                                <template x-for="a in detail.addresses" :key="a.id">
                                    <li class="rounded-lg border border-gray-200 p-3">
                                        <p class="flex flex-wrap items-center gap-2 font-medium">
                                            <span x-text="a.recipient_name"></span>
                                            <span x-show="a.label" class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-semibold text-gray-600" x-text="a.label"></span>
                                            <span x-show="a.is_default" class="rounded bg-green-100 px-1.5 py-0.5 text-[11px] font-semibold text-green-700">Utama</span>
                                        </p>
                                        <p class="text-gray-500" x-text="a.phone"></p>
                                        <p class="mt-1 text-gray-600" x-text="`${a.address_line}, ${a.subdistrict.name}, ${a.district.name}, ${a.city.name}, ${a.province.name} ${a.post_code}`"></p>
                                    </li>
                                </template>
                            </ul>
                        </section>

                        <section>
                            <h3 class="font-semibold">Pesanan terbaru</h3>
                            <p x-show="detail.recent_orders.length === 0" class="mt-1 text-gray-500">Belum pernah memesan.</p>
                            <ul class="mt-2 divide-y divide-gray-100">
                                <template x-for="order in detail.recent_orders" :key="order.order_number">
                                    <li class="flex items-center justify-between gap-3 py-2">
                                        <span>
                                            <a :href="`{{ route('admin.orders.index') }}?order=${order.order_number}`" class="font-mono font-semibold underline decoration-gray-300 hover:decoration-gray-900" x-text="order.order_number"></a>
                                            <span class="block text-xs text-gray-500" x-text="date(order.created_at)"></span>
                                        </span>
                                        <span class="text-right">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="badge(order.status)" x-text="order.status_label"></span>
                                            <span class="mt-1 block text-xs tabular-nums text-gray-600" x-text="$format.formatRupiah(order.grand_total)"></span>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                        </section>
                    </div>
                </template>
            </aside>
        </div>
    </div>
@endsection
