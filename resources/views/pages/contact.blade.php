@extends('layouts.app')

@section('title', 'Kontak & Lokasi Toko Sepeda Listrik Bekasi')
@section('meta_description', 'Hubungi Toko Sepeda Listrik Saeful Askar: Jl. Raya Pisangan No.1, Tambun Utara, Bekasi. Buka setiap hari 08.00–21.00 WIB.')
@section('body_class', 'bg-neutral-950 text-white')

@php
    $store = require resource_path('static/store.php');
    $waNumber = '6282227222971';
@endphp

@section('content')
    <x-page-hero eyebrow="Kontak" crumb="Kontak">
        Hubungi <span class="text-brand">Saeful Askar</span>
        <x-slot:intro>Tanya produk, stok, harga partner, atau jadwal service. Kami balas lewat WhatsApp setiap hari.</x-slot:intro>
    </x-page-hero>

    {{-- Contact channels --}}
    <section class="py-16">
        <div class="mx-auto grid max-w-7xl gap-4 px-4 sm:grid-cols-2 md:px-8 lg:grid-cols-4">
            @foreach ([
                ['WhatsApp', $store['phone'], 'Chat sekarang', $store['wa']['shop'], 'M20.5 3.5A11 11 0 0 0 3.2 16.9L2 22l5.2-1.4A11 11 0 0 0 20.5 3.5ZM12 20a8 8 0 0 1-4.1-1.1l-.3-.2-3 .8.8-3-.2-.3A8 8 0 1 1 12 20Zm4.4-6c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1a6.6 6.6 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.1 0-.3 0-.4l-.7-1.7c-.2-.5-.4-.4-.5-.4h-.5a.9.9 0 0 0-.7.3 2.8 2.8 0 0 0-.9 2.1 4.9 4.9 0 0 0 1 2.6 11.2 11.2 0 0 0 4.3 3.8c1.6.7 2.2.7 3 .6.5-.1 1.4-.6 1.6-1.1.2-.6.2-1 .1-1.1l-.3-.3Z'],
                ['Telepon', $store['phone'], 'Telepon', 'tel:+6282227222971', 'M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z'],
                ['Jam buka', $store['hours'], null, null, 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 4v5l3 2'],
                ['Alamat', $store['address_short'], 'Petunjuk arah', $store['maps_url'], 'M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11Zm0-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z'],
            ] as [$label, $value, $cta, $url, $icon])
                <div class="flex flex-col gap-4 rounded-2xl border border-neutral-800 bg-neutral-900 p-6">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-brand/10 text-brand">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}" /></svg>
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-neutral-500">{{ $label }}</p>
                        <p class="mt-1 font-semibold">{{ $value }}</p>
                    </div>
                    @if ($cta)
                        <a href="{{ $url }}" @if (! str_starts_with($url, 'tel:')) target="_blank" rel="noopener" @endif
                           class="mt-auto inline-flex items-center gap-1 text-sm font-semibold text-brand transition hover:text-accent">
                            {{ $cta }} &rarr;
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- Message form (opens WhatsApp with the composed text) + map --}}
    <section class="border-t border-neutral-800/50 py-20 md:py-24">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 md:px-8 lg:grid-cols-2">
            <form class="flex flex-col gap-5 rounded-2xl border border-neutral-800 bg-neutral-900 p-8"
                  x-data="{ name: '', topic: 'Pembelian sepeda listrik', message: '' }"
                  @submit.prevent="window.open('https://api.whatsapp.com/send?phone={{ $waNumber }}&text=' + encodeURIComponent(`Halo min, saya ${name}.\nTopik: ${topic}\n\n${message}`), '_blank')">
                <div>
                    <h2 class="text-2xl font-bold">Kirim pesan</h2>
                    <p class="mt-1 text-sm text-neutral-400">Pesan akan dibuka di WhatsApp, tinggal tekan kirim.</p>
                </div>
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-neutral-300">Nama</span>
                    <input type="text" x-model="name" required placeholder="Nama Anda"
                           class="w-full rounded-xl border border-neutral-700 bg-neutral-950 px-4 py-3 text-white placeholder:text-neutral-600 focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-neutral-300">Topik</span>
                    <select x-model="topic"
                            class="w-full rounded-xl border border-neutral-700 bg-neutral-950 px-4 py-3 text-white focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
                        <option>Pembelian sepeda listrik</option>
                        <option>Sparepart</option>
                        <option>Jasa service</option>
                        <option>Gabung sebagai partner</option>
                        <option>Status pesanan website</option>
                        <option>Lainnya</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-2 block text-sm font-medium text-neutral-300">Pesan</span>
                    <textarea x-model="message" required rows="5" placeholder="Tulis pertanyaan Anda…"
                              class="w-full rounded-xl border border-neutral-700 bg-neutral-950 px-4 py-3 text-white placeholder:text-neutral-600 focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"></textarea>
                </label>
                <button type="submit"
                        class="inline-flex h-12 items-center justify-center rounded-full bg-brand px-7 text-sm font-semibold text-ink transition hover:scale-[1.01] hover:bg-brand/90">
                    Kirim via WhatsApp
                </button>
            </form>

            <div class="flex flex-col gap-6">
                <div class="min-h-80 flex-1 overflow-hidden rounded-2xl border border-neutral-800 bg-neutral-900">
                    <iframe src="{{ $store['maps_embed'] }}" title="Peta lokasi {{ $store['name'] }}" class="h-full min-h-80 w-full grayscale-[40%]" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                </div>
                <div class="rounded-2xl border border-neutral-800 bg-neutral-900 p-6">
                    <p class="text-sm font-semibold uppercase tracking-widest text-brand">{{ $store['name'] }}</p>
                    <p class="mt-2 text-sm leading-relaxed text-neutral-400">{{ $store['address'] }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Socials + marketplaces --}}
    <section class="border-t border-neutral-800/50 py-16">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 md:px-8 lg:grid-cols-2">
            <div class="rounded-2xl border border-neutral-800 bg-neutral-900 p-8">
                <h2 class="text-lg font-bold">Ikuti kami</h2>
                <div class="mt-5 flex flex-wrap gap-3">
                    @foreach ($store['socials'] as $social)
                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener"
                           class="inline-flex h-11 items-center gap-2 rounded-full border border-neutral-700 px-5 text-sm font-semibold transition hover:border-brand hover:text-brand">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $social['icon'] }}" /></svg>
                            {{ $social['name'] }}
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="rounded-2xl border border-neutral-800 bg-neutral-900 p-8">
                <h2 class="text-lg font-bold">Juga tersedia di marketplace</h2>
                <div class="mt-5 flex flex-wrap gap-3">
                    @foreach ($store['marketplaces'] as $marketplace)
                        <a href="{{ $marketplace['url'] }}" target="_blank" rel="noopener" class="rounded-xl bg-white px-4 py-2 transition hover:scale-[1.03]">
                            <img src="{{ asset($marketplace['logo']) }}" alt="{{ $marketplace['name'] }}" class="h-9 w-auto object-contain">
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endsection

@include('seo.local-business')
<x-seo-breadcrumbs :items="[['Home', route('home')], ['Kontak', route('contact')]]" />
