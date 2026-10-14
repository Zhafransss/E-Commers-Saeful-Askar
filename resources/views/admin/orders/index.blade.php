@extends('layouts.admin')

@section('title', 'Pesanan')

@section('content')
    <x-page-placeholder title="Pesanan" :endpoints="['ADM-09', 'ADM-10', 'ADM-11', 'ADM-12']" />
@endsection
