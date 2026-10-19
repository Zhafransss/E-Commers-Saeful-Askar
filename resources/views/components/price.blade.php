@props(['min', 'max' => null])

{{-- Integer rupiah (contract "Aturan data"); a range when variants differ in price. --}}
@php
    $rupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
@endphp

<span {{ $attributes->class('font-bold tabular-nums') }}>
    {{ $rupiah($min) }}@if ($max && $max !== $min)<span class="font-normal text-gray-400"> – </span>{{ $rupiah($max) }}@endif
</span>
