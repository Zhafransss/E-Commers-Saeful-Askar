<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Storefront;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.home', ['featured' => Storefront::summaries(8)]);
    }
}
