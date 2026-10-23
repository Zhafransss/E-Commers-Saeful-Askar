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
            <aside class="flex flex-col border-b border-gray-200 bg-white md:sticky md:top-0 md:h-screen md:w-60 md:shrink-0 md:border-b-0 md:border-r" x-data>
                <div class="px-4 py-4">
                    <a href="{{ route('home') }}" class="font-semibold">{{ config('app.name') }}</a>
                    <p class="text-xs text-gray-500">Panel admin</p>
                </div>
                <nav class="flex flex-wrap gap-x-2 gap-y-1 px-4 pb-4 text-sm md:flex-col md:flex-nowrap md:gap-1">
                    @foreach ([
                        ['admin.orders.*', 'admin.orders.index', 'Pesanan'],
                        ['admin.shipments.*', 'admin.shipments.index', 'Manajemen Resi'],
                        ['admin.products.*', 'admin.products.index', 'Produk'],
                        ['admin.users.*', 'admin.users.index', 'Manajemen User'],
                        ['admin.sync.*', 'admin.sync.index', 'Riwayat Sinkronisasi'],
                    ] as [$pattern, $route, $label])
                        <a href="{{ route($route) }}" class="whitespace-nowrap rounded px-2 py-1 hover:bg-gray-100 @if(request()->routeIs($pattern)) bg-gray-100 font-medium @endif">{{ $label }}</a>
                    @endforeach
                </nav>

                {{-- Kept apart at the very bottom of the menu, so it is not mistaken for a page --}}
                <div class="mt-auto border-t border-gray-200 px-4 py-3">
                    <button type="button" @click="$store.auth.logout()"
                            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm font-medium text-red-600 hover:bg-red-50">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" /></svg>
                        Keluar
                    </button>
                </div>
            </aside>

            <main class="min-w-0 flex-1 px-4 py-6 md:px-8">
                @yield('content')
            </main>
        </div>
    </body>
</html>
