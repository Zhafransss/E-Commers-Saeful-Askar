@extends('layouts.app')

@section('title', 'Detail Pesanan')
@section('robots', 'noindex, nofollow')

@section('content')
    <x-page-placeholder title="Detail Pesanan" :endpoints="['ORD-03', 'ORD-04', 'ORD-05']">
        <p class="mt-2 text-sm text-gray-600">Pesanan: <code>{{ $orderNumber }}</code></p>
    </x-page-placeholder>
@endsection
