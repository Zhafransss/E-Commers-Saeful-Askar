@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
    {{-- Placeholder until the storefront scope is defined in docs/SRS.md --}}
    <section class="mx-auto max-w-7xl px-4 py-24 text-center">
        <h1 class="text-3xl font-bold sm:text-4xl">{{ config('app.name') }}</h1>
        <p class="mt-4 text-gray-600">Website sedang dalam tahap pengembangan.</p>
    </section>
@endsection
