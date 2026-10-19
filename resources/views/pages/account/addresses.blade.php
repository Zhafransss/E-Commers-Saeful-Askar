@extends('layouts.app')

@section('title', 'Alamat Saya')
@section('robots', 'noindex, nofollow')

@section('content')
    <x-page-placeholder title="Alamat Saya" :endpoints="['ADDR-01', 'ADDR-02', 'ADDR-03', 'ADDR-04', 'ADDR-05', 'ADDR-06', 'REG-01', 'REG-02', 'REG-03', 'REG-04']" />
@endsection
