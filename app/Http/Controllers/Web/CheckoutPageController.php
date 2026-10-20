<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutPageController extends Controller
{
    public function index(): View
    {
        return view('pages.checkout.index');
    }

    /**
     * Return page from DANA (Gapura): /checkout/finish?order=WEB-000123 (INT-GP-01, urlParams PAY_RETURN).
     * The page polls ORD-03 until the order leaves pending_payment.
     */
    public function finish(Request $request): View
    {
        return view('pages.checkout.finish', [
            'orderNumber' => (string) $request->query('order'),
            'failed' => $request->boolean('failed'),
        ]);
    }
}
