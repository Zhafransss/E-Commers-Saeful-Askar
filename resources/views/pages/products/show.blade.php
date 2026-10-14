@extends('layouts.app')

{{-- $product is null in mock mode when the slug only exists in the frontend mocks. --}}
@section('title', $product?->name ?? 'Detail Produk')
@section('meta_description', $product ? \Illuminate\Support\Str::limit(strip_tags((string) $product->description_html), 160) : 'Detail produk sepeda listrik dan baterai.')

@push('meta')
    @if ($product)
        <meta property="og:type" content="product">
        <meta property="og:title" content="{{ $product->name }}">
        @if ($product->thumbnail_url)
            <meta property="og:image" content="{{ $product->thumbnail_url }}">
        @endif
    @endif
@endpush

@section('content')
    <x-page-placeholder title="Detail Produk" :endpoints="['CAT-03', 'CART-02']">
        <p class="mt-2 text-sm text-gray-600">Slug: <code>{{ $slug }}</code></p>
    </x-page-placeholder>
@endsection
