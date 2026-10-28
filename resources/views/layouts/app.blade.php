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
            ['label' => 'Product', 'active' => request()->routeIs('products.*'), 'children' => [
                ['label' => 'Sepeda Listrik', 'text' => 'Sepeda & motor listrik siap kirim', 'url' => route('products.index', ['category' => 'unit'])],
                ['label' => 'Sparepart', 'text' => 'Baterai, ban, charger, dan suku cadang', 'url' => route('products.index', ['category' => 'sparepart'])],
            ]],
            ['label' => 'Jasa Service', 'url' => 'https://www.saefulaskar.com/jasa-service', 'active' => false],
            ['label' => 'Tentang Kami', 'url' => 'https://www.saefulaskar.com/tentang-kami', 'active' => false],
        ];

        // Pages that open with a full-bleed hero (the home page) set @section('header_overlay', '1'): the content
        // slides under the translucent bar, as on the previous site. Other pages start below the bar.
        $headerOverlay = $__env->yieldContent('header_overlay') === '1';
    @endphp
    <body class="@yield('body_class', 'bg-gray-50 text-ink') flex min-h-screen flex-col font-sans antialiased selection:bg-brand selection:text-ink">
        @include('partials.mock-banner')

        {{-- Translucent over a hero image on pages that opt in; solid on the others, where a see-through bar would turn grey over the light page. --}}
        <header @class(['sticky top-0 z-50 border-b border-neutral-800/50 text-white backdrop-blur-xl', 'bg-neutral-950/80' => $headerOverlay, 'bg-neutral-950' => ! $headerOverlay]) x-data="{ open: false, product: false, sub: false }"
                @keydown.escape.window="open = false; product = false" @resize.window="if (window.innerWidth >= 1024) open = false">
            <div class="mx-auto max-w-7xl px-4 md:px-12 xl:px-4">
                <div class="relative flex flex-wrap items-center justify-between gap-6 py-3 md:gap-0 md:py-4">
                    <div class="relative z-20 flex w-full justify-between lg:w-max">
                        <a href="{{ $home }}" class="group flex items-center space-x-2" aria-label="Saeful Askar, beranda">
                            <span class="font-logo text-2xl font-bold text-white">
                                Saeful <span class="text-brand transition-colors duration-300 group-hover:text-accent">Askar</span>
                            </span>
                        </a>

                        {{-- Mobile: cart and the two-line menu button --}}
                        <div class="flex max-h-10 items-center gap-1 lg:hidden">
                            @unless (auth()->user()?->isAdmin())
                            <a href="{{ route('cart') }}" class="relative grid h-11 w-11 place-items-center rounded-full text-neutral-300 transition hover:text-brand" aria-label="Keranjang">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.3" /><circle cx="18" cy="20" r="1.3" /><path d="M2.5 3h2.6l2.4 12.2a1.5 1.5 0 0 0 1.5 1.2h8.9a1.5 1.5 0 0 0 1.5-1.1L21.5 8H6" /></svg>
                                <span x-cloak x-show="$store.cart.totalQty > 0" x-text="$store.cart.totalQty"
                                      class="absolute right-0.5 top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-brand px-1 text-[11px] font-bold text-ink"></span>
                            </a>
                            @endunless
                            <button type="button" @click="open = ! open" :aria-expanded="open" aria-label="Buka menu" class="relative -mr-4 cursor-pointer p-4 md:-mr-6 md:p-6">
                                <span aria-hidden="true" class="m-auto block h-0.5 w-6 origin-center rounded bg-neutral-300 transition duration-300" :class="open && 'translate-y-1 rotate-45'"></span>
                                <span aria-hidden="true" class="m-auto mt-1.5 block h-0.5 w-6 origin-center rounded bg-neutral-300 transition duration-300" :class="open && '-translate-y-1 -rotate-45'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Full-screen blur behind the open mobile menu --}}
                    <div aria-hidden="true" @click="open = false"
                         class="fixed inset-0 z-10 h-screen w-screen origin-bottom scale-y-0 bg-neutral-950/90 backdrop-blur-2xl transition duration-500 lg:hidden"
                         :class="open && 'origin-top scale-y-100'"></div>

                    {{-- Menu: a card under the bar on mobile, inline on desktop --}}
                    <div class="invisible absolute left-0 top-full z-20 flex max-h-[80vh] w-full origin-top translate-y-1 scale-95 flex-col flex-wrap justify-end gap-6 overflow-y-auto rounded-2xl border border-neutral-800 bg-neutral-900 p-8 opacity-0 shadow-2xl shadow-black/20 transition-all duration-300 lg:visible lg:relative lg:flex lg:max-h-none lg:w-auto lg:translate-y-0 lg:scale-100 lg:flex-row lg:items-center lg:gap-0 lg:overflow-visible lg:border-none lg:bg-transparent lg:p-0 lg:opacity-100 lg:shadow-none"
                         :class="open && 'visible! translate-y-0 scale-100 opacity-100'">
                        {{-- Desktop menu --}}
                        <nav class="hidden lg:block lg:pr-4" aria-label="Menu utama">
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

                        {{-- Mobile menu --}}
                        <nav class="w-full text-neutral-300 lg:hidden" aria-label="Menu utama">
                            <ul class="space-y-1 font-medium">
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
                            </ul>
                        </nav>

                        {{-- Cart and account; the yellow pill is the main action, as on the previous site --}}
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:gap-2">
                            @unless (auth()->user()?->isAdmin())
                            <a href="{{ route('cart') }}" class="relative hidden h-11 w-11 place-items-center rounded-full text-neutral-300 transition hover:text-brand lg:grid" aria-label="Keranjang">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.3" /><circle cx="18" cy="20" r="1.3" /><path d="M2.5 3h2.6l2.4 12.2a1.5 1.5 0 0 0 1.5 1.2h8.9a1.5 1.5 0 0 0 1.5-1.1L21.5 8H6" /></svg>
                                <span x-cloak x-show="$store.cart.totalQty > 0" x-text="$store.cart.totalQty"
                                      class="absolute right-0.5 top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-brand px-1 text-[11px] font-bold text-ink"></span>
                            </a>
                            @endunless

                            <template x-if="! $store.auth.user">
                                <a href="{{ route('login') }}"
                                   class="inline-flex h-11 items-center justify-center rounded-full bg-brand px-6 text-sm font-semibold text-ink transition-all duration-200 hover:scale-[1.02] hover:bg-brand/90 active:scale-[0.98]">
                                    Login
                                </a>
                            </template>
                            <template x-if="$store.auth.user">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:gap-1">
                                    <a href="{{ route('account.orders') }}" class="flex h-11 items-center justify-center rounded-full border border-neutral-700 px-5 text-sm font-semibold text-neutral-200 transition hover:text-brand lg:border-0 lg:px-3">Pesanan</a>
                                    <a x-show="$store.auth.isAdmin" href="{{ route('admin.orders.index') }}" class="flex h-11 items-center justify-center rounded-full border border-neutral-700 px-5 text-sm font-semibold text-neutral-200 transition hover:text-brand lg:border-0 lg:px-3">Admin</a>
                                    <button type="button" @click="$store.auth.logout()"
                                            class="inline-flex h-11 items-center justify-center rounded-full bg-brand px-6 text-sm font-semibold text-ink transition-all duration-200 hover:scale-[1.02] hover:bg-brand/90 active:scale-[0.98]">Keluar</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- The bar is 77 px tall; pages that opt in start under it (see $headerOverlay). --}}
        <main @class(['flex-1', '-mt-[77px]' => $headerOverlay])>
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
