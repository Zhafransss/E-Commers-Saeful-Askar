{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
{{-- /sitemap.xml: public pages, category views, and every visible product and published article. --}}
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
@foreach ($articles as $article)
    <url>
        <loc>{{ route('articles.show', $article->slug) }}</loc>
        <lastmod>{{ $article->updated_at?->toAtomString() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
@endforeach
</urlset>
