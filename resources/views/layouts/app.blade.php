@php
    $store = require resource_path('static/store.php');

    /*
    | SEO, set per page with sections:
    |   title            → "<title> | Saeful Askar" (the brand alone when absent)
    |   meta_description → description + og:description
    |   robots           → default "index, follow"; private pages pass "noindex, …"
    |   canonical        → default: current URL without query string
    |   og_type/og_image → Open Graph overrides
    | Structured data goes to @push('schema') as JSON-LD (see resources/views/seo/*).
    */
    // Inline @section values arrive HTML-escaped; decode so {{ }} below escapes them once.
    $section = fn (string $name, string $default = '') => trim(html_entity_decode($__env->yieldContent($name, $default), ENT_QUOTES | ENT_HTML5));
    $pageTitle = $section('title');
    $fullTitle = $pageTitle !== '' ? "{$pageTitle} | {$store['site_name']}" : "{$store['name']} | {$store['site_name']}";
    $description = $section('meta_description', 'Toko sepeda listrik Saeful Askar di Tambun Utara, Bekasi. Jual sepeda listrik, baterai, sparepart, dan jasa service. Kirim ke seluruh Indonesia.');
    $canonical = trim($__env->yieldContent('canonical', url()->current()));
    $ogImage = trim($__env->yieldContent('og_image', asset('images/banner.webp')));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- Read by resources/js/api/client.js: base URL (works from a subfolder) and the server-side mock flag. --}}
        <meta name="app-url" content="{{ url('/') }}">
        <meta name="api-mock" content="{{ config('app.api_mock') ? '1' : '0' }}">
        <meta name="theme-color" content="#0a0a0a">

        <title>{{ $fullTitle }}</title>
        <meta name="description" content="{{ $description }}">
        <meta name="robots" content="@yield('robots', 'index, follow, max-image-preview:large')">
        <link rel="canonical" href="{{ $canonical }}">

        <meta property="og:site_name" content="{{ $store['site_name'] }}">
        <meta property="og:locale" content="id_ID">
        <meta property="og:type" content="@yield('og_type', 'website')">
        <meta property="og:title" content="{{ $pageTitle !== '' ? $pageTitle : $store['name'] }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta name="twitter:card" content="summary_large_image">
        @stack('meta')

        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=urbanist:400,500,600,700,800&display=swap" rel="stylesheet" />

        @stack('schema')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        $home = route('home');

        // Main navigation (client brief). "Product" opens a dropdown.
        $menu = [
            ['label' => 'Home', 'url' => $home, 'active' => request()->routeIs('home')],
            ['label' => 'Product', 'active' => request()->routeIs('products.*', 'service'), 'children' => [
                ['label' => 'Sepeda Listrik', 'text' => 'Sepeda & motor listrik siap kirim', 'url' => route('products.index', ['category' => 'sepeda-listrik'])],
                ['label' => 'Sparepart', 'text' => 'Baterai, ban, charger, dan suku cadang', 'url' => route('products.index', ['category' => 'sparepart'])],
                ['label' => 'Jasa Service', 'text' => 'Service di rumah atau di toko', 'url' => route('service')],
            ]],
            ['label' => 'Tentang Kami', 'url' => route('about'), 'active' => request()->routeIs('about')],
            ['label' => 'Artikel', 'url' => route('articles.index'), 'active' => request()->routeIs('articles.*')],
            ['label' => 'Testimoni', 'url' => route('testimonials'), 'active' => request()->routeIs('testimonials')],
            ['label' => 'Kontak', 'url' => route('contact'), 'active' => request()->routeIs('contact')],
        ];
    @endphp
    <body class="@yield('body_class', 'bg-gray-50 text-ink') flex min-h-screen flex-col font-sans antialiased selection:bg-brand selection:text-ink">
        @include('partials.mock-banner')

        <header class="sticky top-0 z-50 border-b border-neutral-800/50 bg-neutral-950/85 text-white backdrop-blur-xl" x-data="{ open: false, product: false }"
                @keydown.escape.window="open = false; product = false">
            {{-- Accent line, as on the previous site --}}
            <div class="h-0.5 bg-gradient-to-r from-brand via-accent to-brand"></div>

            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-3 md:px-8 lg:py-4">
                <a href="{{ $home }}" class="group shrink-0 font-logo text-xl font-bold tracking-wide sm:text-2xl" aria-label="Saeful Askar, beranda">
                    Saeful <span class="text-brand transition-colors duration-300 group-hover:text-accent">Askar</span>
                </a>

                {{-- Desktop menu --}}
                <nav class="hidden lg:block" aria-label="Menu utama">
                    <ul class="flex items-center text-sm font-medium tracking-wide text-neutral-300">
                        @foreach ($menu as $item)
                            <li class="relative" @isset($item['children']) @mouseenter="product = true" @mouseleave="product = false" @endisset>
                                @isset($item['children'])
                                    <button type="button" @click="product = ! product" :aria-expanded="product"
                                            @class(['flex items-center gap-1 px-4 py-2 transition-colors hover:text-brand', 'text-brand' => $item['active'] ?? false])>
                                        {{ $item['label'] }}
                                        <svg class="h-4 w-4 transition-transform duration-200" :class="product && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                                    </button>

                                    <div x-cloak x-show="product" x-transition.opacity.scale.origin.top
                                         class="absolute left-1/2 top-full w-80 -translate-x-1/2 pt-3" @click.outside="product = false">
                                        <ul class="overflow-hidden rounded-2xl border border-neutral-800 bg-neutral-900 p-2 shadow-2xl shadow-black/40">
                                            @foreach ($item['children'] as $child)
                                                <li>
                                                    <a href="{{ $child['url'] }}" class="group/item flex items-start gap-3 rounded-xl p-3 transition hover:bg-neutral-800">
                                                        <span class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand transition group-hover/item:bg-brand group-hover/item:text-ink">
                                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                @if ($loop->index === 0)
                                                                    <circle cx="5.5" cy="17.5" r="3.5" /><circle cx="18.5" cy="17.5" r="3.5" /><path d="M15 6h2l3 11.5M5.5 17.5 9 10h7l-4 7.5H5.5M9 10 8 7H6" />
                                                                @elseif ($loop->index === 1)
                                                                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" />
                                                                @else
                                                                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9l-3.8 3.8Z" />
                                                                @endif
                                                            </svg>
                                                        </span>
                                                        <span>
                                                            <span class="block font-semibold text-white">{{ $child['label'] }}</span>
                                                            <span class="block text-xs font-normal text-neutral-400">{{ $child['text'] }}</span>
                                                        </span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @else
                                    <a href="{{ $item['url'] }}"
                                       @class(['relative block px-4 py-2 transition-colors hover:text-brand after:absolute after:inset-x-4 after:-bottom-0.5 after:h-0.5 after:origin-left after:scale-x-0 after:bg-brand after:transition-transform hover:after:scale-x-100', 'text-brand after:scale-x-100' => $item['active'] ?? false])>
                                        {{ $item['label'] }}
                                    </a>
                                @endisset
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <div class="flex items-center gap-2">
                    <a href="{{ route('cart') }}" class="relative grid h-11 w-11 place-items-center rounded-full text-neutral-300 transition hover:bg-neutral-800/60 hover:text-brand" aria-label="Keranjang">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.3" /><circle cx="18" cy="20" r="1.3" /><path d="M2.5 3h2.6l2.4 12.2a1.5 1.5 0 0 0 1.5 1.2h8.9a1.5 1.5 0 0 0 1.5-1.1L21.5 8H6" /></svg>
                        <span x-cloak x-show="$store.cart.totalQty > 0" x-text="$store.cart.totalQty"
                              class="absolute right-0.5 top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-brand px-1 text-[11px] font-bold text-ink"></span>
                    </a>

                    <div class="hidden sm:block">
                        <template x-if="! $store.auth.user">
                            <a href="{{ route('login') }}"
                               class="inline-flex h-11 items-center justify-center rounded-full bg-brand px-6 text-sm font-semibold text-ink transition hover:scale-[1.02] hover:bg-brand/90 active:scale-[0.98]">
                                Login
                            </a>
                        </template>
                        <template x-if="$store.auth.user">
                            <div class="flex items-center gap-1 text-sm font-medium text-neutral-300">
                                <a href="{{ route('account.orders') }}" class="rounded-full px-3 py-2 transition hover:text-brand">Pesanan</a>
                                <a x-show="$store.auth.isAdmin" href="{{ route('admin.orders.index') }}" class="rounded-full px-3 py-2 transition hover:text-brand">Admin</a>
                                <button type="button" @click="$store.auth.logout()"
                                        class="inline-flex h-11 items-center rounded-full border border-neutral-700 px-5 transition hover:border-neutral-500 hover:bg-neutral-800/50">Keluar</button>
                            </div>
                        </template>
                    </div>

                    <button type="button" class="grid h-11 w-11 place-items-center rounded-full text-neutral-300 transition hover:bg-neutral-800/60 lg:hidden"
                            @click="open = ! open" :aria-expanded="open" aria-label="Buka menu">
                        <svg x-show="! open" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 8h16M4 16h16" /></svg>
                        <svg x-cloak x-show="open" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
                    </button>
                </div>
            </div>

            {{-- Mobile menu --}}
            <div x-cloak x-show="open" x-transition.opacity class="border-t border-neutral-800 lg:hidden" x-data="{ sub: false }">
                <ul class="mx-auto max-w-7xl space-y-1 px-4 py-4 font-medium text-neutral-300">
                    @foreach ($menu as $item)
                        <li>
                            @isset($item['children'])
                                <button type="button" @click="sub = ! sub" :aria-expanded="sub"
                                        class="flex w-full items-center justify-between rounded-xl px-3 py-3 transition hover:bg-neutral-900 hover:text-brand">
                                    {{ $item['label'] }}
                                    <svg class="h-5 w-5 transition-transform" :class="sub && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                                </button>
                                <ul x-show="sub" x-transition.opacity class="ml-3 border-l border-neutral-800 pl-3">
                                    @foreach ($item['children'] as $child)
                                        <li><a href="{{ $child['url'] }}" @click="open = false" class="block rounded-xl px-3 py-2.5 text-sm transition hover:bg-neutral-900 hover:text-brand">{{ $child['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            @else
                                <a href="{{ $item['url'] }}" @click="open = false"
                                   @class(['block rounded-xl px-3 py-3 transition hover:bg-neutral-900 hover:text-brand', 'text-brand' => $item['active'] ?? false])>{{ $item['label'] }}</a>
                            @endisset
                        </li>
                    @endforeach
                    <li class="pt-3">
                        <template x-if="! $store.auth.user">
                            <a href="{{ route('login') }}" class="flex h-12 items-center justify-center rounded-full bg-brand font-semibold text-ink">Login</a>
                        </template>
                        <template x-if="$store.auth.user">
                            <div class="grid grid-cols-2 gap-2">
                                <a href="{{ route('account.orders') }}" class="flex h-12 items-center justify-center rounded-full border border-neutral-700">Pesanan saya</a>
                                <button type="button" @click="$store.auth.logout()" class="h-12 rounded-full border border-neutral-700">Keluar</button>
                            </div>
                        </template>
                    </li>
                </ul>
            </div>
        </header>

        <main class="flex-1">
            @yield('content')
        </main>

        <footer class="border-t border-neutral-800/50 bg-neutral-950 text-neutral-400">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:grid-cols-2 md:px-8 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
                <div class="space-y-4">
                    <a href="{{ $home }}" class="font-logo text-2xl font-bold text-white">Saeful <span class="text-brand">Askar</span></a>
                    <p class="max-w-xs text-sm leading-relaxed">Toko sepeda listrik dan motor listrik di Bekasi. Berpengalaman lebih dari 10 tahun.</p>
                    <div class="flex gap-2 pt-2">
                        @foreach ($store['socials'] as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener" aria-label="{{ $social['name'] }}"
                               class="grid h-10 w-10 place-items-center rounded-full border border-neutral-800 text-neutral-300 transition hover:border-brand hover:text-brand">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $social['icon'] }}" /></svg>
                            </a>
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="mb-4 text-sm font-semibold text-white">Navigasi</p>
                    <ul class="space-y-2.5 text-sm">
                        @foreach ($menu as $item)
                            @isset($item['children'])
                                @foreach ($item['children'] as $child)
                                    <li><a href="{{ $child['url'] }}" class="transition hover:text-brand">{{ $child['label'] }}</a></li>
                                @endforeach
                            @elseif ($item['label'] !== 'Home')
                                <li><a href="{{ $item['url'] }}" class="transition hover:text-brand">{{ $item['label'] }}</a></li>
                            @endisset
                        @endforeach
                    </ul>
                </div>

                <div>
                    <p class="mb-4 text-sm font-semibold text-white">Kontak</p>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="{{ $store['wa']['shop'] }}" target="_blank" rel="noopener" class="transition hover:text-brand">{{ $store['phone'] }}</a></li>
                        <li>{{ $store['hours'] }}</li>
                        <li>{{ $store['address_short'] }}</li>
                    </ul>
                </div>

                <div>
                    <p class="mb-4 text-sm font-semibold text-white">Juga tersedia di</p>
                    <ul class="space-y-2.5 text-sm">
                        @foreach ($store['marketplaces'] as $marketplace)
                            <li><a href="{{ $marketplace['url'] }}" target="_blank" rel="noopener" class="transition hover:text-brand">{{ $marketplace['name'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="border-t border-neutral-800/50">
                <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-xs text-neutral-500 sm:flex-row sm:items-center sm:justify-between md:px-8">
                    <p>&copy; {{ now()->year }} Saeful Askar. All rights reserved.</p>
                    <p>Tambun Utara, Bekasi — Jawa Barat</p>
                </div>
            </div>
        </footer>
    </body>
</html>
