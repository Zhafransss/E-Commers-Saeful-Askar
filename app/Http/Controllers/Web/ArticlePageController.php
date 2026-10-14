<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\View\View;

/**
 * Article pages. Data is loaded by Alpine from ART-01/ART-02; the detail page
 * also reads the article here for SEO and Open Graph tags (ART-02).
 */
class ArticlePageController extends Controller
{
    public function index(): View
    {
        return view('pages.articles.index');
    }

    public function show(string $slug): View
    {
        $article = Article::published()->where('slug', $slug)->first();

        abort_if(! $article && ! config('app.api_mock'), 404);

        return view('pages.articles.show', compact('slug', 'article'));
    }
}
