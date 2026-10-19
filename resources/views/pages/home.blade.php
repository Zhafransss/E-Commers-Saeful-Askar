@extends('layouts.app')

@section('title', 'Toko Sepeda Listrik Bekasi – Jual & Service')
@section('meta_description', 'Toko sepeda listrik Saeful Askar di Tambun Utara, Bekasi. Jual sepeda listrik, baterai & sparepart, plus service di rumah atau di toko. Berpengalaman 10+ tahun.')
@section('body_class', 'bg-neutral-950 text-white')

@push('meta')
    {{-- The hero photo is the largest element above the fold (LCP). --}}
    <link rel="preload" as="image" href="{{ asset('images/banner.webp') }}" fetchpriority="high">
@endpush

@include('seo.local-business')
<x-json-ld :data="[
    '@type' => 'WebSite',
    'name' => 'Saeful Askar',
    'url' => route('home'),
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('products.index').'?q={search_term_string}'],
        'query-input' => 'required name=search_term_string',
    ],
]" />

{{--
    Static preview in the saefulaskar.com style. Live data later: CAT-02 (catalog),
    ART-01 (articles). Section ids are the targets of the header menu anchors.
--}}
@php
    $static = require resource_path('static/catalog.php');
    $store = require resource_path('static/store.php');
@endphp

