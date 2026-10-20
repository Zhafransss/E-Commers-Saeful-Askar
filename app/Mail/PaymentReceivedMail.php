<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Pembayaran diterima" (docs/api/webhooks.md WH-01), sent through the queue.
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
}
