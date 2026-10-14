@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
    <x-page-placeholder title="Checkout" :endpoints="['ADDR-01', 'SHIP-01', 'ORD-01']" />
@endsection
