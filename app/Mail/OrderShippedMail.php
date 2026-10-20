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
 * "Pesanan dikirim" with the tracking number (WH-05), sent through the queue.
 */
class OrderShippedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Pesanan {$this->order->order_number} sudah dikirim");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-shipped', with: [
            'order' => $this->order,
            'url' => route('account.orders.show', $this->order->order_number),
        ]);
    }
}
