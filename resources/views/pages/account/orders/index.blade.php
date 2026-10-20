@extends('layouts.app')

@section('title', 'Pesanan Saya')
@section('robots', 'noindex, nofollow')

{{-- ORD-02. Behaviour: resources/js/components/orderList.js --}}
@section('content')
    <div x-data="orderList" class="bg-gray-50">
        <div class="mx-auto max-w-4xl px-4 py-10 md:py-14">
            <h1 class="text-3xl font-bold">Pesanan Saya</h1>

            <div class="-mx-4 mt-6 overflow-x-auto px-4">
                <div class="flex w-max gap-2" role="tablist">
                    <template x-for="t in tabs" :key="t.key">
                        <button type="button" role="tab" @click="select(t.key)" :aria-selected="tab === t.key"
                                class="rounded-full border px-4 py-2 text-sm font-semibold transition"
                                :class="tab === t.key ? 'border-ink bg-ink text-brand' : 'border-gray-300 bg-white text-gray-600 hover:border-ink hover:text-ink'"
                                x-text="t.label"></button>
                    </template>
                </div>
            </div>

            <p x-show="loading" class="mt-10 text-center text-gray-500">Memuat pesanan…</p>

            <div x-cloak x-show="error" class="mt-6 rounded-2xl bg-red-50 p-4 text-sm text-red-700">
                <span x-text="error"></span>
                <button type="button" class="ml-2 font-semibold underline" @click="fetchPage(1)">Coba lagi</button>
            </div>

            <div x-cloak x-show="!loading && !error && orders.length === 0" class="mt-8 rounded-3xl border border-gray-200 bg-white p-10 text-center">
                <h2 class="text-lg font-bold">Belum ada pesanan</h2>
                <p class="mt-2 text-gray-600">Pesanan dengan status ini akan muncul di sini.</p>
                <a href="{{ route('products.index') }}" class="mt-5 inline-block rounded-full bg-brand px-7 py-3 text-sm font-semibold text-ink transition hover:bg-brand-hover">Mulai belanja</a>
            </div>

            <ul class="mt-6 space-y-4">
                <template x-for="order in orders" :key="order.order_number">
                    <li>
                        <a :href="'{{ url('/account/orders') }}/' + order.order_number"
                           class="flex items-center gap-4 rounded-3xl border border-gray-200 bg-white p-4 transition hover:border-ink sm:p-5">
                            <span class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-ink-soft">
                                <template x-if="order.first_item?.image_url"><img :src="order.first_item.image_url" alt="" class="h-full w-full object-cover"></template>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span class="font-mono text-sm font-bold" x-text="order.order_number"></span>
                                    <span class="rounded-full px-3 py-0.5 text-xs font-semibold" :class="badge(order.status)" x-text="order.status_label"></span>
                                </span>
                                <span class="mt-1 block truncate text-sm font-semibold" x-text="order.first_item?.name"></span>
                                <span class="block text-xs text-gray-500">
                                    <span x-show="order.item_count > 1" x-text="'+' + (order.item_count - 1) + ' produk lainnya · '"></span><span x-text="date(order.created_at)"></span>
                                </span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="block text-xs text-gray-500">Total</span>
                                <span class="block font-bold tabular-nums" x-text="$format.formatRupiah(order.grand_total)"></span>
                                <span x-show="order.status === 'pending_payment'" class="mt-1 block text-xs font-semibold text-brand-hover">Bayar sekarang →</span>
                            </span>
                        </a>
                    </li>
                </template>
            </ul>

            <div x-cloak x-show="hasMore" class="mt-6 text-center">
                <button type="button" @click="fetchPage(meta.current_page + 1)" :disabled="loadingMore"
                        class="rounded-full border-2 border-ink px-7 py-3 text-sm font-semibold transition hover:bg-ink hover:text-brand disabled:opacity-60"
                        x-text="loadingMore ? 'Memuat…' : 'Muat lebih banyak'"></button>
            </div>
        </div>
    </div>
@endsection
