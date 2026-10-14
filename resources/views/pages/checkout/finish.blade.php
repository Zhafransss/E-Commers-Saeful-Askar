@extends('layouts.app')

@section('title', 'Status Pembayaran')

@section('content')
    {{-- Poll ORD-03 every 3 s until status leaves pending_payment or 60 s pass (checkout-orders.md). --}}
    <x-page-placeholder title="Status Pembayaran" :endpoints="['ORD-03']">
        <p class="mt-2 text-sm text-gray-600">Pesanan: <code>{{ $orderNumber }}</code>@if ($failed) · pembayaran gagal @endif</p>
    </x-page-placeholder>
@endsection
