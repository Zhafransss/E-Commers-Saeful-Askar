@extends('layouts.app')

{{--
    Static preview: resources/js/components/productList.js filters these cards in the
    browser. Live data: CAT-01 (categories) and CAT-02 (?q, category, in_stock,
    price_min, price_max, sort, page), the same query the component keeps in the URL.
--}}
@php
    $static = require resource_path('static/catalog.php');

    // SEO copy per category (permanent content, resources/content/categories.php).
    $content = require resource_path('content/categories.php');
    $slug = (string) request('category', '');
    $current = $content[$slug] ?? $content[''];
    $categoryName = collect($static['categories'])->firstWhere('slug', $slug)['name'] ?? null;

    $categories = collect($static['categories']);
    $normalize = fn (string $text) => trim(preg_replace('/[^a-z0-9]+/', ' ', \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($text))));

    // Search text per product: name, category (and its parent), and every variant name/SKU (CAT-02 finds exact SKUs).
    $products = collect($static['products'])->values()->map(function ($product, $position) use ($static, $categories, $normalize) {
        $category = $categories->firstWhere('id', $product['category']['id']);
        $parent = $category && $category['parent_id'] ? $categories->firstWhere('id', $category['parent_id']) : null;
        $variants = collect($static['productDetail']($product['slug'])['variants'] ?? []);

        return $product + [
            'position' => $position,
            'search' => $normalize(implode(' ', [
                $product['name'], $product['category']['name'], $parent['name'] ?? '',
                $variants->pluck('sku')->implode(' '), $variants->pluck('name')->implode(' '),
            ])),
        ];
    });

    $pricePresets = [[null, 2000000, 'Di bawah Rp 2 jt'], [2000000, 6000000, 'Rp 2 – 6 jt'], [6000000, 10000000, 'Rp 6 – 10 jt'], [10000000, null, 'Di atas Rp 10 jt']];
    $suggestions = ['Baterai 48V', 'PCSY', 'Charger', 'Ban'];
@endphp

@section('title', $current['title'])
@section('meta_description', $current['meta'])
{{-- One indexable URL per category; sort/price/stock filters point back to it, search results stay out of the index. --}}
@section('canonical', route('products.index', isset($content[$slug]) && $slug !== '' ? ['category' => $slug] : []))
@if (request()->filled('q') || ($slug !== '' && ! isset($content[$slug])))
    @section('robots', 'noindex, follow')
@endif

<x-seo-breadcrumbs :items="array_filter([
    ['Home', route('home')],
    ['Produk', route('products.index')],
    $categoryName ? [$categoryName, route('products.index', ['category' => $slug])] : null,
])" />

