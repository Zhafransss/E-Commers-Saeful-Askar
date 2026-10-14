<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Product pages. Data is loaded by Alpine from CAT-02/CAT-03; the detail page
 * also reads the product here for the <title> and meta description (PRD NFR SEO).
 */
class ProductPageController extends Controller
{
    public function index(): View
    {
        return view('pages.products.index');
    }

    public function show(string $slug): View
    {
        $product = Product::visible()->where('slug', $slug)->first();

        // In mock mode the slug may exist only in the frontend mocks.
        abort_if(! $product && ! config('app.api_mock'), 404);

        return view('pages.products.show', compact('slug', 'product'));
    }
}
