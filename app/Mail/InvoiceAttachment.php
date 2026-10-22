<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\Admin\InvoicePdf;
use Illuminate\Mail\Mailables\Attachment;

/**
 * The invoice PDF as an email attachment; shared by InvoiceMail and PaymentReceivedMail. The PDF is built here,
 * so a failure surfaces to the caller instead of halfway through sending.
 */
class InvoiceAttachment
{
    public static function for(Order $order): Attachment
    {
        $pdf = app(InvoicePdf::class)->render($order);

        return Attachment::fromData(fn () => $pdf, InvoicePdf::filename($order))->withMime('application/pdf');
    }
}
