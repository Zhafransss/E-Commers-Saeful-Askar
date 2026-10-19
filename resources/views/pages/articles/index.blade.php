@extends('layouts.app')

@section('title', 'Artikel & Tips Sepeda Listrik')
@section('meta_description', 'Tips merawat baterai, memilih sepeda listrik, aturan berkendara, dan kabar promo dari Saeful Askar, toko sepeda listrik di Bekasi.')
@if (request()->filled('q'))
    @section('robots', 'noindex, follow')
@endif
@section('canonical', route('articles.index'))

<x-seo-breadcrumbs :items="[['Home', route('home')], ['Artikel', route('articles.index')]]" />

{{-- Static preview. Live data: ART-01 (?q, page). --}}
@php
    $static = require resource_path('static/catalog.php');
    [$featured, $rest] = [$static['articles'][0], array_slice($static['articles'], 1)];
@endphp

@section('content')
    <section class="bg-ink text-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-12 sm:py-16 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-brand">Artikel &amp; tips</p>
                <h1 class="mt-2 text-3xl font-bold sm:text-4xl">Panduan seputar sepeda listrik</h1>
                <p class="mt-2 max-w-xl text-gray-400">Perawatan baterai, memilih sepeda, aturan jalan, dan kabar promo dari toko.</p>
            </div>
            <form method="get" role="search" class="w-full md:max-w-xs">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari artikel…" aria-label="Cari artikel"
                       class="w-full rounded-full border border-ink-line bg-ink-soft px-5 py-3 text-sm text-white placeholder:text-gray-500 focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
            </form>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-12">
        {{-- Featured (newest) --}}
        <a href="{{ route('articles.show', $featured['slug']) }}"
           class="group grid overflow-hidden rounded-3xl border border-gray-200 bg-white transition hover:border-ink hover:shadow-lg md:grid-cols-2">
            <div class="aspect-[16/10] overflow-hidden bg-ink md:aspect-auto">
                <img src="{{ $featured['image_url'] }}" alt="{{ $featured['title'] }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
            </div>
            <div class="flex flex-col justify-center gap-4 p-8 sm:p-10">
                <span class="w-fit rounded-full bg-brand px-3 py-1 text-xs font-semibold uppercase tracking-wider text-ink">Terbaru</span>
                <h2 class="text-2xl font-bold leading-tight sm:text-3xl">{{ $featured['title'] }}</h2>
                <p class="text-gray-600">{{ $featured['excerpt'] }}</p>
                <time datetime="{{ $featured['published_at'] }}" class="text-sm text-gray-500">
                    {{ \Illuminate\Support\Carbon::parse($featured['published_at'])->translatedFormat('j F Y') }}
                </time>
            </div>
        </a>

        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($rest as $article)
                <x-article-card :article="$article" />
            @endforeach
        </div>

        <x-pagination :current="1" :last="2" class="mt-12" />
    </div>
@endsection
