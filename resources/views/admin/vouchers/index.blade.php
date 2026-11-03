@extends('layouts.admin')

@section('title', 'Voucher Ongkir')

{{-- ADM-30 list, ADM-31 create, ADM-32 update, ADM-33 delete. Behaviour: resources/js/components/adminVouchers.js --}}
@section('content')
    <div x-data="adminVouchers" @keydown.escape.window="closeForm(); removing = null">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1>Voucher Ongkir</h1>
                <p class="mt-1 text-sm text-gray-500">Voucher hanya memotong ongkos kirim, bukan harga produk atau asuransi. Voucher yang aktif tampil di halaman checkout, dan yang potongannya terbesar langsung terpakai.</p>
            </div>
            <button type="button" @click="openCreate()" class="rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-ink shadow-sm transition hover:bg-gold-hover">+ Tambah voucher</button>
        </div>

        <p x-cloak x-show="notice" class="mt-4 flex items-start justify-between gap-3 rounded-lg bg-green-50 p-3 text-sm text-green-700">
            <span x-text="notice"></span>
            <button type="button" @click="notice = null" class="font-semibold" aria-label="Tutup">✕</button>
        </p>

        <form class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4" @submit.prevent="fetchPage(1)">
            <label class="min-w-64 flex-1 text-xs font-semibold text-gray-500">Cari
                <input type="search" x-model="filters.q" @input="search()" placeholder="Nama voucher"
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
            </label>
            <button type="button" @click="reset()" class="pb-2 text-sm font-semibold text-gray-500 underline hover:text-gray-900">Reset</button>
        </form>

        <div x-cloak x-show="error" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            <span x-text="error"></span> <button type="button" class="ml-2 font-semibold underline" @click="fetchPage(1)">Coba lagi</button>
        </div>

        <div class="mt-4 hidden overflow-x-auto rounded-xl border border-gray-200 bg-white md:block">
            <table class="w-full min-w-[56rem] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Voucher</th>
                        <th class="px-4 py-3">Min. belanja</th>
                        <th class="px-4 py-3">Periode</th>
                        <th class="px-4 py-3">Pemakaian</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr x-show="loading"><td colspan="6" class="px-4 py-8 text-center text-gray-500">Memuat…</td></tr>
                    <tr x-cloak x-show="!loading && items.length === 0"><td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada voucher ongkir.</td></tr>
                    <template x-for="voucher in items" :key="voucher.id">
                        <tr>
                            <td class="px-4 py-3">
                                <span class="block font-medium" x-text="voucher.name"></span>
                                <span class="block text-xs text-gray-500" x-text="benefit(voucher)"></span>
                            </td>
                            <td class="px-4 py-3 tabular-nums" x-text="voucher.min_subtotal > 0 ? $format.formatRupiah(voucher.min_subtotal) : 'Tanpa minimal'"></td>
                            <td class="px-4 py-3 text-gray-600" x-text="period(voucher)"></td>
                            <td class="px-4 py-3 text-gray-600">
                                <span class="block tabular-nums" x-text="usage(voucher)"></span>
                                <span class="block text-xs text-gray-400" x-text="voucher.per_user_limit === null ? 'Tanpa batas per pembeli' : `Maks. ${voucher.per_user_limit}× per pembeli`"></span>
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadge(voucher.status)" x-text="voucher.status_label"></span></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" @click="openEdit(voucher)" class="rounded-lg px-3 py-1.5 text-sm font-semibold hover:bg-gold-soft">Ubah</button>
                                <button type="button" @click="askRemove(voucher)" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-red-600 hover:bg-red-50">Hapus</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="mt-4 space-y-3 md:hidden">
            <p x-show="loading" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Memuat…</p>
            <p x-cloak x-show="!loading && items.length === 0" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Belum ada voucher ongkir.</p>
            <template x-for="voucher in items" :key="voucher.id">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-medium" x-text="voucher.name"></p>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusBadge(voucher.status)" x-text="voucher.status_label"></span>
                    </div>
                    <p class="text-sm text-gray-600" x-text="benefit(voucher)"></p>
                    <p class="mt-1 text-xs text-gray-500" x-text="voucher.min_subtotal > 0 ? 'Min. belanja ' + $format.formatRupiah(voucher.min_subtotal) : 'Tanpa minimal belanja'"></p>
                    <p class="text-xs text-gray-500" x-text="period(voucher)"></p>
                    <p class="text-xs text-gray-500"><span x-text="usage(voucher)"></span> · <span x-text="voucher.per_user_limit === null ? 'tanpa batas per pembeli' : `maks. ${voucher.per_user_limit}× per pembeli`"></span></p>
                    <div class="mt-3 flex gap-2">
                        <button type="button" @click="openEdit(voucher)" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold">Ubah</button>
                        <button type="button" @click="askRemove(voucher)" class="flex-1 rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600">Hapus</button>
                    </div>
                </div>
            </template>
        </div>

        <x-admin-pager />

        {{-- Add / edit form --}}
        <div x-cloak x-show="editing !== null" x-transition.opacity class="fixed inset-0 z-[60] flex items-end justify-center bg-ink/50 p-0 backdrop-blur-sm sm:items-center sm:p-4" @click.self="closeForm()">
            <form @submit.prevent="save()" class="max-h-full w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl sm:p-6" autocomplete="off">
                <h2 class="text-lg font-bold" x-text="editing === 0 ? 'Tambah voucher ongkir' : 'Ubah voucher ongkir'"></h2>
                <p x-cloak x-show="formError" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700" x-text="formError"></p>

                <div class="mt-4 space-y-4">
                    <label class="block text-xs font-semibold text-gray-500">Nama voucher (tampil ke pembeli)
                        <input type="text" x-model="form.name" required maxlength="100" placeholder="Contoh: Gratis Ongkir November" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                        <span x-show="fieldErrors.name" class="mt-1 block font-normal text-red-600" x-text="fieldErrors.name"></span>
                    </label>

                    <fieldset>
                        <legend class="text-xs font-semibold text-gray-500">Jenis</legend>
                        <div class="mt-1 grid gap-2 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-start gap-2 rounded-lg border p-3 text-sm" :class="form.type === 'free_shipping' ? 'border-ink bg-gray-50' : 'border-gray-300'">
                                <input type="radio" value="free_shipping" x-model="form.type" class="mt-0.5 accent-ink">
                                <span><span class="block font-semibold">Gratis ongkir</span><span class="block text-xs text-gray-500">Seluruh ongkos kirim ditanggung toko</span></span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-2 rounded-lg border p-3 text-sm" :class="form.type === 'shipping_discount' ? 'border-ink bg-gray-50' : 'border-gray-300'">
                                <input type="radio" value="shipping_discount" x-model="form.type" class="mt-0.5 accent-ink">
                                <span><span class="block font-semibold">Potongan ongkir</span><span class="block text-xs text-gray-500">Ongkir dipotong sejumlah rupiah tertentu</span></span>
                            </label>
                        </div>
                        <span x-show="fieldErrors.type" class="mt-1 block text-xs text-red-600" x-text="fieldErrors.type"></span>
                    </fieldset>

                    <label x-show="form.type === 'shipping_discount'" class="block text-xs font-semibold text-gray-500">Potongan ongkir (Rp)
                        <input type="number" x-model="form.amount" min="1" step="1" inputmode="numeric" placeholder="5000" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                        <span class="mt-1 block font-normal text-gray-400">Bila ongkir lebih kecil dari potongan, ongkir menjadi Rp0.</span>
                        <span x-show="fieldErrors.amount" class="mt-1 block font-normal text-red-600" x-text="fieldErrors.amount"></span>
                    </label>

                    <label class="block text-xs font-semibold text-gray-500">Minimal belanja (Rp)
                        <input type="number" x-model="form.min_subtotal" required min="0" step="1" inputmode="numeric" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                        <span class="mt-1 block font-normal text-gray-400">Dari total harga produk, sebelum ongkir. Isi 0 bila tanpa minimal.</span>
                        <span x-show="fieldErrors.min_subtotal" class="mt-1 block font-normal text-red-600" x-text="fieldErrors.min_subtotal"></span>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-xs font-semibold text-gray-500">Mulai berlaku (WIB)
                            <input type="datetime-local" x-model="form.starts_at" required class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                            <span x-show="fieldErrors.starts_at" class="mt-1 block font-normal text-red-600" x-text="fieldErrors.starts_at"></span>
                        </label>
                        <label class="block text-xs font-semibold text-gray-500">Berakhir (WIB)
                            <input type="datetime-local" x-model="form.ends_at" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                            <span class="mt-1 block font-normal text-gray-400">Kosongkan bila tanpa batas waktu.</span>
                            <span x-show="fieldErrors.ends_at" class="mt-1 block font-normal text-red-600" x-text="fieldErrors.ends_at"></span>
                        </label>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-xs font-semibold text-gray-500">Kuota total
                            <input type="number" x-model="form.quota" min="1" step="1" inputmode="numeric" placeholder="Tanpa batas" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                            <span class="mt-1 block font-normal text-gray-400">Berapa kali voucher boleh dipakai semua pembeli.</span>
                            <span x-show="fieldErrors.quota" class="mt-1 block font-normal text-red-600" x-text="fieldErrors.quota"></span>
                        </label>
                        <label class="block text-xs font-semibold text-gray-500">Batas per pembeli
                            <input type="number" x-model="form.per_user_limit" min="1" step="1" inputmode="numeric" placeholder="Tanpa batas" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                            <span class="mt-1 block font-normal text-gray-400">Berapa kali satu pembeli boleh memakainya.</span>
                            <span x-show="fieldErrors.per_user_limit" class="mt-1 block font-normal text-red-600" x-text="fieldErrors.per_user_limit"></span>
                        </label>
                    </div>
                    <p class="rounded-lg bg-gray-50 p-3 text-xs text-gray-500">Pesanan yang belum dibayar ikut menghitung kuota. Bila pesanan itu kedaluwarsa atau dibatalkan, kuotanya kembali.</p>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="closeForm()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold">Batal</button>
                    <button type="submit" :disabled="saving" class="rounded-lg bg-gold px-4 py-2 text-sm font-semibold text-ink shadow-sm transition hover:bg-gold-hover disabled:opacity-40" x-text="saving ? 'Menyimpan…' : 'Simpan'"></button>
                </div>
            </form>
        </div>

        {{-- Delete confirmation --}}
        <div x-cloak x-show="removing" x-transition.opacity class="fixed inset-0 z-[60] flex items-center justify-center bg-ink/50 p-4 backdrop-blur-sm" @click.self="removing = null">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-bold">Hapus voucher?</h2>
                <p class="mt-2 text-sm text-gray-600"><span class="font-semibold" x-text="removing?.name"></span> langsung hilang dari halaman checkout. Voucher yang sudah dipakai di pesanan tidak bisa dihapus; ubah waktu berakhirnya untuk menghentikannya.</p>
                <p x-cloak x-show="removeError" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700" x-text="removeError"></p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="removing = null" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold">Batal</button>
                    <button type="button" @click="remove()" :disabled="removeBusy" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-40" x-text="removeBusy ? 'Menghapus…' : 'Hapus'"></button>
                </div>
            </div>
        </div>
    </div>
@endsection
