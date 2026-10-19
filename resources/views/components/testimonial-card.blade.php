@props(['review'])

{{-- One customer review (home preview and /testimoni). --}}
<figure {{ $attributes->class('flex flex-col gap-5 rounded-2xl border border-neutral-800 bg-neutral-900 p-7') }}>
    <div class="flex gap-1 text-brand" aria-label="{{ $review['rating'] }} dari 5 bintang">
        @for ($i = 0; $i < $review['rating']; $i++)
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9l-5.2 2.7 1-5.8L1.5 7.7l5.9-.9L10 1.5Z" /></svg>
        @endfor
    </div>
    <blockquote class="flex-1 leading-relaxed text-neutral-300">“{{ $review['text'] }}”</blockquote>
    <figcaption class="flex items-center gap-3 border-t border-neutral-800 pt-5">
        <span class="grid h-11 w-11 place-items-center rounded-full bg-brand font-bold text-ink">{{ mb_substr($review['name'], 0, 1) }}</span>
        <span>
            <span class="block font-semibold text-white">{{ $review['name'] }}</span>
            <span class="block text-sm text-neutral-500">{{ $review['city'] }} · {{ $review['product'] }}</span>
        </span>
    </figcaption>
</figure>
