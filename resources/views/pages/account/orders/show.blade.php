@extends('layouts.app')

@section('title', 'Detail Pesanan')
@section('robots', 'noindex, nofollow')

{{-- ORD-03 detail, ORD-04 "Bayar sekarang", ORD-05 "Batalkan pesanan", ORD-06 "Cek status pembayaran". Behaviour: resources/js/components/orderPage.js --}}
@section('content')
    <div x-data="orderPage(@js($orderNumber), @js(config('shop.whatsapp')))" class="bg-gray-50">
        <div class="mx-auto max-w-4xl px-4 py-10 md:py-14">
            <a href="{{ route('account.orders') }}" class="text-sm font-semibold text-gray-500 hover:text-ink">← Semua pesanan</a>

            <p x-show="loading" class="mt-10 text-center text-gray-500">Memuat pesanan…</p>

            <div x-cloak x-show="notFound" class="mt-10 rounded-3xl border border-gray-200 bg-white p-10 text-center">
                <h1 class="text-2xl font-bold">Pesanan tidak ditemukan</h1>
                <p class="mt-2 text-gray-600">Nomor <span class="font-mono" x-text="orderNumber"></span> tidak ada atau bukan milik akun ini.</p>
            </div>

            <p x-cloak x-show="error" class="mt-10 rounded-2xl bg-red-50 p-4 text-sm text-red-700" x-text="error"></p>

            <template x-if="order">
                <div class="mt-4 space-y-6">
                    <section class="rounded-3xl border border-gray-200 bg-white p-6 sm:p-8">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">Nomor pesanan</p>
                                <h1 class="mt-1 font-mono text-2xl font-bold" x-text="order.order_number"></h1>
                                <p class="mt-1 text-sm text-gray-500" x-text="'Dibuat ' + new Date(order.created_at).toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' })"></p>
                            </div>
                            <span class="rounded-full px-4 py-1.5 text-sm font-semibold" :class="badge" x-text="order.status_label"></span>
                        </div>

                        <div x-show="remaining" class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-ink px-6 py-4 text-white">
                            <div>
                                <p class="text-xs uppercase tracking-widest text-gray-400">Sisa waktu bayar</p>
                                <p class="mt-1 font-mono text-3xl font-bold tabular-nums text-brand" x-text="remaining"></p>
                            </div>
                            <button type="button" x-show="order.can_pay" @click="pay()" :disabled="busy"
                                    class="rounded-full bg-brand px-7 py-3.5 text-sm font-semibold text-ink transition hover:bg-brand-hover disabled:opacity-60">Bayar sekarang</button>
                        </div>

                        {{-- Paid but still waiting: ask DANA now (ORD-06), or report it to the admin on WhatsApp. --}}
                        <div x-show="order.status === 'pending_payment'">
                            <div class="mt-5 rounded-2xl border border-gray-200 bg-gray-50 p-5">
                            <p class="text-sm font-semibold">Sudah membayar, tetapi status belum berubah?</p>
                            <p class="mt-1 text-sm text-gray-600">Periksa langsung ke DANA. Bila masih belum berubah, hubungi admin; pesannya sudah kami isi.</p>
                            <div class="mt-3 flex flex-wrap  gap-3">
                                <button type="button" @click="checkPayment()" :disabled="checkingPayment"
                                        class="inline-flex items-center gap-2 rounded-full border-2 border-ink px-5 py-2.5 text-sm font-semibold transition hover:bg-ink hover:text-brand disabled:opacity-60">
                                    <svg class="h-4 w-4" :class="checkingPayment && 'animate-spin'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 0 1-15.5 6.2L3 16M3 12a9 9 0 0 1 15.5-6.2L21 8M21 3v5h-5M3 21v-5h5" /></svg>
                                    <span x-text="checkingPayment ? 'Memeriksa…' : 'Cek status pembayaran'"></span>
                                </button>
                                <a :href="reportUrl()" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-2 rounded-full bg-[#25D366] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1ebe5b]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.3.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.2-.2-.5-.3Z" /></svg>
                                    Hubungi admin
                                </a>
                            </div>
                            <p x-cloak x-show="paymentNotice" class="mt-3 rounded-xl p-3 text-sm" :class="paymentNoticeError ? 'bg-yellow-50 text-yellow-800' : 'bg-green-50 text-green-700'" x-text="paymentNotice"></p>
                        </div>
                        </div>

                        <p x-cloak x-show="actionError" class="mt-4 rounded-2xl bg-red-50 p-3 text-sm text-red-700" x-text="actionError"></p>

                        <div x-show="order.can_cancel" class="mt-5">
                            <button type="button" x-show="!confirmingCancel" @click="confirmingCancel = true" class="text-sm font-semibold text-red-600 hover:underline">Batalkan pesanan</button>
                            <div x-cloak x-show="confirmingCancel" class="rounded-2xl border border-red-200 bg-red-50 p-4">
                                <label class="text-sm font-semibold" for="reason">Alasan pembatalan (opsional)</label>
                                <input id="reason" type="text" maxlength="255" x-model="reason" class="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
                                <div class="mt-3 flex gap-3">
                                    <button type="button" @click="cancel()" :disabled="busy" class="rounded-full bg-red-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-60">Ya, batalkan</button>
                                    <button type="button" @click="confirmingCancel = false" class="rounded-full px-5 py-2.5 text-sm font-semibold text-gray-600">Tidak</button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-gray-200 bg-white p-6 sm:p-8">
                        <h2 class="text-lg font-bold">Produk</h2>
                        <ul class="mt-4 divide-y divide-gray-100">
                            <template x-for="item in order.items" :key="item.sku">
                                <li class="flex items-center gap-4 py-3">
                                    <span class="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-ink-soft">
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
                        <dl class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd class="tabular-nums" x-text="$format.formatRupiah(order.amounts.subtotal)"></dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Ongkos kirim</dt><dd class="tabular-nums" x-text="$format.formatRupiah(order.amounts.shipping_cost)"></dd></div>
                            <div x-show="order.amounts.insurance_cost > 0" class="flex justify-between"><dt class="text-gray-500">Asuransi pengiriman</dt><dd class="tabular-nums" x-text="$format.formatRupiah(order.amounts.insurance_cost)"></dd></div>
                            <div x-show="order.amounts.discount > 0" class="flex justify-between"><dt class="text-gray-500">Voucher ongkir <span class="block text-xs" x-text="order.amounts.shipping_voucher_name ?? ''"></span></dt><dd class="tabular-nums text-green-700" x-text="'−' + $format.formatRupiah(order.amounts.discount)"></dd></div>
                            <div class="flex justify-between border-t border-gray-100 pt-2 text-base font-bold"><dt>Total</dt><dd class="tabular-nums" x-text="$format.formatRupiah(order.amounts.grand_total)"></dd></div>
                        </dl>
                    </section>

                    <div class="grid gap-6 md:grid-cols-2">
                        <section class="rounded-3xl border border-gray-200 bg-white p-6 text-sm">
                            <h2 class="text-lg font-bold">Pengiriman</h2>
                            <p class="mt-3 font-semibold" x-text="order.shipping_address.recipient_name + ' · ' + order.shipping_address.phone"></p>
                            <p class="mt-1 leading-relaxed text-gray-600" x-text="`${order.shipping_address.address_line}, ${order.shipping_address.subdistrict}, ${order.shipping_address.district}, ${order.shipping_address.city}, ${order.shipping_address.province} ${order.shipping_address.post_code}`"></p>
                            <p class="mt-3 text-gray-600"><span class="font-semibold text-ink" x-text="order.shipping.service_name"></span> · estimasi <span x-text="order.shipping.etd_text"></span></p>
                            <p class="mt-2 text-gray-600">No. resi: <span class="font-mono font-semibold text-ink" x-text="order.shipping.tracking_no ?? 'Belum tersedia'"></span></p>
                            <p x-show="order.note" class="mt-3 text-gray-600">Catatan: <span x-text="order.note"></span></p>
                        </section>

                        <section class="rounded-3xl border border-gray-200 bg-white p-6 text-sm">
                            <h2 class="text-lg font-bold">Riwayat status</h2>
                            <ol class="mt-3 space-y-3">
                                <template x-for="(h, i) in order.status_history" :key="i">
                                    <li class="flex gap-3">
                                        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-brand"></span>
                                        <span>
                                            <span class="block font-semibold" x-text="h.label"></span>
                                            <span class="block text-gray-500" x-text="new Date(h.at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })"></span>
                                        </span>
                                    </li>
                                </template>
                            </ol>
                            <p x-show="order.payment.payment_channel" class="mt-4 border-t border-gray-100 pt-3 text-gray-600">Dibayar via <span class="font-semibold text-ink" x-text="order.payment.payment_channel"></span></p>
                        </section>
                    </div>
                </div>
            </template>
        </div>
    </div>
@endsection