@section('content')
    {{-- Hero --}}
    <section id="home" class="relative flex min-h-[640px] items-end overflow-hidden lg:min-h-[min(88vh,820px)]">
        <div class="absolute inset-0">
            <img src="{{ asset('images/banner.webp') }}" alt="Toko Sepeda Listrik Saeful Askar Bekasi" class="h-full w-full object-cover" fetchpriority="high">
            <div class="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/70 to-neutral-950/40"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-neutral-950/70 to-transparent"></div>
        </div>

        <div class="relative mx-auto w-full max-w-7xl px-4 pb-20 pt-32 md:px-8 md:pb-28">
            <div class="max-w-3xl">
                <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-brand">Tambun Utara, Bekasi</p>
                <h1 class="text-4xl font-bold leading-[1.1] md:text-5xl xl:text-6xl 2xl:text-7xl">
                    Toko Sepeda Listrik <span class="text-brand">Saeful Askar</span> Bekasi
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-neutral-300">
                    Solusi kebutuhan sepeda listrik dan motor listrik Anda. Berpengalaman lebih dari 10 tahun melayani area Jabodetabek.
                </p>
                <div class="mt-10 flex flex-wrap gap-4">
                    <a href="{{ route('products.index') }}"
                       class="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-brand px-7 text-sm font-semibold text-ink transition hover:scale-[1.02] hover:bg-brand/90 active:scale-[0.98]">
                        Belanja Sekarang
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-3.22-3.22a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.22-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
                    </a>
                    <a href="{{ $store['wa']['partner'] }}" target="_blank" rel="noopener"
                       class="inline-flex h-12 items-center justify-center rounded-full border border-neutral-700 px-7 text-sm font-semibold transition hover:scale-[1.02] hover:border-neutral-500 hover:bg-neutral-800/50 active:scale-[0.98]">
                        Gabung Sebagai Partner
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Value props --}}
    <section class="border-y border-neutral-800/50">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <div class="grid divide-y divide-neutral-800/50 md:grid-cols-3 md:divide-x md:divide-y-0">
                @foreach ([
                    ['Harga Terbaik', 'Tetap berkualitas dengan harga bersaing', 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
                    ['Service Cepat dan Tepat', 'Pelayanan yang cepat dengan aksi yang tepat', 'M22 11.1V12a10 10 0 1 1-5.9-9.1M22 4 12 14l-3-3'],
                    ['Harga Spesial Partner', 'Partner bisnis lebih baik', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8'],
                ] as [$title, $text, $icon])
                    <div class="flex items-start gap-4 py-8 md:px-6 md:first:pl-0 md:last:pr-0">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}" /></svg>
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ $title }}</h3>
                            <p class="mt-1 text-sm text-neutral-400">{{ $text }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Brand partners --}}
    <section class="border-b border-neutral-800/50">
        <div class="mx-auto max-w-7xl px-4 py-8 md:px-8">
            <p class="mb-6 text-center text-xs font-semibold uppercase tracking-widest text-neutral-500">Brand Partner</p>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach (['chilwee' => 'Chilwee', 'haha' => 'Haha', 'selis' => 'Selis', 'tianeng' => 'Tianeng'] as $file => $brand)
                    <div class="flex items-center justify-center rounded-xl border border-neutral-800/50 bg-neutral-900/30 p-4 transition-colors hover:bg-neutral-900/60">
                        <img src="{{ asset("images/clients/{$file}.png") }}" alt="{{ $brand }}" loading="lazy" width="80" height="40"
                             class="h-10 w-auto object-contain opacity-60 transition-opacity hover:opacity-100">
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Product categories (same three entries as the "Product" dropdown) --}}
    <section class="py-20 md:py-28">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <x-section-heading eyebrow="Produk & Layanan">
                Semua kebutuhan <span class="text-brand">sepeda listrik</span>
            </x-section-heading>

            <div class="grid gap-4 md:grid-cols-3 md:gap-6">
                @foreach ([
                    ['Sepeda Listrik', 'Sepeda dan motor listrik untuk anak-anak hingga dewasa.', route('products.index', ['category' => 'sepeda-listrik']), asset('images/products/2-640.webp'), '18+ model'],
                    ['Sparepart', 'Baterai, charger, ban, dan suku cadang. Grosir & eceran.', route('products.index', ['category' => 'sparepart']), asset('images/products/6-640.webp'), 'Grosir & eceran'],
                    ['Jasa Service', 'Teknisi berpengalaman, service di rumah atau di toko.', route('service'), asset('images/banner.webp'), 'Di rumah / di toko'],
                ] as [$title, $text, $url, $image, $tag])
                    <a href="{{ $url }}" class="group relative flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-2xl border border-neutral-800 p-6 transition-all duration-300 hover:-translate-y-1 hover:border-neutral-600 sm:aspect-[4/3] md:aspect-[3/4] lg:aspect-[4/5]">
                        <img src="{{ $image }}" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                        <div class="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/60 to-transparent"></div>
                        <span class="absolute left-6 top-6 rounded-full border border-neutral-700 bg-neutral-950/70 px-3 py-1 text-xs font-semibold text-neutral-200 backdrop-blur">{{ $tag }}</span>
                        <div class="relative">
                            <h3 class="text-2xl font-bold">{{ $title }}</h3>
                            <p class="mt-2 text-sm text-neutral-300">{{ $text }}</p>
                            <span class="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-brand">
                                Lihat {{ strtolower($title) }}
                                <svg class="h-4 w-4 transition group-hover:translate-x-1" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-3.22-3.22a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.22-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Promo --}}
    <section id="promo" class="border-y border-neutral-800/50 py-20 md:py-28">
        <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 md:grid-cols-2 md:px-8">
            <div>
                <div class="mb-6 flex h-10 w-10 items-center justify-center rounded-xl bg-accent/10 text-accent">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8ZM7 7h.01" /></svg>
                </div>
                <h2 class="mb-4 text-3xl font-bold md:text-4xl">Promo Terbaru</h2>
                <div class="space-y-3 text-neutral-300">
                    <p>Belanja berdua ternyata lebih <span class="font-semibold text-brand">muraah!</span></p>
                    <p>Dapatkan diskon sebesar <span class="text-lg font-bold text-brand">Rp 800.000</span> untuk semua varian sepeda listrik untuk setiap pembelian 2 unit sepeda listrik.</p>
                    <p>Yuk, ajak saudara dan tetanggamu beli barengan dan dapatkan <span class="font-semibold text-brand">diskonnya!</span></p>
                </div>
                <a href="{{ route('products.index', ['category' => 'sepeda-listrik']) }}"
                   class="mt-8 inline-flex h-12 items-center justify-center rounded-full bg-brand px-7 text-sm font-semibold text-ink transition hover:scale-[1.02] hover:bg-brand/90">
                    Pilih Sepeda Listrik
                </a>
            </div>
            <div class="overflow-hidden rounded-2xl border border-neutral-800">
                <img src="{{ asset('images/promo.webp') }}" alt="Promo sepeda listrik Saeful Askar — beli 2 diskon Rp 800.000" loading="lazy" width="600" height="400" class="h-auto w-full">
            </div>
        </div>
    </section>

    {{-- Catalog --}}
    <section id="katalog" class="scroll-mt-20 py-20 md:py-28">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <x-section-heading eyebrow="Katalog Produk">
                Belanja Mudah dan Aman
                <x-slot:intro>Kami menyediakan produk dengan kualitas dan harga terbaik. Stok selalu sesuai gudang.</x-slot:intro>
            </x-section-heading>

            {{-- Production: the first 8 products from CAT-02 (same card as the product list). --}}
            <div class="grid grid-cols-2 gap-4 md:gap-6 lg:grid-cols-4">
                @foreach (array_slice($static['products'], 0, 8) as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>

            <div class="mt-10 text-center">
                <a href="{{ route('products.index') }}"
                   class="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-neutral-700 px-7 text-sm font-semibold transition hover:scale-[1.02] hover:border-neutral-500 hover:bg-neutral-800/50">
                    Lihat Semua Produk
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-3.22-3.22a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.22-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
                </a>
            </div>
        </div>
    </section>

    {{-- Sparepart --}}
    <section id="sparepart" class="scroll-mt-20 border-t border-neutral-800/50 py-20 md:py-28">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <x-section-heading>
                Cari <span class="text-brand">Sparepart?</span>
                <x-slot:intro>Suku cadang lengkap untuk sepeda listrik dan motor listrik. Beli langsung di website atau di marketplace favoritmu.</x-slot:intro>
            </x-section-heading>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <a href="{{ route('products.index', ['category' => 'sparepart']) }}"
                   class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-brand/40 bg-brand/10 p-6 text-center transition-all duration-300 hover:-translate-y-1 hover:border-brand">
                    <span class="font-logo text-lg font-bold">Saeful <span class="text-brand">Askar</span></span>
                    <span class="text-sm font-semibold text-brand">Belanja di website</span>
                </a>
                @foreach ($store['marketplaces'] as $marketplace)
                    <a href="{{ $marketplace['url'] }}" target="_blank" rel="noopener"
                       class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-neutral-800 bg-neutral-900 p-6 transition-all duration-300 hover:-translate-y-1 hover:border-neutral-600">
                        {{-- Light plate keeps dark logos (Lazada) readable on the dark card. --}}
                        <span class="rounded-xl bg-white px-4 py-2">
                            <img src="{{ asset($marketplace['logo']) }}" alt="{{ $marketplace['name'] }}" loading="lazy" class="h-12 w-auto object-contain">
                        </span>
                    </a>
                @endforeach
            </div>

            <p class="mt-6 text-center text-neutral-400">
                Ingin belanja grosir <span class="font-semibold text-brand">lebih murah?</span> Hubungi kami via WhatsApp klik
                <a href="{{ $store['wa']['wholesale'] }}" target="_blank" rel="noopener" class="font-semibold text-brand underline underline-offset-4 transition-colors hover:text-accent">di sini!</a>
            </p>
        </div>
    </section>

    {{-- Service preview (full page: /jasa-service) --}}
    <section class="border-t border-neutral-800/50 py-20 md:py-28">
        <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 md:px-8 lg:grid-cols-[1fr_1.2fr]">
            <div>
                <x-section-heading eyebrow="Layanan Kami" :center="false" flush>
                    Jasa <span class="text-brand">Service</span>
                    <x-slot:intro>Teknisi berpengalaman lebih dari 10 tahun. Pilih service di rumah atau bawa langsung ke toko.</x-slot:intro>
                </x-section-heading>
                <a href="{{ route('service') }}"
                   class="mt-8 inline-flex h-12 items-center justify-center gap-2 rounded-full bg-brand px-7 text-sm font-semibold text-ink transition hover:scale-[1.02] hover:bg-brand/90">
                    Lihat Jasa Service
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-3.22-3.22a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.22-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
                </a>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($store['services'] as $service)
                    <a href="{{ route('service') }}" class="group flex flex-col gap-4 rounded-2xl border border-neutral-800 bg-neutral-900 p-6 transition hover:-translate-y-1 hover:border-neutral-600">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand/10 text-brand transition group-hover:bg-brand group-hover:text-ink">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $service['icon'] }}" /></svg>
                        </span>
                        <span class="text-lg font-bold">{{ $service['title'] }}</span>
                        <span class="text-sm text-neutral-400">{{ implode(' · ', $service['points']) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- About preview (full page: /tentang-kami) --}}
    <section class="border-t border-neutral-800/50 py-20 md:py-28">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 md:px-8 lg:grid-cols-2">
            <div class="relative">
                <div class="overflow-hidden rounded-3xl border border-neutral-800">
                    <img src="{{ asset('images/products/1-640.webp') }}" alt="Sepeda listrik di toko Saeful Askar" loading="lazy" class="aspect-[4/3] w-full object-cover">
                </div>
                <div class="absolute -bottom-6 right-6 rounded-2xl bg-brand px-6 py-4 text-ink shadow-2xl shadow-black/40">
                    <p class="text-3xl font-extrabold leading-none">10+</p>
                    <p class="text-xs font-semibold uppercase tracking-wider">Tahun melayani</p>
                </div>
            </div>

            <div>
                <x-section-heading eyebrow="Tentang Kami" :center="false" flush class="mb-6">
                    Tentang <span class="text-brand">Saeful Askar</span>
                </x-section-heading>
                <p class="leading-relaxed text-neutral-300">
                    Saeful Askar adalah toko sepeda listrik dan motor listrik yang berlokasi di Bekasi, Jawa Barat. Dengan pengalaman lebih dari 10 tahun, kami menyediakan produk berkualitas dengan harga bersaing.
                </p>
                <dl class="mt-8 grid grid-cols-3 gap-4">
                    @foreach ($store['stats'] as [$value, $label])
                        <div class="rounded-2xl border border-neutral-800 bg-neutral-900 p-4 sm:p-5">
                            <dt class="sr-only">{{ $label }}</dt>
                            <dd class="text-2xl font-extrabold text-brand sm:text-3xl">{{ $value }}</dd>
                            <dd class="mt-1 text-xs text-neutral-400 sm:text-sm">{{ $label }}</dd>
                        </div>
                    @endforeach
                </dl>
                <a href="{{ route('about') }}"
                   class="mt-8 inline-flex h-12 items-center justify-center gap-2 rounded-full border border-neutral-700 px-7 text-sm font-semibold transition hover:scale-[1.02] hover:border-neutral-500 hover:bg-neutral-800/50">
                    Selengkapnya tentang kami
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-3.22-3.22a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.22-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
                </a>
            </div>
        </div>
    </section>

    {{-- Testimonials preview (full page: /testimoni) --}}
    <section class="border-t border-neutral-800/50 py-20 md:py-28">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <x-section-heading eyebrow="Testimoni">
                Kata <span class="text-brand">pelanggan</span> kami
            </x-section-heading>

            <div class="grid gap-6 md:grid-cols-3">
                @foreach (array_slice($static['testimonials'], 0, 3) as $review)
                    <x-testimonial-card :review="$review" />
                @endforeach
            </div>

            <div class="mt-10 text-center">
                <a href="{{ route('testimonials') }}"
                   class="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-neutral-700 px-7 text-sm font-semibold transition hover:scale-[1.02] hover:border-neutral-500 hover:bg-neutral-800/50">
                    Lihat Semua Testimoni
                </a>
            </div>
        </div>
    </section>

    {{-- Articles --}}
    <section class="border-t border-neutral-800/50 py-20 md:py-28">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <div class="mb-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <x-section-heading eyebrow="Artikel" :center="false" flush>Tips &amp; kabar terbaru</x-section-heading>
                <a href="{{ route('articles.index') }}" class="shrink-0 text-sm font-semibold text-brand underline underline-offset-4 transition hover:text-accent">Semua artikel</a>
            </div>
            <div class="grid gap-6 md:grid-cols-3">
                @foreach (array_slice($static['articles'], 0, 3) as $article)
                    <x-article-card :article="$article" dark />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Contact preview (full page: /kontak) --}}
    <section class="border-t border-neutral-800/50 py-16">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <div class="grid gap-6 rounded-2xl border border-neutral-800 bg-neutral-900 p-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div class="grid gap-6 sm:grid-cols-3">
                    @foreach ([
                        ['Alamat', $store['address_short'], 'M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11Zm0-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z'],
                        ['Jam buka', $store['hours'], 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 4v5l3 2'],
                        ['Telepon / WhatsApp', $store['phone'], 'M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z'],
                    ] as [$label, $value, $icon])
                        <div class="flex items-start gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand/10 text-brand">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}" /></svg>
                            </span>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-widest text-neutral-500">{{ $label }}</p>
                                <p class="mt-1 text-sm text-neutral-200">{{ $value }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('contact') }}"
                       class="inline-flex h-11 items-center justify-center rounded-full bg-brand px-6 text-sm font-semibold text-ink transition hover:scale-[1.02] hover:bg-brand/90">Hubungi Kami</a>
                    <a href="{{ $store['maps_url'] }}" target="_blank" rel="noopener"
                       class="inline-flex h-11 items-center justify-center rounded-full border border-neutral-700 px-6 text-sm font-semibold transition hover:border-neutral-500 hover:bg-neutral-800/50">Petunjuk Arah</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="pb-20 md:pb-28">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <div class="relative overflow-hidden rounded-3xl border border-neutral-800 bg-neutral-900 px-6 py-16 text-center md:py-20">
                <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                    <div class="absolute -left-20 -top-20 h-72 w-72 rounded-full bg-brand/20 blur-3xl"></div>
                    <div class="absolute -bottom-24 -right-16 h-72 w-72 rounded-full bg-accent/20 blur-3xl"></div>
                </div>
                <div class="relative">
                    <h2 class="text-3xl font-bold md:text-5xl">Ayo <span class="text-brand">Belanja</span> Sekarang</h2>
                    <p class="mx-auto mt-4 max-w-md text-neutral-400">Dapatkan harga dan potongan harga menarik yang kami tawarkan segera.</p>
                    <div class="mt-10 flex flex-wrap justify-center gap-4">
                        <a href="{{ route('products.index') }}"
                           class="inline-flex h-12 items-center justify-center rounded-full bg-brand px-7 text-sm font-semibold text-ink transition hover:scale-[1.02] hover:bg-brand/90">Belanja Sekarang</a>
                        <a href="{{ $store['wa']['partner'] }}" target="_blank" rel="noopener"
                           class="inline-flex h-12 items-center justify-center rounded-full border border-neutral-700 px-7 text-sm font-semibold transition hover:border-neutral-500 hover:bg-neutral-800/50">Gabung Sebagai Partner</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
