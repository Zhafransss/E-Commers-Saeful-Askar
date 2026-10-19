@props(['product'])

{{-- One item of the CAT-02 list. Used on the product list and the home page (first 8 products). --}}
<a href="{{ route('products.show', $product['slug']) }}"
   {{ $attributes->class('group flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white text-ink transition hover:-translate-y-0.5 hover:border-ink hover:shadow-lg') }}>
    <div class="relative">
        <x-product-image :src="$product['thumbnail_url']" :alt="$product['name']"
                         :icon="$product['category']['slug'] === 'baterai' ? 'battery' : 'part'"
                         class="aspect-[4/3] [&_img]:transition [&_img]:duration-300 group-hover:[&_img]:scale-105" />
        <x-stock-badge :qty="$product['total_available_qty']" :in-stock="$product['in_stock']" class="absolute left-3 top-3" />
        @unless ($product['in_stock'])
            <div class="absolute inset-0 bg-white/50"></div>
        @endunless
    </div>

    <div class="flex flex-1 flex-col gap-1 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $product['category']['name'] }}</p>
        <h3 class="line-clamp-2 font-semibold text-ink group-hover:underline group-hover:decoration-brand group-hover:decoration-2 group-hover:underline-offset-4">
            {{ $product['name'] }}
        </h3>
        <div class="mt-auto pt-3">
            <x-price :min="$product['price_min']" :max="$product['price_max']" class="text-sm text-ink sm:text-base" />
            @if ($product['variant_count'] > 1)
                <p class="mt-0.5 text-xs text-gray-500">{{ $product['variant_count'] }} varian</p>
            @endif
        </div>
    </div>
</a>
