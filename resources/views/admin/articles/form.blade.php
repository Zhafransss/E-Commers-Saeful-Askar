@extends('layouts.admin')

@section('title', $articleId ? 'Ubah Artikel' : 'Tulis Artikel')

@section('content')
    <x-page-placeholder :title="$articleId ? 'Ubah Artikel' : 'Tulis Artikel'" :endpoints="$articleId ? ['ADM-04', 'ADM-05', 'ADM-08'] : ['ADM-03', 'ADM-08']">
        @if ($articleId)
            <p class="mt-2 text-sm text-gray-600">ID artikel: <code>{{ $articleId }}</code></p>
        @endif
    </x-page-placeholder>
@endsection
