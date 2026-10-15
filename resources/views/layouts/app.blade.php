<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- Read by resources/js/api/client.js: base URL (works from a subfolder) and the server-side mock flag. --}}
        <meta name="app-url" content="{{ url('/') }}">
        <meta name="api-mock" content="{{ config('app.api_mock') ? '1' : '0' }}">

        <title>@hasSection('title')@yield('title') | @endif{{ config('app.name') }}</title>
        <meta name="description" content="@yield('meta_description', 'Toko sepeda listrik dan baterai sepeda listrik Saeful Askar.')">
        @stack('meta')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-white font-sans text-gray-900 antialiased">
        @include('partials.mock-banner')

        <header class="border-b border-gray-200" x-data>
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4">
                <a href="{{ route('home') }}" class="text-lg font-semibold">{{ config('app.name') }}</a>

                <nav class="flex items-center gap-4 text-sm sm:gap-6">
                    <a href="{{ route('products.index') }}" class="hover:text-gray-600">Produk</a>
                    <a href="{{ route('articles.index') }}" class="hover:text-gray-600">Artikel</a>

                    <a href="{{ route('cart') }}" class="relative hover:text-gray-600" aria-label="Keranjang">
                        Keranjang
                        <span x-cloak x-show="$store.cart.totalQty > 0" x-text="$store.cart.totalQty"
                              class="ml-1 rounded-full bg-gray-900 px-2 py-0.5 text-xs text-white"></span>
                    </a>

                    <template x-if="$store.auth.loaded && ! $store.auth.user">
                        <a href="{{ route('login') }}" class="hover:text-gray-600">Masuk</a>
                    </template>
                    <template x-if="$store.auth.user">
                        <div class="flex items-center gap-4">
                            <a href="{{ route('account.orders') }}" class="hover:text-gray-600">Pesanan</a>
                            <a x-show="$store.auth.isAdmin" href="{{ route('admin.orders.index') }}" class="hover:text-gray-600">Admin</a>
                            <button type="button" @click="$store.auth.logout()" class="hover:text-gray-600">Keluar</button>
                        </div>
                    </template>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            @yield('content')
        </main>

        <footer class="border-t border-gray-200">
            <div class="mx-auto max-w-7xl px-4 py-6 text-sm text-gray-500">
                &copy; {{ now()->year }} {{ config('app.name') }}
            </div>
        </footer>
    </body>
</html>
