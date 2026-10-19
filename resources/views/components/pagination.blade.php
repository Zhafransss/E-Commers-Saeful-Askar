@props(['current' => 1, 'last' => 1])

{{-- Page links from the contract meta block (current_page, last_page). Static for now. --}}
@if ($last > 1)
    <nav {{ $attributes->class('flex items-center justify-center gap-1') }} aria-label="Halaman">
        <a href="#" @class(['grid h-10 min-w-10 place-items-center rounded-lg border px-3 text-sm', 'pointer-events-none border-gray-200 text-gray-300' => $current <= 1, 'border-gray-300 text-ink hover:border-ink' => $current > 1])
           aria-label="Sebelumnya">&larr;</a>

        @for ($page = 1; $page <= $last; $page++)
            <a href="#" @class(['grid h-10 min-w-10 place-items-center rounded-lg px-3 text-sm font-semibold', 'bg-ink text-brand' => $page === $current, 'text-ink hover:bg-gray-100' => $page !== $current])
               @if ($page === $current) aria-current="page" @endif>{{ $page }}</a>
        @endfor

        <a href="#" @class(['grid h-10 min-w-10 place-items-center rounded-lg border px-3 text-sm', 'pointer-events-none border-gray-200 text-gray-300' => $current >= $last, 'border-gray-300 text-ink hover:border-ink' => $current < $last])
           aria-label="Berikutnya">&rarr;</a>
    </nav>
@endif
