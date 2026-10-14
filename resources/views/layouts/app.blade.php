<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@hasSection('title')@yield('title') | @endif{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-white font-sans text-gray-900 antialiased">
        <header class="border-b border-gray-200">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
                <a href="{{ route('home') }}" class="text-lg font-semibold">{{ config('app.name') }}</a>
            </div>
        </header>

        <main>
            @yield('content')
        </main>

        <footer class="border-t border-gray-200">
            <div class="mx-auto max-w-7xl px-4 py-6 text-sm text-gray-500">
                &copy; {{ now()->year }} {{ config('app.name') }}
            </div>
        </footer>
    </body>
</html>
