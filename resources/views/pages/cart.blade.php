@extends('layouts.app')

@section('title', 'Keranjang')
@section('robots', 'noindex, nofollow')

{{--
    Static preview: resources/js/components/cartPage.js works on the localStorage cart
    (resources/js/static/cart.js), seeded with the sample CART-01 object.
    Live data: CART-01 (show), CART-03 (qty), CART-04 (remove), CART-05 (clear).
--}}
@php($static = require resource_path('static/catalog.php'))

@section('content')
    <div x-data="cartPage()">
        <section class="bg-ink text-white">
            <div class="mx-auto max-w-7xl px-4 py-10 md:px-8">
                <h1 class="text-3xl font-bold sm:text-4xl">Keranjang <span class="text-brand">belanja</span></h1>
                <p class="mt-2 text-gray-400">
                    <span x-text="cart.summary.item_count">{{ $static['cart']['summary']['item_count'] }}</span> produk ·
                    <span x-text="cart.summary.total_qty">{{ $static['cart']['summary']['total_qty'] }}</span> barang
                </p>
            </div>
        </section>

        {{-- Empty state --}}
        <div x-cloak x-show="cart.items.length === 0" class="mx-auto max-w-md px-4 py-24 text-center">
            <span class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-brand-soft text-ink">
                <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.3" /><circle cx="18" cy="20" r="1.3" /><path d="M2.5 3h2.6l2.4 12.2a1.5 1.5 0 0 0 1.5 1.2h8.9a1.5 1.5 0 0 0 1.5-1.1L21.5 8H6" /></svg>
            </span>
            <h2 class="mt-6 text-2xl font-bold">Keranjang masih kosong</h2>
            <p class="mt-2 text-gray-600">Yuk, pilih sepeda listrik atau sparepart yang kamu butuhkan.</p>
            <a href="{{ route('products.index') }}" class="mt-8 inline-flex rounded-full bg-brand px-7 py-3.5 font-semibold text-ink transition hover:bg-brand-hover">Mulai belanja</a>
        </div>

        <div x-show="cart.items.length > 0" class="mx-auto max-w-7xl px-4 py-10 md:px-8">
            <div class="grid gap-8 lg:grid-cols-[1fr_22rem]">
                <div class="space-y-4">
                    <div x-show="cart.summary.has_issue" class="flex gap-3 rounded-2xl border border-brand bg-brand-soft p-4 text-sm" role="alert">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-ink" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.49 2.5c.67-1.17 2.35-1.17 3.02 0l6.28 10.9c.67 1.17-.17 2.6-1.51 2.6H3.72c-1.34 0-2.18-1.43-1.51-2.6L8.49 2.5ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" /></svg>
                        <p><span class="font-semibold">Ada item yang perlu diperbaiki.</span> Stok berubah sejak barang dimasukkan ke keranjang.</p>
                    </div>

                    <p x-cloak x-show="notice" x-text="notice" role="status"
                       class="rounded-2xl border border-gray-300 bg-white p-4 text-sm text-gray-700"></p>

                    <template x-for="item in cart.items" :key="item.id">
                        <article class="flex gap-4 rounded-2xl border bg-white p-4 transition sm:p-5"
                                 :class="item.issue ? 'border-brand ring-1 ring-brand' : 'border-gray-200'">
                            <a :href="`{{ url('/products') }}/${item.product.slug}`" class="h-24 w-24 shrink-0 overflow-hidden rounded-xl bg-ink-soft sm:h-28 sm:w-28">
                                <template x-if="item.variant.image_url">
                                    <img :src="item.variant.image_url" :alt="item.product.name" class="h-full w-full object-cover">
                                </template>
                                <template x-if="! item.variant.image_url">
                                    <span class="flex h-full w-full items-center justify-center bg-[radial-gradient(circle_at_30%_20%,#2a2a2a,#0a0a0a_70%)] text-brand" role="img" :aria-label="item.product.name">
                                        <svg class="h-1/3 w-1/3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="18" height="10" rx="2" /><path d="M22 11v2" /><path d="m11 9-2 3h4l-2 3" /></svg>
                                    </span>
                                </template>
                            </a>

                            <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:justify-between">
                                <div class="min-w-0">
                                    <a :href="`{{ url('/products') }}/${item.product.slug}`" x-text="item.product.name"
                                       class="font-semibold hover:underline hover:decoration-brand hover:decoration-2"></a>
                                    <p class="mt-1 text-sm text-gray-500">
                                        <template x-for="option in item.variant.variation_values" :key="option.label">
                                            <span><span x-text="option.label"></span>: <span class="text-ink" x-text="option.value"></span> · </span>
                                        </template>
                                        <span x-text="$format.formatWeight(item.variant.package_weight)"></span>
                                    </p>
                                    <p class="mt-2 text-sm font-semibold tabular-nums" x-text="$format.formatRupiah(item.variant.price)"></p>

                                    <p x-show="item.issue" class="mt-2 inline-flex rounded-lg bg-brand-soft px-2.5 py-1 text-xs font-medium text-ink">
                                        <span x-text="issueMessage(item) + ' Tersisa ' + item.variant.available_qty + '.'"></span>
                                    </p>
                                </div>

                                <div class="flex items-center justify-between gap-4 sm:flex-col sm:items-end">
                                    <p class="font-bold tabular-nums sm:order-last" x-text="$format.formatRupiah(item.subtotal)"></p>
                                    <div class="flex items-center gap-2">
                                        <div class="inline-flex items-center rounded-full border border-gray-300">
                                            <button type="button" class="grid h-9 w-9 place-items-center font-semibold transition hover:text-brand-hover disabled:text-gray-300"
                                                    @click="update(item, item.qty - 1)" :disabled="! canDecrease(item)" aria-label="Kurangi">−</button>
                                            <span class="w-8 text-center text-sm font-semibold tabular-nums" x-text="item.qty"></span>
                                            <button type="button" class="grid h-9 w-9 place-items-center font-semibold transition hover:text-brand-hover disabled:text-gray-300"
                                                    @click="update(item, item.qty + 1)" :disabled="! canIncrease(item)" aria-label="Tambah">+</button>
                                        </div>
                                        <button type="button" @click="remove(item)"
                                                class="grid h-9 w-9 place-items-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-red-600" aria-label="Hapus item">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4h6v3" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 text-sm">
                        <a href="{{ route('products.index') }}" class="font-semibold underline decoration-brand decoration-2 underline-offset-4">&larr; Lanjut belanja</a>

                        <button type="button" x-show="! confirmingClear" @click="confirmingClear = true" class="text-gray-500 transition hover:text-red-600">Kosongkan keranjang</button>
                        <div x-cloak x-show="confirmingClear" class="flex items-center gap-2">
                            <span class="text-gray-600">Hapus semua item?</span>
                            <button type="button" @click="clear()" class="rounded-full bg-red-600 px-4 py-1.5 font-semibold text-white transition hover:bg-red-700">Ya, kosongkan</button>
                            <button type="button" @click="confirmingClear = false" class="rounded-full border border-gray-300 px-4 py-1.5 font-semibold transition hover:border-ink">Batal</button>
                        </div>
                    </div>
                </div>

                {{-- Summary --}}
                <aside class="h-fit rounded-2xl bg-ink p-6 text-white lg:sticky lg:top-24">
                    <h2 class="text-lg font-semibold">Ringkasan</h2>
                    <dl class="mt-6 space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-gray-400">Subtotal (<span x-text="cart.summary.total_qty"></span> barang)</dt><dd class="font-semibold tabular-nums" x-text="$format.formatRupiah(cart.summary.subtotal)"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-400">Total berat</dt><dd class="font-semibold" x-text="$format.formatWeight(cart.summary.total_weight)"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-400">Ongkos kirim</dt><dd class="text-gray-400">Dihitung saat checkout</dd></div>
                    </dl>
                    <div class="my-5 h-px bg-ink-line"></div>
                    <div class="flex items-baseline justify-between gap-4">
                        <span class="text-sm text-gray-400">Total</span>
                        <span class="text-2xl font-bold tabular-nums text-brand" x-text="$format.formatRupiah(cart.summary.subtotal)"></span>
                    </div>

                    <button type="button" x-show="cart.summary.has_issue" disabled
                            class="mt-6 w-full cursor-not-allowed rounded-full bg-ink-line py-3.5 font-semibold text-gray-500">Perbaiki item dulu</button>
                    <a href="{{ route('checkout') }}" x-cloak x-show="! cart.summary.has_issue"
                       class="mt-6 block w-full rounded-full bg-brand py-3.5 text-center font-semibold text-ink transition hover:bg-brand-hover">Checkout</a>

                    <p class="mt-4 text-center text-xs text-gray-500">Pembayaran aman via Xendit</p>
                </aside>
            </div>
        </div>
    </div>
@endsection
