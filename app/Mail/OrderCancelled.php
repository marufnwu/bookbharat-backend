<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderCancelled extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $user;
    public $cancellation_reason;

    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        $this->order = $data['order'];
        $this->user = $data['user'];
        $this->cancellation_reason = $data['cancellation_reason'] ?? 'Order cancelled as requested';
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Order Cancelled - #' . $this->order->order_number)
                    ->view('emails.order.status_update')
                    ->with([
                        'order' => $this->order,
                        'user' => $this->user,
                        'order_number' => $this->order->order_number,
                        'new_status' => 'cancelled',
                        'status_message' => $this->cancellation_reason,
                    ]);
    }
}

