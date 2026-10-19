{{-- The declaration is split so PHP with short_open_tag=On does not read "<?xml" as a PHP tag. --}}
{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
{{-- /sitemap.xml: public pages, category views, and every visible product. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($pages as [$url, $priority, $changefreq])
    <url>
        <loc>{{ $url }}</loc>
        <changefreq>{{ $changefreq }}</changefreq>
        <priority>{{ $priority }}</priority>
    </url>
@endforeach
@foreach ($products as $product)
    <url>
        <loc>{{ route('products.show', $product->slug) }}</loc>
        <lastmod>{{ $product->updated_at?->toAtomString() }}</lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
@endforeach
</urlset>
