<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- Read by resources/js/api/client.js: base URL (works from a subfolder) and the server-side mock flag. --}}
        <meta name="app-url" content="{{ url('/') }}">
        <meta name="api-mock" content="{{ config('app.api_mock') ? '1' : '0' }}">
        <meta name="robots" content="noindex, nofollow">

        <title>@hasSection('title')@yield('title') | @endif Admin {{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
        @include('partials.mock-banner')

        <div class="flex min-h-screen flex-col md:flex-row">
            <aside class="border-b border-gray-200 bg-white md:w-56 md:border-b-0 md:border-r" x-data>
                <div class="px-4 py-4">
                    <a href="{{ route('home') }}" class="font-semibold">{{ config('app.name') }}</a>
                    <p class="text-xs text-gray-500">Panel admin</p>
                </div>
                <nav class="flex gap-4 overflow-x-auto px-4 pb-4 text-sm md:flex-col md:gap-1">
                    <a href="{{ route('admin.orders.index') }}" class="rounded px-2 py-1 hover:bg-gray-100 @if(request()->routeIs('admin.orders.*')) bg-gray-100 font-medium @endif">Pesanan</a>
                    <a href="{{ route('admin.articles.index') }}" class="rounded px-2 py-1 hover:bg-gray-100 @if(request()->routeIs('admin.articles.*')) bg-gray-100 font-medium @endif">Artikel</a>
                    <a href="{{ route('admin.sync.index') }}" class="rounded px-2 py-1 hover:bg-gray-100 @if(request()->routeIs('admin.sync.*')) bg-gray-100 font-medium @endif">Sinkron</a>
                    <button type="button" @click="$store.auth.logout()" class="rounded px-2 py-1 text-left hover:bg-gray-100">Keluar</button>
                </nav>
            </aside>

            <main class="flex-1 px-4 py-6 md:px-8">
                @yield('content')
            </main>
        </div>
    </body>
</html>
