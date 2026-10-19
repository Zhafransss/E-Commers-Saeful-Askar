<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Admin panel pages; all data comes from /api/v1/admin/* (docs/api/admin.md).
 */
class AdminPageController extends Controller
{
    public function orders(): View
    {
        return view('admin.orders.index');
    }

    public function sync(): View
    {
        return view('admin.sync.index');
    }
}