@section('content')
    <div x-data="productList(@js(['products' => $products, 'categories' => $categories->values(), 'content' => $content]))"
         @keydown.window.slash="if (! ['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) { $event.preventDefault(); $refs.search.focus() }"
         @keydown.escape.window="filtersOpen = false">

        {{-- Hero + search --}}
        <section class="relative overflow-hidden bg-ink text-white">
            <div class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-brand/10 blur-3xl" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-7xl px-4 pb-10 pt-8 md:px-8 md:pb-14 md:pt-10">
                <nav class="text-xs text-gray-400" aria-label="Breadcrumb">
                    <a href="{{ route('home') }}" class="hover:text-brand">Home</a>
                    <span class="mx-1.5">/</span>
                    <a href="{{ route('products.index') }}" class="hover:text-brand">Produk</a>
                    <template x-if="currentCategory">
                        <span><span class="mx-1.5">/</span><span class="text-gray-200" x-text="currentCategory.name"></span></span>
                    </template>
                </nav>
                <div class="mt-4 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-brand sm:text-4xl" x-text="pageContent.h1">{{ $current['h1'] }}</h1>
                        <p class="mt-2 max-w-lg text-gray-400" x-text="pageContent.intro">{{ $current['intro'] }}</p>
                    </div>

                    <div class="w-full lg:max-w-xl">
                        <form role="search" @submit.prevent="$refs.search.blur()" class="relative">
                            <label for="product-search" class="sr-only">Cari produk</label>
                            <svg class="pointer-events-none absolute left-5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.08 3.08a.75.75 0 1 1-1.06 1.06l-3.08-3.08A7 7 0 0 1 2 9Z" clip-rule="evenodd" /></svg>
                            <input id="product-search" x-ref="search" type="search" x-model.debounce.200ms="q" autocomplete="off"
                                   value="{{ request('q') }}" placeholder="Cari nama produk, kategori, atau SKU…"
                                   class="h-14 w-full rounded-full border border-ink-line bg-white pl-14 pr-24 text-base text-ink placeholder:text-gray-400 focus:border-brand focus:outline-none focus:ring-4 focus:ring-brand/30 [&::-webkit-search-cancel-button]:hidden">
                            <button type="button" x-cloak x-show="q" @click="q = ''; $refs.search.focus()"
                                    class="absolute right-14 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-ink" aria-label="Hapus pencarian">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                            </button>
                            <kbd class="pointer-events-none absolute right-5 top-1/2 hidden -translate-y-1/2 rounded-md border border-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-400 sm:block">/</kbd>
                        </form>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                            <span class="text-gray-500">Populer:</span>
                            @foreach ($suggestions as $suggestion)
                                <button type="button" @click="q = @js($suggestion)"
                                        class="rounded-full border border-ink-line px-3 py-1 text-gray-300 transition hover:border-brand hover:text-brand">{{ $suggestion }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Category pills --}}
        <div class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-7xl gap-2 overflow-x-auto px-4 py-3 md:px-8 [scrollbar-width:none]">
                <a href="{{ route('products.index') }}" @click.prevent="category = ''"
                        :class="category === '' ? 'border-ink bg-ink text-brand' : 'border-gray-200 bg-white text-gray-700 hover:border-ink'"
                        class="flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition">
                    Semua <span class="text-xs opacity-60" x-text="countFor('')"></span>
                </a>
                @foreach ($categories as $category)
                    <a href="{{ route('products.index', ['category' => $category['slug']]) }}" @click.prevent="category = @js($category['slug'])"
                            :class="category === @js($category['slug']) ? 'border-ink bg-ink text-brand' : 'border-gray-200 bg-white text-gray-700 hover:border-ink'"
                            class="flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition">
                        {{ $category['name'] }} <span class="text-xs opacity-60" x-text="countFor(@js($category['slug']))"></span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-8 md:px-8 md:py-10">
            <div class="grid gap-8 lg:grid-cols-[16.5rem_1fr]">
                {{-- Mobile backdrop --}}
                <div x-cloak x-show="filtersOpen" x-transition.opacity @click="filtersOpen = false" class="fixed inset-0 z-[60] bg-ink/50 backdrop-blur-sm lg:hidden"></div>

                {{-- Filters: drawer on mobile, sidebar on desktop --}}
                {{-- Closed by default in the markup, so the drawer never flashes open before Alpine starts. --}}
                <aside class="fixed inset-y-0 left-0 z-[70] flex w-[85%] max-w-sm -translate-x-full flex-col bg-white shadow-2xl transition-transform duration-300 lg:static lg:z-auto lg:w-auto lg:max-w-none lg:translate-x-0 lg:bg-transparent lg:shadow-none"
                       :class="filtersOpen && 'translate-x-0!'" aria-label="Filter produk">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 lg:hidden">
                        <p class="text-lg font-bold">Filter</p>
                        <button type="button" @click="filtersOpen = false" class="grid h-10 w-10 place-items-center rounded-full hover:bg-gray-100" aria-label="Tutup filter">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                        </button>
                    </div>

                    <div class="flex-1 space-y-4 overflow-y-auto p-5 lg:sticky lg:top-24 lg:overflow-visible lg:p-0">
                        <div class="hidden items-center justify-between lg:flex">
                            <p class="font-bold">Filter</p>
                            <button type="button" x-show="chips.length" @click="reset()" class="text-sm font-semibold text-gray-500 transition hover:text-ink">Reset</button>
                        </div>

                        <section class="rounded-2xl border border-gray-200 bg-white p-5">
                            <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500">Kategori</h2>
                            <ul class="mt-3 space-y-0.5 text-sm">
                                <li>
                                    <a href="{{ route('products.index') }}" @click.prevent="category = ''"
                                            :class="category === '' ? 'bg-ink font-semibold text-brand' : 'text-gray-700 hover:bg-gray-100'"
                                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 transition">
                                        Semua produk <span class="text-xs opacity-60" x-text="countFor('')"></span>
                                    </a>
                                </li>
                                @foreach ($categories as $category)
                                    <li>
                                        <a href="{{ route('products.index', ['category' => $category['slug']]) }}" @click.prevent="category = @js($category['slug'])"
                                                :class="category === @js($category['slug']) ? 'bg-ink font-semibold text-brand' : 'text-gray-700 hover:bg-gray-100'"
                                                @class(['flex w-full items-center justify-between rounded-lg py-2 pr-3 transition', 'pl-6' => $category['parent_id'], 'pl-3' => ! $category['parent_id']])>
                                            <span>@if ($category['parent_id'])<span class="mr-1 text-gray-300">└</span>@endif{{ $category['name'] }}</span>
                                            <span class="text-xs opacity-60" x-text="countFor(@js($category['slug']))"></span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>

                        <section class="rounded-2xl border border-gray-200 bg-white p-5">
                            <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500">Harga</h2>
                            <div class="mt-3 space-y-1 text-sm">
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg px-1 py-1.5 text-gray-700">
                                    <input type="radio" name="price_preset" class="h-4 w-4 accent-ink" :checked="isPrice(null, null)" @change="setPrice(null, null)">
                                    Semua harga
                                </label>
                                @foreach ($pricePresets as [$from, $to, $label])
                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg px-1 py-1.5 text-gray-700">
                                        <input type="radio" name="price_preset" class="h-4 w-4 accent-ink" :checked="isPrice(@js($from), @js($to))" @change="setPrice(@js($from), @js($to))">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <div class="mt-4 grid gap-2">
                                @foreach (['priceMin' => 'Harga minimum', 'priceMax' => 'Harga maksimum'] as $model => $placeholder)
                                    <label class="relative block">
                                        <span class="sr-only">{{ $placeholder }}</span>
                                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-gray-400">Rp</span>
                                        <input type="number" min="0" step="50000" inputmode="numeric" x-model.debounce.400ms="{{ $model }}" placeholder="{{ $placeholder }}"
                                               class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink">
                                    </label>
                                @endforeach
                                <p x-cloak x-show="priceInvalid" class="text-xs text-red-600">Harga maksimum tidak boleh lebih kecil dari minimum.</p>
                            </div>
                        </section>

                        <section class="rounded-2xl border border-gray-200 bg-white p-5">
                            <label class="flex cursor-pointer items-center justify-between gap-3 text-sm font-medium text-gray-700">
                                Hanya yang tersedia
                                <input type="checkbox" x-model="inStock" class="peer sr-only">
                                <span class="relative h-6 w-11 shrink-0 rounded-full bg-gray-300 transition peer-checked:bg-ink peer-focus-visible:ring-2 peer-focus-visible:ring-brand after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5 peer-checked:after:bg-brand"></span>
                            </label>
                        </section>

                        <a href="https://api.whatsapp.com/send?phone=6282227222971&text={{ rawurlencode('Halo min, saya butuh bantuan memilih produk di website.') }}" target="_blank" rel="noopener"
                           class="hidden items-center gap-3 rounded-2xl bg-ink p-5 text-sm text-white transition hover:bg-ink-soft lg:flex">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand text-ink">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z" /></svg>
                            </span>
                            <span><span class="block font-semibold">Bingung memilih?</span><span class="text-gray-400">Tanya admin via WhatsApp</span></span>
                        </a>
                    </div>

                    <div class="flex gap-3 border-t border-gray-200 p-4 lg:hidden">
                        <button type="button" @click="reset()" class="flex-1 rounded-full border border-gray-300 py-3 text-sm font-semibold">Reset</button>
                        <button type="button" @click="filtersOpen = false" class="flex-[2] rounded-full bg-ink py-3 text-sm font-semibold text-brand">
                            Tampilkan <span x-text="results.length"></span> produk
                        </button>
                    </div>
                </aside>

                {{-- Results --}}
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-gray-600" aria-live="polite">
                            <span class="font-semibold text-ink" x-text="results.length">{{ $products->count() }}</span> produk ditemukan
                        </p>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="filtersOpen = true"
                                    class="relative inline-flex h-10 items-center gap-2 rounded-full border border-gray-300 bg-white px-4 text-sm font-semibold transition hover:border-ink lg:hidden">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" /></svg>
                                Filter
                                <span x-cloak x-show="chips.length" x-text="chips.length" class="grid h-5 min-w-5 place-items-center rounded-full bg-brand px-1 text-[11px] font-bold text-ink"></span>
                            </button>
                            <label class="relative">
                                <span class="sr-only">Urutkan</span>
                                <select x-model="sort" class="h-10 appearance-none rounded-full border border-gray-300 bg-white pl-4 pr-10 text-sm font-semibold focus:border-ink focus:outline-none focus:ring-1 focus:ring-ink">
                                    <template x-for="(label, value) in sorts" :key="value">
                                        <option :value="value" x-text="'Urutkan: ' + label" :selected="value === sort"></option>
                                    </template>
                                </select>
                                <svg class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                            </label>
                        </div>
                    </div>

                    {{-- Active filters --}}
                    <div x-cloak x-show="chips.length" class="mt-4 flex flex-wrap items-center gap-2">
                        <template x-for="chip in chips" :key="chip.key">
                            <button type="button" @click="removeChip(chip.key)"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-brand-soft py-1.5 pl-3 pr-2 text-sm font-medium text-ink ring-1 ring-brand/60 transition hover:ring-ink">
                                <span x-text="chip.label"></span>
                                <svg class="h-4 w-4 opacity-60" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                            </button>
                        </template>
                        <button type="button" @click="reset()" class="px-2 text-sm font-semibold text-gray-500 underline-offset-4 hover:text-ink hover:underline">Hapus semua</button>
                    </div>

                    {{-- Grid: cards are server-rendered; Alpine shows and orders them. --}}
                    <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-6 xl:grid-cols-3">
                        @foreach ($products as $product)
                            <div x-show="rank({{ $product['id'] }}) !== -1" :style="{ order: rank({{ $product['id'] }}) }" class="flex">
                                <x-product-card :product="$product" class="w-full" />
                            </div>
                        @endforeach
                    </div>

                    {{-- Empty state --}}
                    <div x-cloak x-show="results.length === 0" class="mt-6 rounded-3xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
                        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-brand-soft text-ink">
                            <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.08 3.08a.75.75 0 1 1-1.06 1.06l-3.08-3.08A7 7 0 0 1 2 9Z" clip-rule="evenodd" /></svg>
                        </span>
                        <h2 class="mt-5 text-xl font-bold">Produk tidak ditemukan</h2>
                        <p class="mx-auto mt-2 max-w-sm text-gray-600">
                            <template x-if="q.trim()"><span>Tidak ada hasil untuk “<span class="font-semibold text-ink" x-text="q.trim()"></span>”. </span></template>
                            Coba kata kunci lain atau longgarkan filter.
                        </p>
                        <div class="mt-6 flex flex-wrap justify-center gap-3">
                            <button type="button" @click="reset()" class="rounded-full bg-ink px-6 py-3 text-sm font-semibold text-brand">Hapus semua filter</button>
                            <a href="https://api.whatsapp.com/send?phone=6282227222971&text={{ rawurlencode('Halo min, saya cari produk yang tidak ada di website.') }}" target="_blank" rel="noopener"
                               class="rounded-full border border-gray-300 px-6 py-3 text-sm font-semibold transition hover:border-ink">Tanya via WhatsApp</a>
                        </div>
                    </div>

                    {{-- Load more (CAT-02 page + 1) --}}
                    <div x-cloak x-show="results.length > 0" class="mt-10 flex flex-col items-center gap-3">
                        <p class="text-sm text-gray-500">
                            Menampilkan <span class="font-semibold text-ink" x-text="Math.min(shown, results.length)"></span> dari <span x-text="results.length"></span> produk
                        </p>
                        <div class="h-1 w-48 overflow-hidden rounded-full bg-gray-200">
                            <div class="h-full rounded-full bg-brand transition-all" :style="{ width: Math.min(100, (shown / results.length) * 100) + '%' }"></div>
                        </div>
                        <button type="button" x-show="hasMore" @click="loadMore()"
                                class="mt-2 inline-flex h-12 items-center rounded-full border-2 border-ink px-8 text-sm font-semibold transition hover:bg-ink hover:text-brand">
                            Muat lebih banyak
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Category copy for search engines and shoppers (resources/content/categories.php). --}}
        <section class="border-t border-gray-200 bg-white">
            <div class="rich-text mx-auto max-w-3xl px-4 py-14 md:px-8" x-html="pageContent.body">{!! $current['body'] !!}</div>
        </section>
    </div>
@endsection
