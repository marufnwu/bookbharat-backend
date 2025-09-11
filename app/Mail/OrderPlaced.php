<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order Placed Successfully - #' . $this->data['order_number'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.placed',
            with: $this->data
        );
    }

    public function attachments(): array
    {
        return [];
    }
}