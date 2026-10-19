<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Storefront;
use Illuminate\View\View;

/**
 * Product pages. The list is loaded by Alpine from CAT-01/CAT-02. The detail page is
 * rendered here from the same data as CAT-03, so title, description, structured data,
 * and images are in the HTML for search engines (PRD NFR SEO); Alpine only handles
 * the variant choice and the cart (CART-02).
 */
class ProductPageController extends Controller
{
    public function index(): View
    {
        return view('pages.products.index');
    }

    public function show(string $slug): View
    {
        $item = Storefront::detail($slug);

        abort_if($item === null, 404);

        return view('pages.products.show', [
            'item' => $item,
            'related' => Storefront::summaries(4, $item['category']['id'] ?? null, $item['id']),
        ]);
    }
}
