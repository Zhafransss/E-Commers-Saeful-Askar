@extends('layouts.app')

@section('title', 'Service Sepeda Listrik Bekasi – Di Rumah & Toko')
@section('meta_description', 'Jasa service sepeda listrik dan motor listrik Saeful Askar Bekasi. Service di rumah atau di toko oleh teknisi berpengalaman lebih dari 10 tahun.')
@section('body_class', 'bg-neutral-950 text-white')

@php
    $store = require resource_path('static/store.php');

    $repairs = [
        ['Baterai & charger', 'Cek kapasitas, ganti sel, dan ganti baterai lithium atau aki.', 'M3 7h16v10H3zM21 11v2M10 9l-2 3h4l-2 3'],
        ['Dinamo / motor', 'Dinamo berisik, lemah tarikan, atau mati total.', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM12 2v3m0 14v3M2 12h3m14 0h3M4.9 4.9l2.1 2.1m10 10 2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1'],
        ['Controller', 'Ganti atau perbaiki controller yang korslet dan error.', 'M5 5h14v14H5zM9 1v4m6-4v4M9 19v4m6-4v4M1 9h4m-4 6h4m14-6h4m-4 6h4'],
        ['Rem', 'Setel dan ganti kampas rem cakram maupun tromol.', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-5a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
        ['Ban & velg', 'Tambal, ganti ban dalam/luar, dan setel velg.', 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm0-6a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
        ['Kelistrikan & lampu', 'Lampu, klakson, kabel, kunci kontak, dan panel speedometer.', 'M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2Z'],
    ];

    $steps = [
        ['Hubungi kami', 'Ceritakan keluhan sepeda listrik Anda lewat WhatsApp.'],
        ['Atur jadwal', 'Pilih service di rumah atau datang langsung ke toko.'],
        ['Pengecekan', 'Teknisi memeriksa dan menjelaskan kerusakan serta biayanya.'],
        ['Selesai', 'Dikerjakan setelah Anda setuju, lalu sepeda siap dipakai.'],
    ];

    // Sample answers: confirm the wording with the store before going live.
    $faqs = [
        ['Berapa biaya service?', 'Biaya tergantung jenis kerusakan dan sparepart yang diganti. Teknisi menyampaikan perkiraan biaya setelah pengecekan, sebelum pekerjaan dimulai.'],
        ['Area mana saja yang bisa service di rumah?', 'Service di rumah melayani area Bekasi dan sekitarnya. Hubungi kami via WhatsApp untuk memastikan lokasi Anda terjangkau.'],
        ['Apakah bisa service sepeda yang tidak dibeli di Saeful Askar?', 'Bisa. Kami melayani service sepeda listrik dan motor listrik dari berbagai merek.'],
        ['Berapa lama pengerjaannya?', 'Kerusakan ringan biasanya selesai di hari yang sama. Bila perlu menunggu sparepart, kami kabari perkiraan waktunya.'],
    ];
@endphp

@section('content')
    <x-page-hero eyebrow="Layanan Kami" crumb="Jasa Service" :image="asset('images/banner.webp')">
        Jasa Service <span class="text-brand">Sepeda Listrik</span>
        <x-slot:intro>Layanan service profesional untuk sepeda listrik dan motor listrik Anda. Dikerjakan oleh teknisi berpengalaman lebih dari 10 tahun.</x-slot:intro>
        <x-slot:actions>
            <a href="{{ $store['wa']['service'] }}" target="_blank" rel="noopener"
               class="inline-flex h-12 items-center justify-center rounded-full bg-brand px-7 text-sm font-semibold text-ink transition hover:scale-[1.02] hover:bg-brand/90">Konsultasi via WhatsApp</a>
            <a href="#pilihan-service"
               class="inline-flex h-12 items-center justify-center rounded-full border border-neutral-700 px-7 text-sm font-semibold transition hover:border-neutral-500 hover:bg-neutral-800/50">Lihat pilihan service</a>
        </x-slot:actions>
    </x-page-hero>

    {{-- Service options --}}
    <section id="pilihan-service" class="scroll-mt-20 py-20 md:py-24">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <x-section-heading eyebrow="Pilihan Service">Di rumah atau di toko</x-section-heading>
            <div class="mx-auto grid max-w-4xl gap-6 md:grid-cols-2">
                @foreach ($store['services'] as $service)
                    <x-service-card :service="$service" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- What we repair --}}
    <section class="border-t border-neutral-800/50 py-20 md:py-24">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <x-section-heading eyebrow="Yang Kami Tangani">
                Dari baterai sampai <span class="text-brand">kelistrikan</span>
            </x-section-heading>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($repairs as [$title, $text, $icon])
                    <div class="flex gap-4 rounded-2xl border border-neutral-800 bg-neutral-900/60 p-6">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand/10 text-brand">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}" /></svg>
                        </span>
                        <div>
                            <h3 class="font-semibold">{{ $title }}</h3>
                            <p class="mt-1 text-sm text-neutral-400">{{ $text }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Steps --}}
    <section class="border-t border-neutral-800/50 py-20 md:py-24">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <x-section-heading eyebrow="Alur Service">Mudah dalam 4 langkah</x-section-heading>
            <ol class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($steps as [$title, $text])
                    <li class="relative rounded-2xl border border-neutral-800 bg-neutral-900 p-6">
                        <span class="text-5xl font-extrabold text-brand/25">0{{ $loop->iteration }}</span>
                        <h3 class="mt-3 text-lg font-bold">{{ $title }}</h3>
                        <p class="mt-1 text-sm text-neutral-400">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- FAQ + workshop --}}
    <section class="border-t border-neutral-800/50 py-20 md:py-24">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 md:px-8 lg:grid-cols-[1.4fr_1fr]">
            <div>
                <x-section-heading eyebrow="Tanya Jawab" :center="false" flush class="mb-8">Pertanyaan umum</x-section-heading>
                <div class="divide-y divide-neutral-800 rounded-2xl border border-neutral-800 bg-neutral-900" x-data="{ open: 0 }">
                    @foreach ($faqs as [$question, $answer])
                        <div>
                            <button type="button" @click="open = open === {{ $loop->index }} ? null : {{ $loop->index }}"
                                    :aria-expanded="open === {{ $loop->index }}"
                                    class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left font-semibold transition hover:text-brand">
                                {{ $question }}
                                <svg class="h-5 w-5 shrink-0 text-brand transition-transform" :class="open === {{ $loop->index }} && 'rotate-45'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" /></svg>
                            </button>
                            <p x-show="open === {{ $loop->index }}" @if (! $loop->first) x-cloak @endif class="px-6 pb-5 text-neutral-400">{{ $answer }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="h-fit rounded-2xl border border-neutral-800 bg-neutral-900 p-8">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand">Lokasi Workshop</p>
                <p class="mt-3 font-semibold">{{ $store['name'] }}</p>
                <p class="mt-2 text-sm leading-relaxed text-neutral-400">{{ $store['address'] }}</p>
                <p class="mt-4 text-sm text-neutral-300">{{ $store['hours'] }}</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ $store['maps_url'] }}" target="_blank" rel="noopener"
                       class="inline-flex h-11 items-center justify-center rounded-full bg-brand px-6 text-sm font-semibold text-ink transition hover:bg-brand/90">Petunjuk Arah</a>
                    <a href="{{ route('contact') }}"
                       class="inline-flex h-11 items-center justify-center rounded-full border border-neutral-700 px-6 text-sm font-semibold transition hover:border-neutral-500">Kontak</a>
                </div>
            </aside>
        </div>
    </section>
@endsection

@include('seo.local-business')
<x-seo-breadcrumbs :items="[['Home', route('home')], ['Jasa Service', route('service')]]" />
<x-json-ld :data="[
    '@type' => 'Service',
    'name' => 'Jasa Service Sepeda Listrik dan Motor Listrik',
    'serviceType' => 'Service sepeda listrik',
    'description' => 'Service sepeda listrik dan motor listrik di rumah atau di toko: baterai, charger, dinamo, controller, rem, ban, dan kelistrikan.',
    'provider' => ['@id' => route('home').'#store'],
    'areaServed' => ['Bekasi', 'Jabodetabek'],
    'url' => route('service'),
]" />
<x-json-ld :data="[
    '@type' => 'FAQPage',
    'mainEntity' => collect($faqs)->map(fn ($faq) => [
        '@type' => 'Question',
        'name' => $faq[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
    ])->all(),
]" />
