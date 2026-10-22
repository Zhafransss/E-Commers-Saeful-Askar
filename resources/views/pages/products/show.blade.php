@extends('layouts.app')

{{--
    $item is the CAT-03 object and $related a list of CAT-02 objects, both read by
    ProductPageController from the local catalog, so title, description, images, and
    structured data are in the HTML. Alpine handles the variant choice; "Tambah ke
    keranjang" calls CART-02 through Alpine.store('cart').
--}}
@php
    $firstInStock = collect($item['variants'])->firstWhere('in_stock', true) ?? $item['variants'][0];
    $isBattery = $item['category']['slug'] === 'baterai';
    $waAsk = 'https://api.whatsapp.com/send?phone=6282227222971&text='.rawurlencode("Halo min, saya mau tanya produk {$item['name']} di website.");

    // SEO: "<name> – <category>" unless the name already says what it is.
    $seoName = $item['name'];
    $seoCategory = $item['category']['name'] ?? null;
    $seoTitle = $seoCategory && ! \Illuminate\Support\Str::contains($seoName, $seoCategory, ignoreCase: true) ? "{$seoName} – {$seoCategory}" : $seoName;
    $seoText = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($item['description_html'] ?? ''))));
    $seoImage = $item['images'][0]['url'] ?? null;

    // Columns that may be NULL in the ERD (brand, dimensions, weight, description) render as "—" or are skipped.
    $grams = fn (?int $g) => $g === null ? '—' : ($g >= 1000 ? rtrim(rtrim(number_format($g / 1000, 1, ',', '.'), '0'), ',').' kg' : "{$g} g");
    $dimensions = array_filter($item['package_dimensions'] ?? [], fn ($v) => $v !== null);
@endphp

@section('title', $seoTitle)
@section('meta_description', $seoText !== '' ? \Illuminate\Support\Str::limit($seoText, 155) : "Beli {$seoName} di Saeful Askar, toko sepeda listrik Bekasi. Harga dan stok sesuai gudang, kirim ke seluruh Indonesia.")
@section('og_type', 'product')
@if ($seoImage)
    @section('og_image', $seoImage)
@endif

<x-seo-breadcrumbs :items="[
        ['Home', route('home')],
        ['Produk', route('products.index')],
        [$item['category']['name'], route('products.index', ['category' => $item['category']['slug']])],
        [$item['name'], route('products.show', $item['slug'])],
    ]" />

    {{-- Product rich result: price, availability, brand. No rating markup without real reviews. --}}
    @php
        $availability = $item['in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
        $offer = $item['price_min'] === $item['price_max']
            ? ['@type' => 'Offer', 'price' => $item['price_min'], 'priceCurrency' => 'IDR', 'availability' => $availability, 'itemCondition' => 'https://schema.org/NewCondition', 'url' => route('products.show', $item['slug']), 'seller' => ['@type' => 'Organization', 'name' => 'Saeful Askar']]
            : ['@type' => 'AggregateOffer', 'lowPrice' => $item['price_min'], 'highPrice' => $item['price_max'], 'offerCount' => count($item['variants']), 'priceCurrency' => 'IDR', 'availability' => $availability, 'itemCondition' => 'https://schema.org/NewCondition', 'url' => route('products.show', $item['slug']), 'seller' => ['@type' => 'Organization', 'name' => 'Saeful Askar']];
    @endphp
    <x-json-ld :data="array_filter([
        '@type' => 'Product',
        'name' => $seoName,
        'description' => \Illuminate\Support\Str::limit($seoText, 500),
        'image' => collect($item['images'])->pluck('url')->all() ?: null,
        'sku' => count($item['variants']) === 1 ? $item['variants'][0]['sku'] : null,
        'brand' => $item['brand_name'] ? ['@type' => 'Brand', 'name' => $item['brand_name']] : null,
        'category' => $item['category']['name'],
        'weight' => $firstInStock['package_weight'] ? ['@type' => 'QuantitativeValue', 'value' => $firstInStock['package_weight'], 'unitCode' => 'GRM'] : null,
        'offers' => $offer,
    ])" />

