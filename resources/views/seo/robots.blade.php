@if (app()->isProduction())
User-agent: *
Disallow: /cart
Disallow: /checkout
Disallow: /account
Disallow: /admin
Disallow: /api/
Disallow: /webhooks/

Sitemap: {{ route('sitemap') }}
@else
# Not production (APP_ENV={{ app()->environment() }}): keep test copies of the site out of search engines.
User-agent: *
Disallow: /
@endif
