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
 * "Pesanan dibatalkan" (WH-01 `05` and ORD-05), sent through the queue.
 */
class OrderCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Pesanan {$this->order->order_number} dibatalkan");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-cancelled', with: [
            'order' => $this->order,
            'url' => route('account.orders.show', $this->order->order_number),
        ]);
    }
}
