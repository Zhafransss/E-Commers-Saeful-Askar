@props(['title', 'endpoints' => []])

{{-- Foundation placeholder: replace with the real page. Lists the contract codes this page consumes. --}}
<section class="mx-auto max-w-7xl px-4 py-16">
    <h1 class="text-2xl font-bold">{{ $title }}</h1>
    <p class="mt-2 text-sm text-gray-500">Halaman ini belum dibangun.</p>

    @if ($endpoints)
        <p class="mt-4 text-sm text-gray-600">
            Endpoint (docs/api):
            @foreach ($endpoints as $code)
                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $code }}</code>
            @endforeach
        </p>
    @endif

    {{ $slot }}
</section>
