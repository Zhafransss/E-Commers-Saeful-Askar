@props(['qty' => 0, 'inStock' => true])

{{-- Stock label from available_qty: out of stock, low (≤ 3), or available. --}}
@php
    [$label, $classes] = match (true) {
        ! $inStock || $qty <= 0 => ['Stok habis', 'bg-gray-200 text-gray-600'],
        $qty <= 3 => ["Sisa {$qty}", 'bg-brand text-ink'],
        default => ['Tersedia', 'bg-ink text-white'],
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold', $classes]) }}>
    {{ $label }}
</span>
