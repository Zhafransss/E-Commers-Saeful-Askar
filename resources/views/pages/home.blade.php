@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
    <x-page-placeholder title="Beranda" :endpoints="['CAT-01', 'CAT-02', 'ART-01']" />
@endsection
