@props(['min', 'max' => null])

{{-- Integer rupiah (contract "Aturan data"); a range when variants differ in price. --}}
@php
    $rupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
@endphp

<span {{ $attributes->class('font-bold tabular-nums') }}>
    <span class="whitespace-nowrap">{{ $rupiah($min) }}</span>@if ($max && $max !== $min) <span class="whitespace-nowrap"><span class="font-normal text-gray-400">– </span>{{ $rupiah($max) }}</span>@endif
</span>
