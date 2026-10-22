<?php

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * The invoice of an order as a PDF (admin screen now; the same file can be attached to an email later).
 * Built from the order alone: address, items and prices are the snapshots taken at checkout, so an invoice
 * does not change when the catalog does.
 */
class InvoicePdf
{
    /** "INV-WEB-000006": one invoice per order, numbered after it. */
    public static function number(Order $order): string
    {
        return 'INV-'.$order->order_number;
    }

    public static function filename(Order $order): string
    {
        return strtolower(self::number($order)).'.pdf';
    }

    /**
     * @return string The PDF file's bytes
     */
    public function render(Order $order): string
    {
        $order->loadMissing(['user', 'items', 'payment']);

        $html = view('admin.invoice', [
            'order' => $order,
            'number' => self::number($order),
            'store' => require resource_path('static/store.php'),
            'paid' => in_array($order->status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed], true),
        ])->render();

        $dompdf = new Dompdf($this->options());
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function options(): Options
    {
        // Font cache and temp files go to storage/, which is writable on shared hosting (vendor/ may not be).
        $directory = storage_path('app/dompdf');
        is_dir($directory) || @mkdir($directory, 0775, true);

        return new Options([
            'defaultFont' => 'DejaVu Sans',
            'fontDir' => $directory,
            'fontCache' => $directory,
            'tempDir' => $directory,
            'chroot' => [base_path()],
            'isRemoteEnabled' => false,
        ]);
    }
}
