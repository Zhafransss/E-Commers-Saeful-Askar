@extends('layouts.app')

@section('title', 'Checkout')
@section('robots', 'noindex, nofollow')

{{--
    Static preview: resources/js/components/checkoutPage.js. Live data: ADDR-01/02 and
    REG-01 … REG-04 (alamat), SHIP-01 (kurir & ongkir), ORD-01 (bayar → Xendit invoice).
--}}
@php
    $checkout = require resource_path('static/checkout.php');
    $input = 'w-full rounded-xl border bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40';
@endphp

@section('content')
    <div x-data="checkoutPage(@js($checkout))" data-finish-url="{{ route('checkout.finish') }}" class="pb-28 lg:pb-0">
        {{-- Header + steps --}}
        <section class="bg-ink text-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 md:flex-row md:items-end md:justify-between md:px-8 md:py-10">
                <div>
                    <a href="{{ route('cart') }}" class="text-xs text-gray-400 transition hover:text-brand">&larr; Kembali ke keranjang</a>
                    <h1 class="mt-3 text-3xl font-bold sm:text-4xl"><span class="text-brand">Checkout</span></h1>
                </div>
                <ol class="flex items-center gap-2 text-xs font-semibold sm:text-sm" aria-label="Tahapan">
                    <li class="flex items-center gap-2 text-gray-400">
                        <span class="grid h-6 w-6 place-items-center rounded-full bg-ink-line text-brand">✓</span> Keranjang
                    </li>
                    <li class="h-px w-6 bg-ink-line sm:w-10" aria-hidden="true"></li>
                    <li class="flex items-center gap-2 text-white" aria-current="step">
                        <span class="grid h-6 w-6 place-items-center rounded-full bg-brand text-ink">2</span> Checkout
                    </li>
                    <li class="h-px w-6 bg-ink-line sm:w-10" aria-hidden="true"></li>
                    <li class="flex items-center gap-2 text-gray-500">
                        <span class="grid h-6 w-6 place-items-center rounded-full border border-ink-line">3</span> Pembayaran
                    </li>
                </ol>
            </div>
        </section>

        {{-- Empty cart --}}
        <div x-cloak x-show="cart.items.length === 0" class="mx-auto max-w-md px-4 py-24 text-center">
            <h2 class="text-2xl font-bold">Keranjang masih kosong</h2>
            <p class="mt-2 text-gray-600">Tambahkan produk ke keranjang sebelum checkout.</p>
            <a href="{{ route('products.index') }}" class="mt-8 inline-flex rounded-full bg-brand px-7 py-3.5 font-semibold text-ink transition hover:bg-brand-hover">Mulai belanja</a>
        </div>

        <div x-show="cart.items.length > 0" class="mx-auto max-w-7xl px-4 py-8 md:px-8 md:py-10">
            <div x-cloak x-show="cart.summary.has_issue" class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-brand bg-brand-soft p-4 text-sm" role="alert">
                <p><span class="font-semibold">Ada item di keranjang yang perlu diperbaiki</span> (stok berubah). Checkout belum bisa dilanjutkan.</p>
                <a href="{{ route('cart') }}" class="font-semibold underline decoration-2 underline-offset-4">Perbaiki keranjang</a>
            </div>

            <div class="grid gap-8 lg:grid-cols-[1fr_24rem]">
                <div class="space-y-6">
                    {{-- 1. Address --}}
                    <section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="flex items-center gap-3 text-lg font-bold">
                                <span class="grid h-8 w-8 place-items-center rounded-full bg-ink text-sm text-brand">1</span> Alamat pengiriman
                            </h2>
                            <button type="button" x-show="! formOpen" @click="openForm()" class="text-sm font-semibold text-ink underline decoration-brand decoration-2 underline-offset-4 hover:text-gray-600">+ Tambah alamat</button>
                        </div>

                        <div x-show="! formOpen" class="mt-5 grid gap-3" role="radiogroup" aria-label="Pilih alamat">
                            <template x-for="a in addresses" :key="a.id">
                                <label class="flex cursor-pointer gap-4 rounded-xl border-2 p-4 transition"
                                       :class="addressId === a.id ? 'border-ink bg-gray-50' : 'border-gray-200 hover:border-gray-400'">
                                    <input type="radio" name="address" class="mt-1 h-4 w-4 shrink-0 accent-ink" :value="a.id" :checked="addressId === a.id" @change="addressId = a.id">
                                    <span class="min-w-0 text-sm">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold" x-text="a.recipient_name"></span>
                                            <span class="text-gray-400">·</span>
                                            <span class="text-gray-600" x-text="a.phone"></span>
                                            <span x-show="a.label" x-text="a.label" class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700"></span>
                                            <span x-show="a.is_default" class="rounded-md bg-brand px-2 py-0.5 text-xs font-semibold text-ink">Utama</span>
                                        </span>
                                        <span class="mt-1 block leading-relaxed text-gray-600" x-text="formatAddress(a)"></span>
                                    </span>
                                </label>
                            </template>
                        </div>

                        {{-- New address (ADDR-02) --}}
                        <form x-cloak x-show="formOpen" @submit.prevent="saveAddress()" novalidate class="mt-5 grid gap-4 sm:grid-cols-2">
                            @foreach ([
                                ['recipient_name', 'Nama penerima', 'text', 'Nama lengkap', 'name'],
                                ['phone', 'Nomor HP', 'tel', '08xxxxxxxxxx', 'tel'],
                            ] as [$field, $label, $type, $placeholder, $autocomplete])
                                <label class="block">
                                    <span class="mb-1.5 block text-sm font-medium">{{ $label }}</span>
                                    <input type="{{ $type }}" x-model="form.{{ $field }}" placeholder="{{ $placeholder }}" autocomplete="{{ $autocomplete }}"
                                           :class="formErrors.{{ $field }} ? 'border-red-500' : 'border-gray-300 focus:border-ink'" class="{{ $input }}">
                                    <span x-show="formErrors.{{ $field }}" x-text="formErrors.{{ $field }}" class="mt-1 block text-xs text-red-600"></span>
                                </label>
                            @endforeach

                            <label class="block sm:col-span-2">
                                <span class="mb-1.5 block text-sm font-medium">Alamat lengkap</span>
                                <textarea x-model="form.address_line" rows="2" maxlength="255" placeholder="Nama jalan, nomor rumah, RT/RW, patokan" autocomplete="street-address"
                                          :class="formErrors.address_line ? 'border-red-500' : 'border-gray-300 focus:border-ink'" class="{{ $input }}"></textarea>
                                <span x-show="formErrors.address_line" x-text="formErrors.address_line" class="mt-1 block text-xs text-red-600"></span>
                            </label>

                            @foreach ([
                                ['province_id', 'Provinsi', 'regions', null],
                                ['city_id', 'Kota/Kabupaten', 'cities', 'province_id'],
                                ['district_id', 'Kecamatan', 'districts', 'city_id'],
                                ['subdistrict_id', 'Kelurahan/Desa', 'subdistricts', 'district_id'],
                            ] as [$field, $label, $options, $parent])
                                <label class="block">
                                    <span class="mb-1.5 block text-sm font-medium">{{ $label }}</span>
                                    <select x-model="form.{{ $field }}" @change="pickRegion('{{ $field }}')" @if ($parent) :disabled="! form.{{ $parent }}" @endif
                                            :class="formErrors.{{ $field }} ? 'border-red-500' : 'border-gray-300 focus:border-ink'" class="{{ $input }} disabled:bg-gray-100 disabled:text-gray-400">
                                        <option value="">Pilih {{ strtolower($label) }}</option>
                                        <template x-for="r in {{ $options }}" :key="r.id">
                                            <option :value="r.id" x-text="r.name" :selected="r.id === form.{{ $field }}"></option>
                                        </template>
                                    </select>
                                    <span x-show="formErrors.{{ $field }}" x-text="formErrors.{{ $field }}" class="mt-1 block text-xs text-red-600"></span>
                                </label>
                            @endforeach

                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium">Kode pos</span>
                                <input type="text" x-model="form.post_code" inputmode="numeric" maxlength="5" placeholder="17565" autocomplete="postal-code"
                                       :class="formErrors.post_code ? 'border-red-500' : 'border-gray-300 focus:border-ink'" class="{{ $input }}">
                                <span x-show="formErrors.post_code" x-text="formErrors.post_code" class="mt-1 block text-xs text-red-600"></span>
                            </label>
                            <label class="block">
                                <span class="mb-1.5 block text-sm font-medium">Label <span class="font-normal text-gray-400">(opsional)</span></span>
                                <input type="text" x-model="form.label" maxlength="50" placeholder="Rumah, Kantor, …" class="{{ $input }} border-gray-300 focus:border-ink">
                            </label>

                            <label class="flex items-center gap-2 text-sm sm:col-span-2">
                                <input type="checkbox" x-model="form.is_default" class="h-4 w-4 accent-ink"> Jadikan alamat utama
                            </label>

                            <div class="flex flex-wrap gap-3 sm:col-span-2">
                                <button type="submit" class="rounded-full bg-ink px-6 py-3 text-sm font-semibold text-brand transition hover:bg-ink-soft">Simpan & gunakan alamat</button>
                                <button type="button" x-show="addresses.length" @click="formOpen = false" class="rounded-full border border-gray-300 px-6 py-3 text-sm font-semibold transition hover:border-ink">Batal</button>
                            </div>
                        </form>
                    </section>

                    {{-- 2. Shipping --}}
                    <section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-lg font-bold">
                            <span class="grid h-8 w-8 place-items-center rounded-full bg-ink text-sm text-brand">2</span> Kurir pengiriman
                        </h2>
                        <p class="mt-2 text-sm text-gray-500">
                            Dikirim dari Gudang Tambun Utara, Bekasi · berat total <span class="font-semibold text-ink" x-text="$format.formatWeight(cart.summary.total_weight)"></span>
                        </p>

                        <p x-show="! address" class="mt-5 rounded-xl bg-gray-50 p-4 text-sm text-gray-600">Pilih atau tambahkan alamat dulu untuk melihat ongkir.</p>

                        <div x-cloak x-show="ratesLoading" class="mt-5 space-y-3" aria-busy="true" aria-label="Menghitung ongkir">
                            <template x-for="n in 3" :key="n">
                                <div class="h-[74px] animate-pulse rounded-xl bg-gray-100"></div>
                            </template>
                        </div>

                        <p x-cloak x-show="ratesError" x-text="ratesError" class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-700"></p>

                        <div x-show="! ratesLoading && rates.length" class="mt-5 grid gap-3" role="radiogroup" aria-label="Pilih kurir">
                            <template x-for="r in rates" :key="r.rate_id">
                                <label class="flex cursor-pointer items-center gap-4 rounded-xl border-2 p-4 transition"
                                       :class="rateId === r.rate_id ? 'border-ink bg-gray-50' : 'border-gray-200 hover:border-gray-400'">
                                    <input type="radio" name="rate" class="h-4 w-4 shrink-0 accent-ink" :value="r.rate_id" :checked="rateId === r.rate_id" @change="rateId = r.rate_id">
                                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-ink text-[11px] font-bold uppercase text-brand" x-text="r.courier_code"></span>
                                    <span class="min-w-0 flex-1 text-sm">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold" x-text="r.service_name"></span>
                                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600" x-text="serviceTypeLabel(r.service_type)"></span>
                                        </span>
                                        <span class="mt-0.5 block text-gray-500">Estimasi tiba <span x-text="r.etd_text"></span></span>
                                    </span>
                                    <span class="shrink-0 font-bold tabular-nums" x-text="$format.formatRupiah(r.cost)"></span>
                                </label>
                            </template>
                            <p class="text-xs text-gray-500">Ongkir berlaku 30 menit. Sepeda listrik umumnya dikirim dengan layanan kargo.</p>
                        </div>
                    </section>

                    {{-- 3. Note --}}
                    <section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-lg font-bold">
                            <span class="grid h-8 w-8 place-items-center rounded-full bg-ink text-sm text-brand">3</span> Catatan untuk toko
                            <span class="text-sm font-normal text-gray-400">(opsional)</span>
                        </h2>
                        <textarea x-model="note" rows="3" maxlength="255" placeholder="Contoh: tolong dibungkus kardus tebal"
                                  class="mt-4 w-full rounded-xl border border-gray-300 px-4 py-3 text-sm focus:border-ink focus:outline-none focus:ring-2 focus:ring-brand/40"></textarea>
                        <p class="mt-1 text-right text-xs text-gray-400"><span x-text="note.length"></span>/255</p>
                    </section>

                    {{-- 4. Payment info --}}
                    <section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-lg font-bold">
                            <span class="grid h-8 w-8 place-items-center rounded-full bg-ink text-sm text-brand">4</span> Pembayaran
                        </h2>
                        <div class="mt-4 flex gap-4 rounded-xl bg-gray-50 p-4 text-sm text-gray-600">
                            <svg class="h-6 w-6 shrink-0 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="10" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                            <p>Setelah menekan <span class="font-semibold text-ink">Bayar</span>, Anda diarahkan ke halaman pembayaran aman <span class="font-semibold text-ink">Xendit</span> untuk memilih metode bayar. Selesaikan pembayaran dalam <span class="font-semibold text-ink">2 jam</span>; lewat dari itu pesanan dibatalkan otomatis.</p>
                        </div>
                    </section>
                </div>

                {{-- Summary --}}
                <aside class="h-fit space-y-4 lg:sticky lg:top-24">
                    <div class="rounded-2xl bg-ink p-6 text-white">
                        <h2 class="text-lg font-semibold">Ringkasan pesanan</h2>
                        <ul class="mt-5 max-h-72 space-y-4 overflow-y-auto pr-1">
                            <template x-for="item in cart.items" :key="item.id">
                                <li class="flex gap-3">
                                    <span class="relative h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-ink-soft">
                                        <template x-if="item.variant.image_url"><img :src="item.variant.image_url" alt="" class="h-full w-full object-cover"></template>
                                        <span class="absolute -right-0 -top-0 grid h-5 min-w-5 place-items-center rounded-bl-lg bg-brand px-1 text-[11px] font-bold text-ink" x-text="item.qty"></span>
                                    </span>
                                    <span class="min-w-0 flex-1 text-sm">
                                        <span class="block truncate font-medium" x-text="item.product.name"></span>
                                        <span class="block text-xs text-gray-400" x-text="item.variant.variation_values.map((v) => v.value).join(', ')"></span>
                                    </span>
                                    <span class="shrink-0 text-sm font-semibold tabular-nums" x-text="$format.formatRupiah(item.subtotal)"></span>
                                </li>
                            </template>
                        </ul>

                        <dl class="mt-5 space-y-3 border-t border-ink-line pt-5 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-gray-400">Subtotal (<span x-text="cart.summary.total_qty"></span> barang)</dt><dd class="font-semibold tabular-nums" x-text="$format.formatRupiah(cart.summary.subtotal)"></dd></div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-400">Ongkos kirim <span x-show="rate" class="block text-xs" x-text="rate ? rate.service_name : ''"></span></dt>
                                <dd class="font-semibold tabular-nums" x-text="rate ? $format.formatRupiah(shippingCost) : '—'"></dd>
                            </div>
                            <div x-cloak x-show="insuranceCost > 0" class="flex justify-between gap-4">
                                <dt class="text-gray-400">Asuransi pengiriman</dt>
                                <dd class="font-semibold tabular-nums" x-text="$format.formatRupiah(insuranceCost)"></dd>
                            </div>
                        </dl>
                        <div class="mt-5 flex items-baseline justify-between gap-4 border-t border-ink-line pt-5">
                            <span class="text-sm text-gray-400">Total bayar</span>
                            <span class="text-2xl font-bold tabular-nums text-brand" x-text="$format.formatRupiah(grandTotal)"></span>
                        </div>

                        <button type="button" @click="pay()" :disabled="blockedReason !== null || paying"
                                class="mt-6 hidden w-full items-center justify-center gap-2 rounded-full bg-brand py-3.5 font-semibold text-ink transition hover:bg-brand-hover disabled:cursor-not-allowed disabled:bg-ink-line disabled:text-gray-500 lg:flex">
                            <svg x-show="paying" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                            <span x-text="paying ? 'Membuat pesanan…' : (blockedReason ?? 'Bayar sekarang')"></span>
                        </button>
                        <p x-cloak x-show="payError" x-text="payError" class="mt-3 rounded-lg bg-red-500/10 p-3 text-sm text-red-300" role="alert"></p>
                        <p class="mt-4 text-center text-xs text-gray-500">Pembayaran diproses aman oleh Xendit</p>
                    </div>

                    <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 text-sm text-gray-600">
                        <svg class="h-5 w-5 shrink-0 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" /></svg>
                        Stok dikunci untuk Anda begitu pesanan dibuat, sampai batas waktu bayar.
                    </div>
                </aside>
            </div>
        </div>

        {{-- Mobile pay bar --}}
        <div x-show="cart.items.length > 0" class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white px-4 pt-3 shadow-[0_-8px_24px_rgba(0,0,0,0.08)] lg:hidden"
             style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px))">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                <div>
                    <p class="text-xs text-gray-500">Total bayar</p>
                    <p class="text-lg font-bold tabular-nums" x-text="$format.formatRupiah(grandTotal)"></p>
                </div>
                <button type="button" @click="pay()" :disabled="blockedReason !== null || paying"
                        class="flex-1 rounded-full bg-brand py-3.5 text-sm font-semibold text-ink transition disabled:bg-gray-200 disabled:text-gray-500 sm:flex-none sm:px-10">
                    <span x-text="paying ? 'Membuat pesanan…' : (blockedReason ?? 'Bayar sekarang')"></span>
                </button>
            </div>
        </div>
    </div>
@endsection
