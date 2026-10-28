<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\InvoicePdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function users(): View
    {
        return view('admin.users.index');
    }

    public function admins(): View
    {
        return view('admin.admins.index');
    }

    public function products(): View
    {
        return view('admin.products.index');
    }

    public function shipments(): View
    {
        return view('admin.shipments.index');
    }

    /**
     * The invoice PDF of an order, opened in the browser (or downloaded with ?download=1). A web route rather
     * than an API one so a plain link works with the session cookie.
     */
    public function invoice(Request $request, string $orderNumber, InvoicePdf $invoices): Response
    {
        $order = Order::where('order_number', $orderNumber)->first() ?? abort(404);

        return response($invoices->render($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.InvoicePdf::filename($order).'"',
        ]);
    }
}
