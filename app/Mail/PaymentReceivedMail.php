<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * "Pembayaran diterima" (docs/api/webhooks.md WH-01), sent through the queue, with the invoice PDF attached:
 * this is the automatic invoice mail of a paid order.
 */
class PaymentReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Pembayaran pesanan {$this->order->order_number} diterima");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.payment-received', with: [
            'order' => $this->order->loadMissing('items'),
            'url' => route('account.orders.show', $this->order->order_number),
        ]);
    }

    /**
     * The invoice, when it can be made. The payment confirmation matters more than its attachment, so a PDF that
     * fails to build is logged and the mail goes out without it (an admin can send the invoice again).
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        try {
            return [InvoiceAttachment::for($this->order)];
        } catch (\Throwable $e) {
            Log::warning("Invoice PDF for {$this->order->order_number} could not be attached: {$e->getMessage()}");

            return [];
        }
    }
}
