@extends('layouts.app')

@section('title', 'Pesanan Saya')
@section('robots', 'noindex, nofollow')

@section('content')
    <x-page-placeholder title="Pesanan Saya" :endpoints="['ORD-02']" />
@endsection
