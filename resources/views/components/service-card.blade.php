@props(['service'])

{{-- One service option from static/store.php `services` (home preview and /jasa-service). --}}
<div {{ $attributes->class('flex flex-col gap-5 rounded-2xl border border-neutral-800 bg-neutral-900 p-8 transition hover:border-neutral-600') }}>
    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand/10 text-brand">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $service['icon'] }}" /></svg>
    </div>
    <div>
        <h3 class="text-xl font-bold text-white">{{ $service['title'] }}</h3>
        <p class="mt-2 text-sm text-neutral-400">{{ $service['text'] }}</p>
    </div>
    <ul class="space-y-3">
        @foreach ($service['points'] as $point)
            <li class="flex items-center gap-3 text-neutral-300">
                <span class="grid h-5 w-5 place-items-center rounded-full bg-brand/15 text-brand">
                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                </span>
                {{ $point }}
            </li>
        @endforeach
    </ul>
    <a href="{{ $service['wa'] }}" target="_blank" rel="noopener"
       class="mt-auto inline-flex h-11 items-center justify-center rounded-full bg-brand px-6 text-sm font-semibold text-ink transition hover:scale-[1.02] hover:bg-brand/90">
        Pesan {{ $service['title'] }}
    </a>
</div>
