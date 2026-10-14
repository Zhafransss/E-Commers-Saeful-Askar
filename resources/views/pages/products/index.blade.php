@extends('layouts.app')

@section('title', 'Produk')

@section('content')
    <x-page-placeholder title="Produk" :endpoints="['CAT-01', 'CAT-02']" />
@endsection
