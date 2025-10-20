<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $user;
    public $failure_reason;
    public $retry_url;

    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        $this->order = $data['order'];
        $this->user = $data['user'];
        $this->failure_reason = $data['failure_reason'] ?? 'Payment could not be processed';
        $this->retry_url = $data['retry_url'] ?? config('app.frontend_url') . '/orders/' . $data['order']->id . '/retry-payment';
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Payment Failed - Order #' . $this->order->order_number)
                    ->view('emails.payment.confirmation')
                    ->with([
                        'order' => $this->order,
                        'user' => $this->user,
                        'order_number' => $this->order->order_number,
                        'payment_status' => 'failed',
                        'failure_reason' => $this->failure_reason,
                        'retry_url' => $this->retry_url,
                    ]);
    }
}

