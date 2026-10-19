@props(['data'])

{{-- Structured data (schema.org) for search engines, emitted in <head> via the layout's "schema" stack. --}}
@push('schema')
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org'] + $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush
