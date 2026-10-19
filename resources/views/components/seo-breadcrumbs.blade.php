@props(['items'])

{{-- BreadcrumbList structured data. $items: [[name, url], …] from Home to the current page. --}}
<x-json-ld :data="[
    '@type' => 'BreadcrumbList',
    'itemListElement' => collect($items)->values()->map(fn ($item, $i) => [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $item[0],
        'item' => $item[1],
    ])->all(),
]" />
