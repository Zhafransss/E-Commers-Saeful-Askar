@extends('layouts.app')

{{--
    Static preview. Live data: ART-02.
    $article (database) is null in mock mode when the slug only exists in the mocks;
    SEO tags prefer it, so production pages are described by the real article.
--}}
@php
    $static = require resource_path('static/catalog.php');
    $post = $static['articleDetail'];
    $more = collect($static['articles'])->where('slug', '!=', $post['slug'])->take(3);

    $seoTitle = $article?->title ?? $post['title'];
    $seoText = $article ? trim(preg_replace('/\s+/', ' ', strip_tags($article->body))) : $post['excerpt'];
    $seoImage = $article?->image ? \Illuminate\Support\Facades\Storage::disk('public')->url($article->image) : $post['image_url'];
    $seoUrl = route('articles.show', $slug);
@endphp

@section('title', $seoTitle)
@section('meta_description', \Illuminate\Support\Str::limit($seoText, 155))
@section('og_type', 'article')
@section('og_image', $seoImage)

@push('meta')
    <meta property="article:published_time" content="{{ $article?->published_at?->toIso8601String() ?? $post['published_at'] }}">
@endpush

<x-seo-breadcrumbs :items="[['Home', route('home')], ['Artikel', route('articles.index')], [$seoTitle, $seoUrl]]" />
<x-json-ld :data="[
    '@type' => 'Article',
    'headline' => $seoTitle,
    'description' => \Illuminate\Support\Str::limit($seoText, 300),
    'image' => [$seoImage],
    'datePublished' => $article?->published_at?->toIso8601String() ?? $post['published_at'],
    'dateModified' => $article?->updated_at?->toIso8601String() ?? $post['updated_at'],
    'author' => ['@type' => 'Organization', 'name' => 'Saeful Askar', 'url' => route('home')],
    'publisher' => ['@type' => 'Organization', 'name' => 'Saeful Askar', 'url' => route('home'), 'logo' => ['@type' => 'ImageObject', 'url' => asset('favicon.png')]],
    'mainEntityOfPage' => $seoUrl,
]" />

@section('content')
    <article>
        <header class="bg-ink text-white">
            <div class="mx-auto max-w-3xl px-4 pb-32 pt-12 sm:pt-16">
                <nav class="text-xs text-gray-400" aria-label="Breadcrumb">
                    <a href="{{ route('home') }}" class="hover:text-brand">Beranda</a>
                    <span class="mx-1.5">/</span>
                    <a href="{{ route('articles.index') }}" class="hover:text-brand">Artikel</a>
                </nav>
                <h1 class="mt-5 text-3xl font-bold leading-tight sm:text-5xl">{{ $post['title'] }}</h1>
                <p class="mt-5 text-lg text-gray-300">{{ $post['excerpt'] }}</p>
                <div class="mt-8 flex items-center gap-3 text-sm">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-brand font-bold text-ink">{{ mb_substr($post['author']['name'], 0, 1) }}</span>
                    <div>
                        <p class="font-semibold">{{ $post['author']['name'] }}</p>
                        <p class="text-gray-400">
                            <time datetime="{{ $post['published_at'] }}">{{ \Illuminate\Support\Carbon::parse($post['published_at'])->translatedFormat('j F Y') }}</time>
                        </p>
                    </div>
                </div>
            </div>
        </header>

        <div class="mx-auto -mt-24 max-w-4xl px-4">
            <img src="{{ str_replace('w=800', 'w=1600', $post['image_url']) }}" alt="{{ $post['title'] }}"
                 class="aspect-[16/9] w-full rounded-3xl object-cover shadow-2xl">
        </div>

        <div class="mx-auto max-w-3xl px-4 py-12">
            <div class="rich-text text-lg">{!! $post['body_html'] !!}</div>

            <p class="mt-10 text-sm text-gray-500">
                Diperbarui {{ \Illuminate\Support\Carbon::parse($post['updated_at'])->translatedFormat('j F Y, H.i') }} WIB
            </p>

            <div class="mt-10 flex flex-col items-start gap-4 rounded-2xl bg-brand p-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-bold">Saatnya ganti baterai?</p>
                    <p class="text-sm text-ink/80">Lihat baterai lithium 36V dan 48V yang siap kirim.</p>
                </div>
                <a href="{{ route('products.index', ['category' => 'baterai']) }}"
                   class="shrink-0 rounded-full bg-ink px-6 py-3 text-sm font-semibold text-brand transition hover:bg-ink-soft">Lihat baterai</a>
            </div>
        </div>
    </article>

    <section class="border-t border-gray-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-14">
            <div class="flex items-end justify-between gap-4">
                <h2 class="text-2xl font-bold">Artikel lainnya</h2>
                <a href="{{ route('articles.index') }}" class="text-sm font-semibold underline decoration-brand decoration-2 underline-offset-4">Semua artikel</a>
            </div>
            <div class="mt-8 grid gap-6 md:grid-cols-3">
                @foreach ($more as $other)
                    <x-article-card :article="$other" />
                @endforeach
            </div>
        </div>
    </section>
@endsection
