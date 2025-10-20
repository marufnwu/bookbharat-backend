<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AbandonedCartMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $cart;
    public $cartItems;
    public $recoveryUrl;
    public $discount;
    public $emailType;

    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        $this->user = $data['user'];
        $this->cart = $data['cart'];
        $this->cartItems = $data['cart_items'] ?? [];
        $this->recoveryUrl = $data['recovery_url'];
        $this->discount = $data['discount'];
        $this->emailType = $data['email_type'];
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = match($this->emailType) {
            'first_reminder' => 'You left items in your cart',
            'second_reminder' => 'Complete your order & save 5%',
            'final_reminder' => 'Last chance! Save 10% on your cart',
            default => 'Your cart is waiting for you',
        };

        return $this->subject($subject)
                    ->view('emails.abandoned_cart')
                    ->with([
                        'user' => $this->user,
                        'cart' => $this->cart,
                        'cart_items' => $this->cartItems,
                        'recovery_url' => $this->recoveryUrl,
                        'discount' => $this->discount,
                        'email_type' => $this->emailType,
                        'has_discount' => $this->discount !== null,
                    ]);
    }
}

