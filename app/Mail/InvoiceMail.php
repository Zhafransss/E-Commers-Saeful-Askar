<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\Admin\InvoicePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The invoice of an order, as a PDF attachment, sent from the admin screen (ADM-24) through the queue. The PDF is
 * made when the mail is sent, from the order as it is then. The automatic mail after a payment carries the same
 * PDF on PaymentReceivedMail, so a customer does not get two mails for one payment.
 */
class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invoice pesanan '.$this->order->order_number);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice', with: [
            'order' => $this->order,
            'number' => InvoicePdf::number($this->order),
            'url' => route('account.orders.show', $this->order->order_number),
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [InvoiceAttachment::for($this->order)];
    }
}
