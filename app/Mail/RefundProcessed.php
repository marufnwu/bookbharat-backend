<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RefundProcessed extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $user;
    public $refund_amount;
    public $refund_method;

    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        $this->order = $data['order'];
        $this->user = $data['user'];
        $this->refund_amount = $data['refund_amount'] ?? $data['order']->total_amount;
        $this->refund_method = $data['refund_method'] ?? 'Original payment method';
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Refund Processed - Order #' . $this->order->order_number)
                    ->view('emails.order.status_update')
                    ->with([
                        'order' => $this->order,
                        'user' => $this->user,
                        'order_number' => $this->order->order_number,
                        'new_status' => 'refunded',
                        'status_message' => "Your refund of ₹{$this->refund_amount} has been processed to {$this->refund_method}. It may take 5-7 business days to reflect in your account.",
                        'refund_amount' => $this->refund_amount,
                        'refund_method' => $this->refund_method,
                    ]);
    }
}

