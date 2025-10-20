<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmed extends Mailable
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
        return $this->subject('Order Confirmed - #' . $this->order->order_number)
                    ->view('emails.order.confirmation')
                    ->with([
                        'order' => $this->order,
                        'user' => $this->user,
                        'items' => $this->items,
                        'order_number' => $this->order->order_number,
                        'order_total' => $this->order->total_amount,
                    ]);
    }
}

