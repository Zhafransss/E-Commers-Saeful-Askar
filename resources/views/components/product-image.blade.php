@props(['src' => null, 'alt' => '', 'icon' => 'battery'])

{{-- Product photo, or a branded tile when the item has no image (e.g. batteries). --}}
<div {{ $attributes->class('overflow-hidden bg-ink-soft') }}>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" class="h-full w-full object-cover">
    @else
        <div class="flex h-full w-full flex-col items-center justify-center gap-2 bg-[radial-gradient(circle_at_30%_20%,#2a2a2a,#0a0a0a_70%)] text-brand" role="img" aria-label="{{ $alt }}">
            @if ($icon === 'battery')
                <svg class="h-1/3 w-1/3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="7" width="18" height="10" rx="2" />
                    <path d="M22 11v2" />
                    <path d="m11 9-2 3h4l-2 3" />
                </svg>
            @elseif ($icon === 'part')
                <svg class="h-1/3 w-1/3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9l-3.8 3.8Z" />
                </svg>
            @else
                <svg class="h-1/3 w-1/3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="5.5" cy="17.5" r="3.5" /><circle cx="18.5" cy="17.5" r="3.5" /><path d="M15 6h2l3 11.5M5.5 17.5 9 10h7l-4 7.5H5.5M9 10 8 7H6" />
                </svg>
            @endif
        </div>
    @endif
</div>
