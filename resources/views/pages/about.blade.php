@extends('layouts.app')

@section('title', 'Tentang Kami – Toko Sepeda Listrik Bekasi')
@section('meta_description', 'Saeful Askar adalah toko sepeda listrik dan motor listrik di Tambun Utara, Bekasi, berpengalaman lebih dari 10 tahun. Jual, sparepart, dan service.')
@section('body_class', 'bg-neutral-950 text-white')

{{-- Copy and figures come from the previous saefulaskar.com "Tentang Kami" page. --}}
@php
    $store = require resource_path('static/store.php');

    $reasons = [
        ['Harga Terbaik', 'Tetap berkualitas dengan harga bersaing.', 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
        ['Service Cepat dan Tepat', 'Pelayanan yang cepat dengan aksi yang tepat, oleh teknisi berpengalaman.', 'M22 11.1V12a10 10 0 1 1-5.9-9.1M22 4 12 14l-3-3'],
        ['Konsultasi Sebelum Membeli', 'Kami bantu memilih sepeda listrik yang sesuai kebutuhan dan anggaran Anda.', 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z'],
        ['After-sales Service', 'Pembelian tidak berhenti di kasir: perawatan dan perbaikan tetap kami layani.', 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9l-3.8 3.8Z'],
    ];

    $offerings = [
        ['Sepeda Listrik', 'Pilihan model untuk anak-anak hingga dewasa, siap kirim ke area Jabodetabek.', route('products.index', ['category' => 'sepeda-listrik']), 'Lihat produk'],
        ['Sparepart', 'Baterai, charger, ban, dan suku cadang lain. Tersedia grosir dan eceran.', route('products.index', ['category' => 'sparepart']), 'Lihat sparepart'],
        ['Jasa Service', 'Service di rumah atau di toko untuk sepeda listrik dan motor listrik.', route('service'), 'Lihat layanan'],
    ];
@endphp

@section('content')
    <x-page-hero eyebrow="Tentang Kami" crumb="Tentang Kami" :image="asset('images/banner.webp')">
        Tentang <span class="text-brand">Saeful Askar</span>
        <x-slot:intro>Toko sepeda listrik dan motor listrik di Tambun Utara, Bekasi. Lebih dari 10 tahun melayani pelanggan di area Jabodetabek.</x-slot:intro>
    </x-page-hero>

    {{-- Story --}}
    <section class="py-20 md:py-28">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 md:px-8 lg:grid-cols-2">
            <div class="relative">
                <div class="overflow-hidden rounded-3xl border border-neutral-800">
                    <img src="{{ asset('images/products/1-640.webp') }}" alt="Sepeda listrik di toko Saeful Askar" class="aspect-[4/3] w-full object-cover">
                </div>
                <div class="absolute -bottom-6 right-6 rounded-2xl bg-brand px-6 py-4 text-ink shadow-2xl shadow-black/40">
                    <p class="text-3xl font-extrabold leading-none">10+</p>
                    <p class="text-xs font-semibold uppercase tracking-wider">Tahun melayani</p>
                </div>
            </div>

            <div>
                <x-section-heading eyebrow="Cerita Kami" :center="false" flush class="mb-6">
                    Sepeda listrik untuk <span class="text-brand">semua kebutuhan</span>
                </x-section-heading>
                <div class="space-y-4 text-lg leading-relaxed text-neutral-300">
                    <p>Saeful Askar adalah toko sepeda listrik dan motor listrik yang berlokasi di Bekasi, Jawa Barat. Dengan pengalaman lebih dari 10 tahun, kami menyediakan produk berkualitas dengan harga bersaing.</p>
                    <p>Kami berkomitmen memberikan pelayanan terbaik, mulai dari konsultasi pemilihan produk, penjualan, hingga after-sales service.</p>
                    <p>Kami juga membuka kesempatan bagi Anda yang ingin menjadi partner bisnis, dengan harga spesial untuk partner.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Stats --}}
    <section class="border-y border-neutral-800/50 bg-neutral-900/40">
        <dl class="mx-auto grid max-w-7xl divide-y divide-neutral-800/50 px-4 sm:grid-cols-3 sm:divide-x sm:divide-y-0 md:px-8">
            @foreach ($store['stats'] as [$value, $label])
                <div class="flex flex-col-reverse py-10 text-center">
                    <dt class="mt-2 text-sm font-semibold uppercase tracking-widest text-neutral-400">{{ $label }}</dt>
                    <dd class="text-5xl font-extrabold text-brand md:text-6xl">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Why us --}}
    <section class="py-20 md:py-28">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <x-section-heading eyebrow="Kenapa Saeful Askar">Belanja dengan tenang</x-section-heading>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($reasons as [$title, $text, $icon])
                    <div class="rounded-2xl border border-neutral-800 bg-neutral-900 p-6 transition hover:-translate-y-1 hover:border-neutral-600">
                        <span class="grid h-12 w-12 place-items-center rounded-xl bg-brand/10 text-brand">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}" /></svg>
                        </span>
                        <h3 class="mt-5 text-lg font-bold">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-neutral-400">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- What we offer --}}
    <section class="border-t border-neutral-800/50 py-20 md:py-28">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <x-section-heading eyebrow="Yang Kami Tawarkan">Jual, sparepart, dan service</x-section-heading>
            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($offerings as [$title, $text, $url, $cta])
                    <a href="{{ $url }}" class="group flex flex-col rounded-2xl border border-neutral-800 bg-neutral-900 p-7 transition hover:-translate-y-1 hover:border-brand/60">
                        <span class="text-sm font-extrabold text-brand/40">0{{ $loop->iteration }}</span>
                        <h3 class="mt-3 text-2xl font-bold">{{ $title }}</h3>
                        <p class="mt-2 flex-1 text-neutral-400">{{ $text }}</p>
                        <span class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-brand">
                            {{ $cta }}
                            <svg class="h-4 w-4 transition group-hover:translate-x-1" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-3.22-3.22a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.22-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Brand partners --}}
    <section class="border-t border-neutral-800/50 py-16">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <p class="mb-6 text-center text-xs font-semibold uppercase tracking-widest text-neutral-500">Brand Partner</p>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach (['chilwee' => 'Chilwee', 'haha' => 'Haha', 'selis' => 'Selis', 'tianeng' => 'Tianeng'] as $file => $brand)
                    <div class="flex items-center justify-center rounded-xl border border-neutral-800/50 bg-neutral-900/30 p-5 transition-colors hover:bg-neutral-900/60">
                        <img src="{{ asset("images/clients/{$file}.png") }}" alt="{{ $brand }}" loading="lazy" width="80" height="40"
                             class="h-10 w-auto object-contain opacity-60 transition-opacity hover:opacity-100">
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Location --}}
    <section class="border-t border-neutral-800/50 py-20 md:py-28">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 md:px-8 lg:grid-cols-[2fr_3fr]">
            <div class="flex flex-col gap-6 rounded-2xl border border-neutral-800 bg-neutral-900 p-8">
                <x-section-heading eyebrow="Lokasi Kami" :center="false" flush>Kunjungi toko</x-section-heading>
                <div>
                    <p class="font-semibold">{{ $store['name'] }}</p>
                    <p class="mt-2 leading-relaxed text-neutral-400">{{ $store['address'] }}</p>
                </div>
                <ul class="space-y-2 text-neutral-300">
                    <li><span class="text-neutral-500">Jam operasional:</span> {{ $store['hours'] }}</li>
                    <li><span class="text-neutral-500">Telepon:</span> {{ $store['phone'] }}</li>
                </ul>
                <div class="mt-auto flex flex-wrap gap-3">
                    <a href="{{ $store['maps_url'] }}" target="_blank" rel="noopener"
                       class="inline-flex h-11 items-center justify-center rounded-full bg-brand px-6 text-sm font-semibold text-ink transition hover:bg-brand/90">Petunjuk Arah</a>
                    <a href="{{ route('contact') }}"
                       class="inline-flex h-11 items-center justify-center rounded-full border border-neutral-700 px-6 text-sm font-semibold transition hover:border-neutral-500 hover:bg-neutral-800/50">Hubungi Kami</a>
                </div>
            </div>
            <div class="min-h-80 overflow-hidden rounded-2xl border border-neutral-800 bg-neutral-900">
                <iframe src="{{ $store['maps_embed'] }}" title="Peta lokasi {{ $store['name'] }}" class="h-full min-h-80 w-full grayscale-[40%]" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
        </div>
    </section>

    {{-- Partner CTA --}}
    <section class="pb-20 md:pb-28">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <div class="relative overflow-hidden rounded-3xl bg-brand px-6 py-14 text-center text-ink md:py-16">
                <h2 class="text-3xl font-bold md:text-4xl">Gabung sebagai partner</h2>
                <p class="mx-auto mt-3 max-w-lg text-ink/80">Dapatkan harga spesial partner untuk sepeda listrik dan sparepart. Hubungi tim kami untuk informasi kerja sama.</p>
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <a href="{{ $store['wa']['partner'] }}" target="_blank" rel="noopener"
                       class="inline-flex h-12 items-center justify-center rounded-full bg-ink px-7 text-sm font-semibold text-brand transition hover:scale-[1.02]">Daftar Jadi Partner</a>
                    <a href="{{ route('products.index') }}"
                       class="inline-flex h-12 items-center justify-center rounded-full border border-ink/30 px-7 text-sm font-semibold transition hover:border-ink">Lihat Produk</a>
                </div>
            </div>
        </div>
    </section>
@endsection

@include('seo.local-business')
<x-seo-breadcrumbs :items="[['Home', route('home')], ['Tentang Kami', route('about')]]" />
