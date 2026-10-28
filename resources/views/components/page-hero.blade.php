@props(['eyebrow' => null, 'crumb' => null, 'image' => null])

{{-- Dark page header for the company pages (jasa service). --}}
<section class="relative overflow-hidden border-b border-neutral-800/50">
    @if ($image)
        <div class="absolute inset-0" aria-hidden="true">
            <img src="{{ $image }}" alt="" class="h-full w-full object-cover opacity-30">
            <div class="absolute inset-0 bg-gradient-to-r from-neutral-950 via-neutral-950/85 to-neutral-950/50"></div>
        </div>
    @endif
    <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-brand/10 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-7xl px-4 py-16 md:px-8 md:py-24">
        @if ($crumb)
            <nav class="mb-6 text-xs text-neutral-500" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="transition hover:text-brand">Home</a>
                <span class="mx-1.5">/</span>
                <span class="text-neutral-300">{{ $crumb }}</span>
            </nav>
        @endif
        @if ($eyebrow)
            <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-brand">{{ $eyebrow }}</p>
        @endif
        <h1 class="max-w-3xl text-4xl font-bold leading-tight md:text-5xl xl:text-6xl">{{ $slot }}</h1>
        @isset($intro)
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-neutral-300">{{ $intro }}</p>
        @endisset
        @isset($actions)
            <div class="mt-8 flex flex-wrap gap-4">{{ $actions }}</div>
        @endisset
    </div>
</section>
