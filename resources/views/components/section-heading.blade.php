@props(['eyebrow' => null, 'center' => true, 'flush' => false])

{{-- Yellow eyebrow + large title + optional intro, the saefulaskar.com section header. `flush` drops the bottom margin. --}}
<div {{ $attributes->class(['mb-12' => ! $flush, 'text-center' => $center]) }}>
    @if ($eyebrow)
        <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-brand">{{ $eyebrow }}</p>
    @endif
    <h2 class="text-3xl font-bold text-white md:text-4xl xl:text-5xl">{{ $slot }}</h2>
    @isset($intro)
        <p @class(['mt-3 text-neutral-400', 'mx-auto max-w-lg' => $center])>{{ $intro }}</p>
    @endisset
</div>
