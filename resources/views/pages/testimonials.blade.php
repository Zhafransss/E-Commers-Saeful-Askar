@extends('layouts.app')

@section('title', 'Testimoni Pelanggan')
{{-- Kept out of Google while the reviews are samples. Remove this line once they are real. --}}
@section('robots', 'noindex, follow')
@section('meta_description', 'Testimoni pelanggan Toko Sepeda Listrik Saeful Askar Bekasi.')
@section('body_class', 'bg-neutral-950 text-white')

{{-- Reviews are SAMPLES (static/catalog.php): replace with real ones or the Google reviews widget before going live. --}}
@php
    $static = require resource_path('static/catalog.php');
    $store = require resource_path('static/store.php');
@endphp

@section('content')
    <x-page-hero eyebrow="Testimoni" crumb="Testimoni">
        Kata <span class="text-brand">pelanggan</span> kami
        <x-slot:intro>Cerita pelanggan yang sudah belanja sepeda listrik, sparepart, dan service di Saeful Askar.</x-slot:intro>
    </x-page-hero>

    <section class="py-20 md:py-24">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <div class="columns-1 gap-6 md:columns-2 lg:columns-3 [&>*]:mb-6 [&>*]:break-inside-avoid">
                @foreach ($static['testimonials'] as $review)
                    <x-testimonial-card :review="$review" />
                @endforeach
            </div>
        </div>
    </section>

    <section class="pb-20 md:pb-28">
        <div class="mx-auto max-w-7xl px-4 md:px-8">
            <div class="relative overflow-hidden rounded-3xl bg-brand px-6 py-14 text-center text-ink md:py-16">
                <h2 class="text-3xl font-bold md:text-4xl">Sudah belanja di Saeful Askar?</h2>
                <p class="mx-auto mt-3 max-w-lg text-ink/80">Bagikan pengalaman Anda. Ulasan Anda membantu pelanggan lain memilih sepeda listrik yang tepat.</p>
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <a href="{{ $store['maps_url'] }}" target="_blank" rel="noopener"
                       class="inline-flex h-12 items-center justify-center rounded-full bg-ink px-7 text-sm font-semibold text-brand transition hover:scale-[1.02]">Tulis Ulasan di Google</a>
                    <a href="{{ route('products.index') }}"
                       class="inline-flex h-12 items-center justify-center rounded-full border border-ink/30 px-7 text-sm font-semibold transition hover:border-ink">Belanja Sekarang</a>
                </div>
            </div>
        </div>
    </section>
@endsection
