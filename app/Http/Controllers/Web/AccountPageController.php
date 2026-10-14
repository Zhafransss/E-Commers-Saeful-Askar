<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AccountPageController extends Controller
{
    public function orders(): View
    {
        return view('pages.account.orders.index');
    }

    public function orderShow(string $orderNumber): View
    {
        return view('pages.account.orders.show', compact('orderNumber'));
    }

    public function addresses(): View
    {
        return view('pages.account.addresses');
    }
}
