<?php

namespace App\Jobs;

use App\Models\PersistentCart;
use App\Models\EmailTemplate;
use App\Mail\AbandonedCartMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendAbandonedCartEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $cartId;
    protected $emailType;
    protected $attempts = 3;

    public function __construct($cartId, $emailType = 'first_reminder')
    {
        $this->cartId = $cartId;
        $this->emailType = $emailType;
    }

    public function handle(): void
    {
        $cart = PersistentCart::with('user')->find($this->cartId);
        
        if (!$cart || !$cart->user) {
            Log::info("Abandoned cart email skipped - cart or user not found", [
                'cart_id' => $this->cartId
            ]);
            return;
        }

        // Check if cart is still abandoned (not converted)
        if (!$cart->is_abandoned) {
            Log::info("Abandoned cart email skipped - cart no longer abandoned", [
                'cart_id' => $this->cartId
            ]);
            return;
        }

        // Check if user has made a recent purchase
        $recentOrder = $cart->user->orders()
            ->where('created_at', '>', $cart->last_activity)
            ->first();

        if ($recentOrder) {
            Log::info("Abandoned cart email skipped - user made recent purchase", [
                'cart_id' => $this->cartId,
                'user_id' => $cart->user_id
            ]);
            return;
        }

        // Get email template
        $template = EmailTemplate::where('type', 'abandoned_cart')
            ->where('is_active', true)
            ->first();

        if (!$template) {
            Log::error("Abandoned cart email template not found");
            return;
        }

        // Determine discount based on email type
        $discount = $this->getDiscountForEmailType($this->emailType);
        
        // Generate recovery token
        $recoveryToken = \Str::random(32);
        $cart->update([
            'recovery_token' => $recoveryToken,
            'recovery_email_count' => $cart->recovery_email_count + 1,
            'last_recovery_email_sent' => now(),
        ]);

        // Parse cart items
        $cartItems = json_decode($cart->cart_data, true);
        $recoveryUrl = url("/cart/recover/{$recoveryToken}");

        // Send email
        try {
            Mail::to($cart->user->email)->send(new AbandonedCartMail([
                'user' => $cart->user,
                'cart' => $cart,
                'cart_items' => $cartItems,
                'recovery_url' => $recoveryUrl,
                'discount' => $discount,
                'email_type' => $this->emailType,
                'template' => $template,
            ]));

            Log::info("Abandoned cart email sent successfully", [
                'cart_id' => $this->cartId,
                'user_id' => $cart->user_id,
                'email_type' => $this->emailType
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to send abandoned cart email", [
                'cart_id' => $this->cartId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function getDiscountForEmailType($emailType)
    {
        return match($emailType) {
            'first_reminder' => null, // No discount
            'second_reminder' => ['type' => 'percentage', 'value' => 5],
            'final_reminder' => ['type' => 'percentage', 'value' => 10],
            default => null,
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Abandoned cart email job failed", [
            'cart_id' => $this->cartId,
            'email_type' => $this->emailType,
            'error' => $exception->getMessage()
        ]);
    }
}