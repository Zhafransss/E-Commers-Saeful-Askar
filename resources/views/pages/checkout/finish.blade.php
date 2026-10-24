@extends('layouts.app')

@section('title', 'Status Pembayaran')
@section('robots', 'noindex, nofollow')

{{--
    Return page from DANA (Gapura): /checkout/finish?order=WEB-000123 (INT-GP-01, PAY_RETURN).
    Polls ORD-03 every 3 s (max 60 s) until the order leaves pending_payment; "Lanjutkan pembayaran" is ORD-04.
    Behaviour lives in resources/js/components/finishPage.js.
--}}
@section('content')
    <div x-data="finishPage(@js($orderNumber), @js($failed))" class="bg-gray-50">
        <div class="mx-auto max-w-3xl px-4 py-12 md:py-16">
            {{-- Status card --}}
            <section class="overflow-hidden rounded-3xl border border-gray-200 bg-white text-center">
                <div class="px-6 py-10 sm:px-10">
                    {{-- checking --}}
                    <div x-show="state === 'checking'">
                        <svg class="mx-auto h-14 w-14 animate-spin text-brand" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" /></svg>
                        <h1 class="mt-6 text-2xl font-bold">Memeriksa status pembayaran…</h1>
                        <p class="mt-2 text-gray-600">Mohon tunggu sebentar, jangan tutup halaman ini.</p>
                    </div>

                    {{-- paid --}}
                    <div x-cloak x-show="state === 'paid'">
                        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-green-100 text-green-600">
                            <svg class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                        </span>
                        <h1 class="mt-6 text-2xl font-bold">Pembayaran berhasil</h1>
                        <p class="mt-2 text-gray-600">Terima kasih! Pesanan Anda sedang disiapkan gudang. Nomor resi muncul di halaman pesanan setelah dikirim.</p>
                    </div>

                    {{-- pending --}}
                    <div x-cloak x-show="state === 'pending'">
                        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-brand-soft text-ink">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                        </span>
                        <h1 class="mt-6 text-2xl font-bold">Menunggu pembayaran</h1>
                        <p class="mt-2 text-gray-600">Pesanan sudah dibuat dan stok dikunci untuk Anda. Selesaikan pembayaran sebelum batas waktu.</p>
                        <div x-show="remaining" class="mx-auto mt-6 w-fit rounded-2xl bg-ink px-6 py-4 text-white">
                            <p class="text-xs uppercase tracking-widest text-gray-400">Sisa waktu bayar</p>
                            <p class="mt-1 font-mono text-3xl font-bold tabular-nums text-brand" x-text="remaining"></p>
                        </div>
                    </div>

                    {{-- failed --}}
                    <div x-cloak x-show="state === 'failed'">
                        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-red-100 text-red-600">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                        </span>
                        <h1 class="mt-6 text-2xl font-bold">Pembayaran belum berhasil</h1>
                        <p class="mt-2 text-gray-600">Tidak ada dana yang terpotong. Anda bisa mencoba lagi selama batas waktu bayar belum habis.</p>
                    </div>

                    {{-- closed (expired / cancelled / unknown) --}}
                    <div x-cloak x-show="state === 'closed'">
                        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-gray-100 text-gray-500">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                        </span>
                        <h1 class="mt-6 text-2xl font-bold" x-text="order ? 'Pesanan ' + order.status_label.toLowerCase() : 'Pesanan tidak ditemukan'"></h1>
                        <p class="mt-2 text-gray-600">Pesanan ini tidak bisa dibayar lagi. Silakan belanja kembali bila masih membutuhkannya.</p>
                    </div>

                    {{-- login (no session on this visit; the payment itself is handled by Gapura's notification) --}}
                    <div x-cloak x-show="state === 'login'">
                        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-brand-soft text-ink">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></svg>
                        </span>
                        <h1 class="mt-6 text-2xl font-bold">Masuk untuk melihat status pesanan</h1>
                        <p class="mt-2 text-gray-600">Pembayaran Anda diproses otomatis. Masuk ke akun untuk melihat statusnya.</p>
                        <a href="{{ route('login', ['next' => '/checkout/finish?order='.$orderNumber]) }}"
                           class="mt-6 inline-block rounded-full bg-brand px-7 py-3.5 text-sm font-semibold text-ink transition hover:bg-brand-hover">Masuk</a>
                    </div>

                    <p class="mt-6 text-sm text-gray-500">Nomor pesanan <span class="font-mono font-semibold text-ink" x-text="orderNumber">{{ $orderNumber }}</span></p>

                    <p x-cloak x-show="resumeError" class="mt-4 text-sm text-red-600" x-text="resumeError"></p>

                    <div x-cloak x-show="state !== 'checking' && state !== 'login'" class="mt-8 flex flex-wrap justify-center gap-3">
                        {{-- ORD-04 returns the existing payment page (never a new bill). --}}
                        <button type="button" x-show="state === 'pending' || state === 'failed'" @click="resume()" :disabled="resuming"
                                class="rounded-full bg-brand px-7 py-3.5 text-sm font-semibold text-ink transition hover:bg-brand-hover">
                            Lanjutkan pembayaran
                        </button>
                        <a href="{{ $orderNumber ? route('account.orders.show', $orderNumber) : route('account.orders') }}"
                           class="rounded-full border-2 border-ink px-7 py-3 text-sm font-semibold transition hover:bg-ink hover:text-brand">Lihat pesanan</a>
                        <a href="{{ route('products.index') }}" class="rounded-full px-5 py-3.5 text-sm font-semibold text-gray-600 transition hover:text-ink">Lanjut belanja</a>
                    </div>
                </div>
            </section>

            {{-- Order summary --}}
            <section x-cloak x-show="order" class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 sm:p-8">
                <h2 class="text-lg font-bold">Ringkasan pesanan</h2>
                <ul class="mt-5 divide-y divide-gray-100">
                    <template x-for="(item, i) in order?.items ?? []" :key="i">
                        <li class="flex items-center gap-4 py-3">
                            <span class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-ink-soft">
                                <template x-if="item.image_url"><img :src="item.image_url" alt="" class="h-full w-full object-cover"></template>
                            </span>
                            <span class="min-w-0 flex-1 text-sm">
                                <span class="block font-semibold" x-text="item.product_name"></span>
                                <span class="block text-gray-500"><span x-text="item.variant_name ? item.variant_name + ' · ' : ''"></span><span x-text="item.qty + ' × ' + $format.formatRupiah(item.price)"></span></span>
                            </span>
                            <span class="text-sm font-semibold tabular-nums" x-text="$format.formatRupiah(item.subtotal)"></span>
                        </li>
                    </template>
                </ul>

                <div class="mt-4 grid gap-6 border-t border-gray-100 pt-5 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">Dikirim ke</p>
                        <p class="mt-2 font-semibold" x-text="order?.shipping_address.recipient_name + ' · ' + order?.shipping_address.phone"></p>
                        <p class="mt-1 leading-relaxed text-gray-600" x-text="order ? `${order.shipping_address.address_line}, ${order.shipping_address.subdistrict}, ${order.shipping_address.district}, ${order.shipping_address.city} ${order.shipping_address.post_code}` : ''"></p>
                        <p class="mt-2 text-gray-600"><span class="font-semibold text-ink" x-text="order?.shipping.service_name"></span> · estimasi <span x-text="order?.shipping.etd_text"></span></p>
                    </div>
                    <dl class="space-y-2">
                        <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd class="tabular-nums" x-text="$format.formatRupiah(order?.amounts.subtotal)"></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Ongkos kirim</dt><dd class="tabular-nums" x-text="$format.formatRupiah(order?.amounts.shipping_cost)"></dd></div>
                        <div x-show="order?.amounts.insurance_cost > 0" class="flex justify-between"><dt class="text-gray-500">Asuransi pengiriman</dt><dd class="tabular-nums" x-text="$format.formatRupiah(order?.amounts.insurance_cost)"></dd></div>
                        <div class="flex justify-between border-t border-gray-100 pt-2 text-base font-bold"><dt>Total</dt><dd class="tabular-nums" x-text="$format.formatRupiah(order?.amounts.grand_total)"></dd></div>
                    </dl>
                </div>
            </section>
        </div>
    </div>
@endsection
