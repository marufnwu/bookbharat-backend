<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderShipped extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $user;
    public $tracking_number;
    public $tracking_url;

    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        $this->order = $data['order'];
        $this->user = $data['user'];
        $this->tracking_number = $data['tracking_number'] ?? $data['order']->tracking_number;
        $this->tracking_url = $data['tracking_url'] ?? null;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your Order Has Shipped - #' . $this->order->order_number)
                    ->view('emails.order.shipped')
                    ->with([
                        'order' => $this->order,
                        'user' => $this->user,
                        'tracking_number' => $this->tracking_number,
                        'tracking_url' => $this->tracking_url,
                        'order_number' => $this->order->order_number,
                    ]);
    }
}

