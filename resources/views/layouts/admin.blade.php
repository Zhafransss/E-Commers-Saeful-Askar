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
    <body class="admin min-h-screen bg-[#faf8f3] font-sans text-gray-900 antialiased">
        @include('partials.mock-banner')

        @php
            $nav = [
                ['admin.orders.*', 'admin.orders.index', 'Pesanan', 'M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0'],
                ['admin.shipments.*', 'admin.shipments.index', 'Manajemen Resi', 'M1 3h15v13H1zM16 8h4l3 3v5h-7zM5.5 21a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM18.5 21a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z'],
                ['admin.products.*', 'admin.products.index', 'Produk', 'M21 8 12 3 3 8v8l9 5 9-5zM3 8l9 5 9-5M12 13v8'],
                ['admin.users.*', 'admin.users.index', 'Manajemen User', 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
                ['admin.admins.*', 'admin.admins.index', 'Manajemen Admin', 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4'],
                ['admin.sync.*', 'admin.sync.index', 'Riwayat Sinkronisasi', 'M21 12a9 9 0 0 1-15.5 6.2L3 16M3 12a9 9 0 0 1 15.5-6.2L21 8M21 3v5h-5M3 21v-5h5'],
            ];
        @endphp

        <div class="flex min-h-screen flex-col md:flex-row" x-data="{ menu: false }" @keydown.escape.window="menu = false">
            {{-- Phone and tablet: a slim top bar opens the menu as a drawer --}}
            <header class="sticky top-0 z-30 flex items-center justify-between bg-ink px-4 py-3 text-white md:hidden">
                <a href="{{ route('home') }}" class="font-logo text-lg font-bold">Saeful <span class="text-gold">Askar</span></a>
                <button type="button" @click="menu = true" aria-label="Buka menu" class="rounded-lg p-2 text-gold hover:bg-white/10">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            </header>

            <div x-cloak x-show="menu" x-transition.opacity @click="menu = false" class="fixed inset-0 z-40 bg-ink/60 backdrop-blur-sm md:hidden"></div>

            <aside class="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85%] -translate-x-full flex-col bg-ink text-white shadow-2xl transition-transform duration-300 md:sticky md:top-0 md:h-screen md:w-64 md:max-w-none md:shrink-0 md:translate-x-0 md:shadow-none"
                   :class="menu && 'translate-x-0'" x-data>
                <div class="flex items-start justify-between px-5 py-6">
                    <div>
                        <a href="{{ route('home') }}" class="font-logo text-xl font-bold leading-tight">Saeful <span class="text-gold">Askar</span></a>
                        <p class="mt-1 text-xs uppercase tracking-widest text-neutral-400">Panel admin</p>
                    </div>
                    <button type="button" @click="menu = false" aria-label="Tutup menu" class="rounded-lg p-1.5 text-neutral-400 hover:bg-white/10 hover:text-white md:hidden">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-3 text-sm">
                    @foreach ($nav as [$pattern, $route, $label, $icon])
                        @php $active = request()->routeIs($pattern); @endphp
                        <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                           class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition {{ $active ? 'bg-gold text-ink shadow-sm' : 'text-neutral-300 hover:bg-white/10 hover:text-white' }}">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}" /></svg>
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>

                {{-- Kept apart at the very bottom of the menu, so it is not mistaken for a page --}}
                <div class="border-t border-white/10 p-3">
                    <button type="button" @click="$store.auth.logout()"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-red-400 transition hover:bg-red-500/10 hover:text-red-300">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" /></svg>
                        Keluar
                    </button>
                </div>
            </aside>

            <main class="admin-main min-w-0 flex-1 px-4 py-6 md:px-8 md:py-8">
                @yield('content')
            </main>
        </div>
    </body>
</html>
