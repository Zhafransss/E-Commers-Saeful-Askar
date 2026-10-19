{{--
    Alpine twin of <x-product-card>, for lists loaded in the browser (the product list).
    Must sit inside a x-for over CAT-02 objects named `product`, in a component that has
    iconFor(product). Keep the markup in step with product-card.blade.php.
--}}
<a :href="`{{ url('/products') }}/${encodeURIComponent(product.slug)}`"
   {{ $attributes->class('group flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white text-ink transition hover:-translate-y-0.5 hover:border-ink hover:shadow-lg') }}>
    <div class="relative">
        <div class="aspect-[4/3] overflow-hidden bg-ink-soft">
            <template x-if="product.thumbnail_url">
                <img :src="product.thumbnail_url" :alt="product.name" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
            </template>
            <template x-if="! product.thumbnail_url">
                <div class="flex h-full w-full items-center justify-center bg-[radial-gradient(circle_at_30%_20%,#2a2a2a,#0a0a0a_70%)] text-brand" role="img" :aria-label="product.name">
                    <svg x-show="iconFor(product) === 'battery'" class="h-1/3 w-1/3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="18" height="10" rx="2" /><path d="M22 11v2" /><path d="m11 9-2 3h4l-2 3" /></svg>
                    <svg x-show="iconFor(product) !== 'battery'" class="h-1/3 w-1/3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9l-3.8 3.8Z" /></svg>
                </div>
            </template>
        </div>
        {{-- Stock label: out of stock, low (≤ 3), or available --}}
        <span class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
              :class="! product.in_stock || product.total_available_qty <= 0 ? 'bg-gray-200 text-gray-600' : product.total_available_qty <= 3 ? 'bg-brand text-ink' : 'bg-ink text-white'"
              x-text="! product.in_stock || product.total_available_qty <= 0 ? 'Stok habis' : product.total_available_qty <= 3 ? `Sisa ${product.total_available_qty}` : 'Tersedia'"></span>
        <div x-show="! product.in_stock" class="absolute inset-0 bg-white/50"></div>
    </div>

    <div class="flex flex-1 flex-col gap-1 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500" x-text="product.category?.name"></p>
        <h3 class="line-clamp-2 font-semibold text-ink group-hover:underline group-hover:decoration-brand group-hover:decoration-2 group-hover:underline-offset-4" x-text="product.name"></h3>
        <div class="mt-auto pt-3">
            <p class="text-sm font-bold tabular-nums text-ink sm:text-base">
                <span x-text="$format.formatRupiah(product.price_min)"></span><template x-if="product.price_max && product.price_max !== product.price_min"><span><span class="font-normal text-gray-400"> – </span><span x-text="$format.formatRupiah(product.price_max)"></span></span></template>
            </p>
            <p x-show="product.variant_count > 1" class="mt-0.5 text-xs text-gray-500"><span x-text="product.variant_count"></span> varian</p>
        </div>
    </div>
</a>
