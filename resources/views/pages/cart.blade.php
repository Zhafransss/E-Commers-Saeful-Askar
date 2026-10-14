@extends('layouts.app')

@section('title', 'Keranjang')

@section('content')
    <x-page-placeholder title="Keranjang" :endpoints="['CART-01', 'CART-03', 'CART-04', 'CART-05']" />
@endsection
