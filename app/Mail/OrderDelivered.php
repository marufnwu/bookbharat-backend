<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderDelivered extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $user;
    public $items;

    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        $this->order = $data['order'];
        $this->user = $data['user'];
        $this->items = $data['order']->items ?? [];
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Order Delivered - #' . $this->order->order_number)
                    ->view('emails.order.delivered')
                    ->with([
                        'order' => $this->order,
                        'user' => $this->user,
                        'items' => $this->items,
                        'order_number' => $this->order->order_number,
                        'delivered_at' => $this->order->delivered_at,
                    ]);
    }
}

