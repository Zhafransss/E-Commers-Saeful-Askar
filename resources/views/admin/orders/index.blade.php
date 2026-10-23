@extends('layouts.admin')

@section('title', 'Pesanan')

{{-- ADM-09 list, ADM-10 detail panel, ADM-11 resend to Jubelio, ADM-12 re-check payment. Behaviour: resources/js/components/adminOrders.js --}}
@section('content')
    <div x-data="adminOrders" @keydown.escape.window="close()">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Pesanan</h1>
                <p class="mt-1 text-sm text-gray-500"><span x-text="meta.total"></span> pesanan, yang terbaru di atas.</p>
            </div>
            <button type="button" @click="refreshAllPayments()" :disabled="bulkBusy"
                    class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40" x-text="bulkBusy ? 'Memeriksa pembayaran…' : 'Periksa pembayaran'"></button>
        </div>
        <p class="mt-2 text-xs text-gray-500">Tombol <span class="font-semibold">Periksa pembayaran</span> menanyakan ke DANA status semua pesanan yang masih menunggu pembayaran dan memperbaruinya (dibayar atau kedaluwarsa).</p>
        <p x-cloak x-show="bulkNotice" class="mt-3 rounded-lg bg-green-50 p-3 text-sm text-green-700" x-text="bulkNotice"></p>
        <p x-cloak x-show="bulkError" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700" x-text="bulkError"></p>

        {{-- Filters --}}
        <form class="mt-4 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6" @submit.prevent="fetchPage(1)">
            <label class="text-xs font-semibold text-gray-500 lg:col-span-2">Cari
                <input type="search" x-model="filters.q" @input="search()" placeholder="No. pesanan, nama, email, HP, resi"
                       class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
            </label>
            <label class="text-xs font-semibold text-gray-500">Status
                <select x-model="filters.status" @change="fetchPage(1)" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                    <option value="">Semua</option>
                    <template x-for="[value, label] in statuses" :key="value"><option :value="value" x-text="label"></option></template>
                </select>
            </label>
            <label class="text-xs font-semibold text-gray-500">Status Jubelio
                <select x-model="filters.jubelio_sync_status" @change="fetchPage(1)" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
                    <option value="">Semua</option>
                    <option value="pending">Menunggu</option>
                    <option value="synced">Tersinkron</option>
                    <option value="failed">Gagal</option>
                </select>
            </label>
            <label class="text-xs font-semibold text-gray-500">Dari tanggal
                <input type="date" x-model="filters.date_from" @change="fetchPage(1)" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
            </label>
            <label class="text-xs font-semibold text-gray-500">Sampai tanggal
                <input type="date" x-model="filters.date_to" @change="fetchPage(1)" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-normal text-gray-900">
            </label>
            <div class="sm:col-span-2 lg:col-span-6">
                <button type="button" @click="reset()" class="text-sm font-semibold text-gray-500 underline hover:text-gray-900">Reset filter</button>
            </div>
        </form>

        <div x-cloak x-show="error" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            <span x-text="error"></span> <button type="button" class="ml-2 font-semibold underline" @click="fetchPage(1)">Coba lagi</button>
        </div>

        {{-- List --}}
        <div class="mt-4 hidden overflow-x-auto rounded-xl border border-gray-200 bg-white xl:block">
            <table class="w-full min-w-[56rem] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Pesanan</th><th class="px-4 py-3">Pelanggan</th><th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Bayar</th><th class="px-4 py-3">Jubelio</th><th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr x-show="loading"><td colspan="6" class="px-4 py-8 text-center text-gray-500">Memuat…</td></tr>
                    <tr x-cloak x-show="!loading && orders.length === 0"><td colspan="6" class="px-4 py-8 text-center text-gray-500">Tidak ada pesanan yang cocok.</td></tr>
                    <template x-for="order in orders" :key="order.order_number">
                        <tr class="cursor-pointer hover:bg-gray-50" :class="detail?.order_number === order.order_number && 'bg-yellow-50'" @click="open(order.order_number)">
                            <td class="px-4 py-3">
                                <span class="block font-mono font-semibold" x-text="order.order_number"></span>
                                <span class="block text-xs text-gray-500" x-text="date(order.created_at)"></span>
                                <span x-show="order.is_checkout_failed" class="mt-1 inline-block rounded bg-red-100 px-1.5 py-0.5 text-[11px] font-semibold text-red-700">Checkout gagal</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="block font-medium" x-text="order.customer.name"></span>
                                <span class="block text-xs text-gray-500" x-text="order.customer.email"></span>
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="badge(order.status)" x-text="order.status_label"></span></td>
                            <td class="px-4 py-3 text-xs text-gray-600" x-text="order.payment_status ?? '-'"></td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="badge(order.jubelio_sync_status)" x-text="order.jubelio_sync_status"></span>
                                <span class="mt-1 block text-xs text-gray-500" x-text="order.jubelio_salesorder_no"></span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums" x-text="$format.formatRupiah(order.grand_total)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Phone and tablet: one card per order, so nothing hides behind a sideways scroll --}}
        <div class="mt-4 space-y-3 xl:hidden">
            <p x-show="loading" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Memuat…</p>
            <p x-cloak x-show="!loading && orders.length === 0" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500">Tidak ada pesanan yang cocok.</p>
            <template x-for="order in orders" :key="order.order_number">
                <button type="button" @click="open(order.order_number)" class="block w-full rounded-xl border border-gray-200 bg-white p-4 text-left"
                        :class="detail?.order_number === order.order_number && 'bg-yellow-50'">
                    <span class="flex items-start justify-between gap-3">
                        <span>
                            <span class="block font-mono font-semibold" x-text="order.order_number"></span>
                            <span class="block text-xs text-gray-500" x-text="date(order.created_at)"></span>
                        </span>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="badge(order.status)" x-text="order.status_label"></span>
                    </span>
                    <span class="mt-3 flex items-end justify-between gap-3">
                        <span class="min-w-0">
                            <span class="block truncate font-medium" x-text="order.customer.name"></span>
                            <span class="block truncate text-xs text-gray-500" x-text="order.customer.email"></span>
                        </span>
                        <span class="shrink-0 font-semibold tabular-nums" x-text="$format.formatRupiah(order.grand_total)"></span>
                    </span>
                    <span class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                        <span x-show="order.is_checkout_failed" class="rounded bg-red-100 px-1.5 py-0.5 font-semibold text-red-700">Checkout gagal</span>
                        <span class="text-gray-500">Bayar: <span x-text="order.payment_status ?? '-'"></span></span>
                        <span class="rounded-full px-2 py-0.5 font-semibold" :class="badge(order.jubelio_sync_status)" x-text="order.jubelio_sync_status"></span>
                        <span class="text-gray-500" x-text="order.jubelio_salesorder_no"></span>
                    </span>
                </button>
            </template>
        </div>

        <div x-cloak x-show="meta.last_page > 1" class="mt-4 flex items-center justify-between gap-2 text-sm">
            <button type="button" :disabled="meta.current_page <= 1" @click="fetchPage(meta.current_page - 1)" class="shrink-0 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-3 py-2 font-semibold disabled:opacity-40 sm:px-4"><span aria-hidden="true">←</span><span class="sr-only min-[400px]:not-sr-only"> Sebelumnya</span></button>
            <span class="min-w-0 text-center text-gray-500"><span class="hidden min-[400px]:inline">Halaman </span><span x-text="meta.current_page"></span><span class="min-[400px]:hidden"> / </span><span class="hidden min-[400px]:inline"> dari </span><span x-text="meta.last_page"></span></span>
            <button type="button" :disabled="!hasMore" @click="fetchPage(meta.current_page + 1)" class="shrink-0 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-3 py-2 font-semibold disabled:opacity-40 sm:px-4"><span class="sr-only min-[400px]:not-sr-only">Berikutnya </span><span aria-hidden="true">→</span></button>
        </div>

        {{-- Detail panel --}}
        <div x-cloak x-show="detail || detailLoading || detailError" class="fixed inset-0 z-40 flex justify-end bg-black/30" @click.self="close()">
            <aside class="h-full w-full max-w-xl overflow-y-auto bg-white p-5 shadow-xl sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-mono text-lg font-bold" x-text="detail?.order_number ?? 'Detail pesanan'"></h2>
                    <button type="button" @click="close()" class="rounded-lg px-2 py-1 text-sm font-semibold text-gray-500 hover:bg-gray-100" aria-label="Tutup">Tutup ✕</button>
                </div>

                <p x-show="detailLoading" class="mt-6 text-gray-500">Memuat…</p>
                <p x-cloak x-show="detailError" class="mt-6 rounded-lg bg-red-50 p-3 text-sm text-red-700" x-text="detailError"></p>

                <template x-if="detail">
                    <div class="mt-4 space-y-5 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="badge(detail.status)" x-text="detail.status_label"></span>
                            <span x-show="detail.is_checkout_failed" class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">Checkout gagal</span>
                            <span class="text-gray-500" x-text="date(detail.created_at)"></span>
                        </div>

                        {{-- Actions --}}
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tindakan</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button type="button" @click="refreshPayment()" :disabled="busy || !detail.integration.gapura_reference_no"
                                        class="rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white disabled:opacity-40">Periksa pembayaran (DANA)</button>
                                <button type="button" @click="pushJubelio()" :disabled="busy || !canResend"
                                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold disabled:opacity-40">Kirim ulang ke Jubelio</button>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <a :href="invoiceUrl(detail.order_number)" target="_blank" rel="noopener"
                                   class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold hover:bg-gray-100">Lihat invoice (PDF)</a>
                                <a :href="invoiceUrl(detail.order_number, true)"
                                   class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold hover:bg-gray-100">Unduh invoice</a>
                                <button type="button" @click="sendInvoice()" :disabled="busy || !canSendInvoice"
                                        :title="canSendInvoice ? 'Kirim invoice PDF ke email pelanggan' : 'Hanya untuk pesanan yang sudah dibayar'"
                                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold hover:bg-gray-100 disabled:opacity-40">Kirim invoice ke email</button>
                            </div>
                            <p x-cloak x-show="notice" class="mt-2 rounded bg-green-50 p-2 text-green-700" x-text="notice"></p>
                            <p x-cloak x-show="actionError" class="mt-2 rounded bg-red-50 p-2 text-red-700" x-text="actionError"></p>
                        </div>

                        <section>
                            <h3 class="font-semibold">Pelanggan</h3>
                            <p class="mt-1" x-text="detail.customer.name"></p>
                            <p class="text-gray-500" x-text="detail.customer.email + ' · ' + detail.customer.phone"></p>
                        </section>

                        <section>
                            <h3 class="font-semibold">Produk</h3>
                            <ul class="mt-1 divide-y divide-gray-100">
                                <template x-for="item in detail.items" :key="item.id">
                                    <li class="flex justify-between gap-3 py-2">
                                        <span><span class="block font-medium" x-text="item.product_name + (item.variant_name ? ' - ' + item.variant_name : '')"></span>
                                            <span class="block text-xs text-gray-500" x-text="item.sku + ' · ' + item.qty + ' × ' + $format.formatRupiah(item.price)"></span></span>
                                        <span class="tabular-nums" x-text="$format.formatRupiah(item.subtotal)"></span>
                                    </li>
                                </template>
                            </ul>
                            <dl class="mt-2 space-y-1 border-t border-gray-100 pt-2">
                                <div class="flex justify-between"><dt class="text-gray-500">Ongkos kirim</dt><dd class="tabular-nums" x-text="$format.formatRupiah(detail.amounts.shipping_cost + detail.amounts.insurance_cost)"></dd></div>
                                <div class="flex justify-between font-bold"><dt>Total</dt><dd class="tabular-nums" x-text="$format.formatRupiah(detail.amounts.grand_total)"></dd></div>
                            </dl>
                        </section>

                        <section>
                            <h3 class="font-semibold">Pengiriman</h3>
                            <p class="mt-1" x-text="detail.shipping_address.recipient_name + ' · ' + detail.shipping_address.phone"></p>
                            <p class="text-gray-500" x-text="`${detail.shipping_address.address_line}, ${detail.shipping_address.subdistrict}, ${detail.shipping_address.district}, ${detail.shipping_address.city}, ${detail.shipping_address.province} ${detail.shipping_address.post_code}`"></p>
                            <p class="mt-1 text-gray-500" x-text="detail.shipping.service_name + ' · resi: ' + (detail.shipping.tracking_no ?? 'belum ada')"></p>
                            <p x-show="detail.note" class="mt-1 text-gray-500" x-text="'Catatan: ' + detail.note"></p>
                        </section>

                        <section>
                            <h3 class="font-semibold">Pembayaran</h3>
                            <p class="mt-1 text-gray-500">
                                <span x-text="detail.payment.status ?? '-'"></span>
                                <span x-show="detail.payment.payment_channel" x-text="' · ' + detail.payment.payment_channel"></span>
                                <span x-show="detail.payment.paid_at" x-text="' · dibayar ' + date(detail.payment.paid_at)"></span>
                            </p>
                            <p class="text-gray-500" x-show="detail.payment.expires_at" x-text="'Batas bayar: ' + date(detail.payment.expires_at)"></p>
                            <p class="mt-1 break-all font-mono text-xs text-gray-400" x-text="detail.integration.gapura_reference_no"></p>
                        </section>

                        <section>
                            <h3 class="font-semibold">Jubelio</h3>
                            <p class="mt-1">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="badge(detail.integration.jubelio_sync_status)" x-text="detail.integration.jubelio_sync_status"></span>
                                <span class="ml-2 text-gray-500" x-text="detail.integration.jubelio_salesorder_no ?? (detail.integration.jubelio_salesorder_id ? 'ID ' + detail.integration.jubelio_salesorder_id : 'Belum ada sales order')"></span>
                            </p>
                            <p x-show="detail.integration.jubelio_last_error" class="mt-2 break-words rounded bg-red-50 p-2 text-xs text-red-700" x-text="detail.integration.jubelio_last_error"></p>
                            <ul class="mt-2 space-y-1">
                                <template x-for="log in detail.integration.sync_logs" :key="log.id">
                                    <li class="flex items-start justify-between gap-2 text-xs">
                                        <span><span class="font-medium" x-text="log.type"></span> <span class="text-gray-500" x-text="date(log.started_at)"></span>
                                            <span x-show="log.message" class="block text-red-600" x-text="log.message"></span></span>
                                        <span class="rounded-full px-2 py-0.5 font-semibold" :class="badge(log.status)" x-text="log.status"></span>
                                    </li>
                                </template>
                            </ul>
                        </section>

                        <section>
                            <h3 class="font-semibold">Riwayat status</h3>
                            <ol class="mt-1 space-y-1">
                                <template x-for="(h, i) in detail.status_history" :key="i">
                                    <li class="flex justify-between gap-2 text-xs"><span><span class="font-medium" x-text="h.label"></span> <span class="text-gray-500" x-text="'· ' + h.source"></span>
                                        <span x-show="h.note" class="block text-gray-500" x-text="h.note"></span></span><span class="text-gray-500" x-text="date(h.at)"></span></li>
                                </template>
                            </ol>
                        </section>
                    </div>
                </template>
            </aside>
        </div>
    </div>
@endsection
