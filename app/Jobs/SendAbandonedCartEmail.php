<?php

namespace App\Jobs;

use App\Models\PersistentCart;
use App\Models\EmailTemplate;
use App\Models\CartRecoveryDiscount;
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
        $discountConfig = $this->getDiscountForEmailType($this->emailType);

        // Generate discount code if applicable
        $discountCode = null;
        if ($discountConfig && $cart->total_amount >= \App\Models\AdminSetting::get('recovery_min_cart_value', 0)) {
            $discountCode = $this->generateAndSaveDiscount($cart, $discountConfig);
        }

        // Update cart recovery probability and segment
        $cart->update([
            'recovery_probability' => $cart->calculateRecoveryProbability(),
            'customer_segment' => $cart->determineSegment(),
        ]);

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
                'discount' => $discountConfig,
                'discount_code' => $discountCode,
                'email_type' => $this->emailType,
                'template' => $template,
                'recovery_probability' => $cart->recovery_probability,
                'customer_segment' => $cart->customer_segment,
            ]));

            Log::info("Abandoned cart email sent successfully", [
                'cart_id' => $this->cartId,
                'user_id' => $cart->user_id,
                'email_type' => $this->emailType,
                'discount_code' => $discountCode,
                'recovery_probability' => $cart->recovery_probability,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to send abandoned cart email", [
                'cart_id' => $this->cartId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get discount configuration for email type
     */
    protected function getDiscountForEmailType($emailType)
    {
        return match($emailType) {
            'first_reminder' => null, // No discount on first email
            'second_reminder' => [
                'type' => 'percentage',
                'value' => (int) \App\Models\AdminSetting::get('recovery_discount_second_email', 5)
            ],
            'final_reminder' => [
                'type' => 'percentage',
                'value' => (int) \App\Models\AdminSetting::get('recovery_discount_final_email', 10)
            ],
            default => null,
        };
    }

    /**
     * Generate and save discount code for cart recovery
     */
    protected function generateAndSaveDiscount($cart, $discountConfig): ?string
    {
        try {
            $code = CartRecoveryDiscount::generateCode();

            $discount = CartRecoveryDiscount::create([
                'persistent_cart_id' => $cart->id,
                'code' => $code,
                'type' => $discountConfig['type'],
                'value' => $discountConfig['value'],
                'min_purchase_amount' => $cart->total_amount * 0.8, // Must buy 80% of original cart
                'max_discount_amount' => $cart->total_amount * 0.15, // Cap discount at 15% of cart
                'valid_until' => now()->addDays(7), // Code valid for 7 days
                'max_usage_count' => 1, // One-time use
            ]);

            Log::info("Cart recovery discount code generated", [
                'cart_id' => $cart->id,
                'discount_code' => $code,
                'discount_id' => $discount->id,
            ]);

            return $code;
        } catch (\Exception $e) {
            Log::error("Failed to generate discount code", [
                'cart_id' => $cart->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
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
