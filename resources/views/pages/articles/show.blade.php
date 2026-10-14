@extends('layouts.app')

{{-- $article is null in mock mode when the slug only exists in the frontend mocks. --}}
@section('title', $article?->title ?? 'Artikel')
@section('meta_description', $article ? \Illuminate\Support\Str::limit(strip_tags($article->body), 160) : 'Artikel seputar sepeda listrik.')

@push('meta')
    @if ($article)
        <meta property="og:type" content="article">
        <meta property="og:title" content="{{ $article->title }}">
        @if ($article->image)
            <meta property="og:image" content="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($article->image) }}">
        @endif
    @endif
@endpush

@section('content')
    <x-page-placeholder title="Artikel" :endpoints="['ART-02']">
        <p class="mt-2 text-sm text-gray-600">Slug: <code>{{ $slug }}</code></p>
    </x-page-placeholder>
@endsection
