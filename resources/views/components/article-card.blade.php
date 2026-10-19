@props(['article', 'dark' => false])

{{-- One item of the ART-01 list. `dark` matches the home page. --}}
<a href="{{ route('articles.show', $article['slug']) }}"
   {{ $attributes->class([
       'group flex flex-col overflow-hidden rounded-2xl border transition-all duration-300',
       'border-neutral-800 bg-neutral-900 hover:-translate-y-1 hover:border-neutral-600' => $dark,
       'border-gray-200 bg-white hover:border-ink hover:shadow-lg' => ! $dark,
   ]) }}>
    <div class="aspect-[16/9] overflow-hidden bg-ink-soft">
        <img src="{{ $article['image_url'] }}" alt="{{ $article['title'] }}" loading="lazy"
             class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
    </div>
    <div class="flex flex-1 flex-col gap-2 p-5">
        <time datetime="{{ $article['published_at'] }}" @class(['text-xs font-semibold uppercase tracking-widest', 'text-brand' => $dark, 'text-gray-500' => ! $dark])>
            {{ \Illuminate\Support\Carbon::parse($article['published_at'])->translatedFormat('j F Y') }}
        </time>
        <h3 @class(['text-lg font-semibold leading-snug', 'text-white' => $dark, 'text-ink' => ! $dark])>{{ $article['title'] }}</h3>
        <p @class(['line-clamp-2 text-sm', 'text-neutral-400' => $dark, 'text-gray-600' => ! $dark])>{{ $article['excerpt'] }}</p>
        <span @class(['mt-auto inline-flex items-center gap-1 pt-2 text-sm font-semibold', 'text-white' => $dark, 'text-ink' => ! $dark])>
            Baca selengkapnya
            <svg class="h-4 w-4 text-brand transition group-hover:translate-x-1" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-3.22-3.22a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.22-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" /></svg>
        </span>
    </div>
</a>