@section('content')
    <div class="border-b border-gray-200 bg-white">
        <nav class="mx-auto max-w-7xl px-4 py-3 text-xs text-gray-500 md:px-8" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-ink">Home</a>
            <span class="mx-1.5">/</span>
            <a href="{{ route('products.index') }}" class="hover:text-ink">Produk</a>
            <span class="mx-1.5">/</span>
            <a href="{{ route('products.index', ['category' => $item['category']['slug']]) }}" class="hover:text-ink">{{ $item['category']['name'] }}</a>
            <span class="mx-1.5">/</span>
            <span class="text-ink">{{ $item['name'] }}</span>
        </nav>
    </div>

    <section class="mx-auto max-w-7xl px-4 py-10 md:px-8"
             x-data="productDetail({
                productId: @js($item['id']),
                variants: @js($item['variants']),
                imageVariants: @js(collect($item['images'])->pluck('variant_id')),
                selected: @js((object) collect($firstInStock['variation_values'])->pluck('value', 'label')->all()),
                minQty: @js($item['min_qty'] ?? 1),
                maxQty: @js($item['max_qty']),
             })">
        <div class="grid gap-10 lg:grid-cols-2">
            {{-- Gallery --}}
            <div>
                <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white">
                    @forelse ($item['images'] as $index => $photo)
                        <img x-show="image === {{ $index }}" @if ($index > 0) x-cloak @endif
                             src="{{ $photo['url'] }}" alt="{{ $item['name'] }}" class="aspect-square w-full object-cover">
                    @empty
                        <x-product-image :alt="$item['name']" :icon="$isBattery ? 'battery' : 'part'" class="aspect-square" />
                    @endforelse
                </div>
                @if (count($item['images']) > 1)
                    <div class="mt-4 grid grid-cols-4 gap-3">
                        @foreach ($item['images'] as $index => $photo)
                            <button type="button" @click="image = {{ $index }}"
                                    class="overflow-hidden rounded-xl border-2 transition"
                                    :class="image === {{ $index }} ? 'border-brand' : 'border-transparent opacity-70 hover:opacity-100'"
                                    aria-label="Foto {{ $index + 1 }}">
                                <img src="{{ $photo['thumbnail_url'] }}" alt="" class="aspect-square w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Purchase panel --}}
            <div class="lg:pt-4">
                <p class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ implode(' · ', array_filter([$item['brand_name'] ?? null, $item['category']['name']])) }}</p>
                <h1 class="mt-2 text-3xl font-bold leading-tight sm:text-4xl">{{ $item['name'] }}</h1>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <p class="text-3xl font-bold tabular-nums" x-text="variant ? $format.formatRupiah(variant.price) : ''">
                        <x-price :min="$firstInStock['price']" />
                    </p>
                    <template x-if="variant && variant.in_stock">
                        <span class="rounded-full bg-ink px-3 py-1 text-xs font-semibold text-white">Stok <span x-text="variant.available_qty"></span></span>
                    </template>
                    <template x-if="variant && ! variant.in_stock">
                        <span class="rounded-full bg-gray-200 px-3 py-1 text-xs font-semibold text-gray-600">Stok habis</span>
                    </template>
                </div>
                @if ($item['price_min'] !== $item['price_max'])
                    <p class="mt-1 text-sm text-gray-500">Harga tergantung varian: <x-price :min="$item['price_min']" :max="$item['price_max']" class="font-semibold" /></p>
                @endif

                <div class="my-8 h-px bg-gray-200"></div>

                @if ($item['variations'])
                    <x-variant-picker :variations="$item['variations']" :variants="$item['variants']" class="mb-8" />
                @endif

                <div>
                    <p class="mb-2 text-sm font-semibold">Jumlah</p>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="inline-flex items-center rounded-full border-2 border-gray-300 bg-white">
                            <button type="button" class="grid h-11 w-11 place-items-center text-lg font-semibold hover:text-brand-hover disabled:text-gray-300"
                                    @click="qty--" :disabled="qty <= {{ $item['min_qty'] ?? 1 }}" aria-label="Kurangi">−</button>
                            <span class="w-10 text-center font-semibold tabular-nums" x-text="qty">1</span>
                            <button type="button" class="grid h-11 w-11 place-items-center text-lg font-semibold hover:text-brand-hover disabled:text-gray-300"
                                    @click="qty++" :disabled="qty >= maxQty" aria-label="Tambah">+</button>
                        </div>
                        <div class="text-xs text-gray-500">
                            @if ($item['max_qty'])
                                <p>Maksimal {{ $item['max_qty'] }} {{ strtolower($item['sell_unit'] ?? 'unit') }} per pesanan</p>
                            @endif
                            <p x-show="variant && variant.in_stock">Stok tersedia <span class="font-semibold text-ink" x-text="variant?.available_qty"></span></p>
                            <p x-show="variantInCart > 0">Sudah ada <span class="font-semibold text-ink" x-text="variantInCart"></span> di keranjang</p>
                            <p x-cloak x-show="maxQty > 0 && qty >= maxQty" class="font-medium text-amber-700">Jumlah sudah maksimal sesuai stok</p>
                        </div>
                    </div>
                </div>

                <p x-cloak x-show="error" x-text="error" role="alert" class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"></p>

                <div x-cloak x-show="added" x-transition role="status"
                     class="mt-6 flex items-center justify-between gap-3 rounded-2xl border border-brand bg-brand-soft p-4 text-sm">
                    <p><span class="font-semibold">Ditambahkan ke keranjang:</span> <span x-text="added"></span></p>
                    <a href="{{ route('cart') }}" class="shrink-0 font-semibold underline decoration-2 underline-offset-4">Lihat keranjang</a>
                </div>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    {{-- An admin account runs the shop and has no cart --}}
                    @unless (auth()->user()?->isAdmin())
                    <button type="button" @click="addToCart()" :disabled="blockedReason !== null || adding"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-full bg-brand px-8 py-4 font-semibold text-ink transition hover:bg-brand-hover disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-500">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.3" /><circle cx="18" cy="20" r="1.3" /><path d="M2.5 3h2.6l2.4 12.2a1.5 1.5 0 0 0 1.5 1.2h8.9a1.5 1.5 0 0 0 1.5-1.1L21.5 8H6" /></svg>
                        <span x-text="adding ? 'Menambahkan…' : (blockedReason ?? 'Tambah ke keranjang')">Tambah ke keranjang</span>
                    </button>
                    @endunless
                    <a href="{{ $waAsk }}" target="_blank" rel="noopener"
                       class="inline-flex items-center justify-center rounded-full border-2 border-ink px-6 py-4 font-semibold transition hover:bg-ink hover:text-brand">
                        Tanya via WhatsApp
                    </a>
                </div>

                <dl class="mt-8 grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-gray-200 bg-gray-200 text-sm">
                    <div class="bg-white p-4"><dt class="text-xs text-gray-500">SKU</dt><dd class="mt-1 font-semibold" x-text="variant?.sku">{{ $firstInStock['sku'] }}</dd></div>
                    <div class="bg-white p-4"><dt class="text-xs text-gray-500">Berat</dt><dd class="mt-1 font-semibold" x-text="variant ? $format.formatWeight(variant.package_weight) : ''">{{ $grams($firstInStock['package_weight']) }}</dd></div>
                    <div class="bg-white p-4"><dt class="text-xs text-gray-500">Dimensi paket</dt><dd class="mt-1 font-semibold">{{ count($dimensions) === 3 ? implode(' × ', $dimensions).' cm' : '—' }}</dd></div>
                    <div class="bg-white p-4"><dt class="text-xs text-gray-500">Dikirim dari</dt><dd class="mt-1 font-semibold">Tambun Utara, Bekasi</dd></div>
                </dl>
            </div>
        </div>
    </section>

    {{-- Description --}}
    <section class="border-y border-gray-200 bg-white">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 md:px-8 lg:grid-cols-[1fr_20rem]">
            <div>
                <h2 class="text-2xl font-bold">Deskripsi produk</h2>
                @if (trim(strip_tags((string) ($item['description_html'] ?? ''))) !== '')
                    <div class="rich-text mt-6 max-w-3xl">{!! $item['description_html'] !!}</div>
                @else
                    <p class="mt-6 max-w-3xl text-gray-600">Deskripsi produk belum tersedia. <a href="{{ $waAsk }}" target="_blank" rel="noopener" class="font-semibold text-ink underline decoration-brand decoration-2 underline-offset-4">Tanyakan detailnya ke admin via WhatsApp</a>.</p>
                @endif
            </div>
            <aside class="h-fit space-y-4">
                <div class="rounded-2xl bg-ink p-6 text-white">
                    <h3 class="font-semibold text-brand">Belanja dengan tenang</h3>
                    <ul class="mt-4 space-y-3 text-sm text-gray-300">
                        <li class="flex gap-2"><span class="text-brand">✓</span> Stok sinkron dengan gudang</li>
                        <li class="flex gap-2"><span class="text-brand">✓</span> Pembayaran aman via DANA</li>
                        <li class="flex gap-2"><span class="text-brand">✓</span> Nomor resi tampil di halaman pesanan</li>
                        <li class="flex gap-2"><span class="text-brand">✓</span> After-sales service di toko kami</li>
                    </ul>
                </div>
                <a href="{{ route('service') }}" class="group flex items-center justify-between gap-3 rounded-2xl border border-gray-200 p-5 transition hover:border-ink">
                    <span>
                        <span class="block font-semibold">Butuh service?</span>
                        <span class="block text-sm text-gray-500">Service di rumah atau di toko</span>
                    </span>
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand text-ink transition group-hover:translate-x-1">&rarr;</span>
                </a>
            </aside>
        </div>
    </section>

    {{-- Related --}}
    <section class="mx-auto max-w-7xl px-4 py-16 md:px-8">
        <div class="flex items-end justify-between gap-4">
            <h2 class="text-2xl font-bold">Produk lainnya</h2>
            <a href="{{ route('products.index', ['category' => $item['category']['slug']]) }}" class="text-sm font-semibold underline decoration-brand decoration-2 underline-offset-4">Lihat semua</a>
        </div>
        <div class="mt-8 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
            @foreach ($related as $relatedProduct)
                <x-product-card :product="$relatedProduct" />
            @endforeach
        </div>
    </section>
@endsection
