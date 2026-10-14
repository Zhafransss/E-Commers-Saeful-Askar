@extends('layouts.admin')

@section('title', 'Artikel')

@section('content')
    <x-page-placeholder title="Artikel" :endpoints="['ADM-02', 'ADM-06', 'ADM-07']" />
@endsection
