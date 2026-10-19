@extends('layouts.app')

@section('title', 'Atur Ulang Password')
@section('robots', 'noindex, follow')

@section('content')
    {{-- $token and $email come from the AUTH-08 email link and are sent back to AUTH-09. --}}
    <x-page-placeholder title="Atur Ulang Password" :endpoints="['AUTH-09']">
        <p class="mt-2 text-sm text-gray-600">Email: <code>{{ $email }}</code></p>
    </x-page-placeholder>
@endsection
