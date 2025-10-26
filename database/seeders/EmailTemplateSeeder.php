<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;
use App\Models\User;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Get admin user for created_by
        $admin = User::first();

        $templates = [
            'order_confirmation' => [
                'subject' => 'Order Confirmation - #{{order_id}}',
                'html_content' => $this->getOrderConfirmationHtml(),
                'text_content' => 'Your order #{{order_id}} has been confirmed.',
                'variables' => ['order_id', 'customer_name', 'order_total', 'order_items', 'shipping_address', 'payment_method'],
            ],
            'order_shipped' => [
                'subject' => 'Your order #{{order_id}} has been shipped!',
                'html_content' => $this->getOrderShippedHtml(),
                'text_content' => 'Your order has been shipped. Tracking: {{tracking_number}}',
                'variables' => ['order_id', 'customer_name', 'tracking_number', 'carrier', 'estimated_delivery'],
            ],
            'order_delivered' => [
                'subject' => 'Your order #{{order_id}} has been delivered',
                'html_content' => $this->getOrderDeliveredHtml(),
                'text_content' => 'Your order has been delivered on {{delivery_date}}',
                'variables' => ['order_id', 'customer_name', 'delivery_date'],
            ],
            'order_cancelled' => [
                'subject' => 'Order #{{order_id}} has been cancelled',
                'html_content' => $this->getOrderCancelledHtml(),
                'text_content' => 'Your order has been cancelled. Refund: {{refund_amount}}',
                'variables' => ['order_id', 'customer_name', 'cancellation_reason', 'refund_amount'],
            ],
            'welcome_email' => [
                'subject' => 'Welcome to BookBharat!',
                'html_content' => $this->getWelcomeEmailHtml(),
                'text_content' => 'Welcome {{customer_name}}! Thank you for joining BookBharat.',
                'variables' => ['customer_name', 'email', 'activation_link'],
            ],
            'password_reset' => [
                'subject' => 'Reset your password',
                'html_content' => $this->getPasswordResetHtml(),
                'text_content' => 'Click here to reset your password: {{reset_link}}',
                'variables' => ['customer_name', 'reset_link', 'expiry_time'],
            ],
            'abandoned_cart' => [
                'subject' => 'Complete your purchase at BookBharat',
                'html_content' => $this->getAbandonedCartHtml(),
                'text_content' => 'You left items in your cart. Total: {{cart_total}}',
                'variables' => ['customer_name', 'cart_items', 'cart_total', 'cart_link'],
            ],
            'payment_success' => [
                'subject' => 'Payment successful for order #{{order_id}}',
                'html_content' => $this->getPaymentSuccessHtml(),
                'text_content' => 'Payment of {{payment_amount}} received successfully.',
                'variables' => ['order_id', 'payment_amount', 'payment_method', 'transaction_id'],
            ],
            'payment_failed' => [
                'subject' => 'Payment failed for order #{{order_id}}',
                'html_content' => $this->getPaymentFailedHtml(),
                'text_content' => 'Payment failed. Reason: {{failure_reason}}',
                'variables' => ['order_id', 'payment_amount', 'failure_reason', 'retry_link'],
            ],
            'newsletter_welcome' => [
                'subject' => 'Welcome to BookBharat Newsletter!',
                'html_content' => $this->getNewsletterWelcomeHtml(),
                'text_content' => 'Thank you for subscribing to our newsletter!',
                'variables' => ['subscriber_name', 'unsubscribe_link'],
            ],
        ];

        foreach ($templates as $type => $data) {
            EmailTemplate::updateOrCreate(
                [
                    'type' => $type,
                    'language' => 'en',
                ],
                [
                    'name' => str_replace('_', ' ', ucwords($type, '_')),
                    'subject' => $data['subject'],
                    'html_content' => $data['html_content'],
                    'text_content' => $data['text_content'],
                    'variables' => $data['variables'],
                    'from_name' => 'BookBharat',
                    'from_email' => config('mail.from.address'),
                    'is_active' => true,
                    'created_by' => $admin->id ?? 1,
                ]
            );
        }
    }

    private function getOrderConfirmationHtml(): string
    {
        return '<h1>Thank you for your order!</h1><p>Hi {{customer_name}},</p><p>Your order #{{order_id}} has been confirmed.</p><p>Order Total: ₹{{order_total}}</p><p>Payment Method: {{payment_method}}</p>{{order_items}}<p><strong>Shipping Address:</strong><br>{{shipping_address}}</p>';
    }

    private function getOrderShippedHtml(): string
    {
        return '<h1>Your order has been shipped!</h1><p>Hi {{customer_name}},</p><p>Your order #{{order_id}} is on its way.</p><p><strong>Tracking Number:</strong> {{tracking_number}}</p><p><strong>Carrier:</strong> {{carrier}}</p><p><strong>Estimated Delivery:</strong> {{estimated_delivery}}</p>';
    }

    private function getOrderDeliveredHtml(): string
    {
        return '<h1>Your order has been delivered!</h1><p>Hi {{customer_name}},</p><p>Your order #{{order_id}} was delivered on {{delivery_date}}.</p><p>Thank you for shopping with BookBharat!</p>';
    }

    private function getOrderCancelledHtml(): string
    {
        return '<h1>Order Cancelled</h1><p>Hi {{customer_name}},</p><p>Your order #{{order_id}} has been cancelled.</p><p><strong>Reason:</strong> {{cancellation_reason}}</p><p><strong>Refund Amount:</strong> ₹{{refund_amount}}</p>';
    }

    private function getWelcomeEmailHtml(): string
    {
        return '<h1>Welcome to BookBharat!</h1><p>Hi {{customer_name}},</p><p>Thank you for joining BookBharat! We are excited to have you.</p><p>Your account email: {{email}}</p><p>Activate your account: {{activation_link}}</p>';
    }

    private function getPasswordResetHtml(): string
    {
        return '<h1>Reset Your Password</h1><p>Hi {{customer_name}},</p><p>Click the link below to reset your password:</p><p><a href="{{reset_link}}">Reset Password</a></p><p>This link expires in {{expiry_time}}.</p>';
    }

    private function getAbandonedCartHtml(): string
    {
        return '<h1>Complete Your Purchase</h1><p>Hi {{customer_name}},</p><p>You left items in your cart. Don\'t miss out!</p>{{cart_items}}<p><strong>Total:</strong> ₹{{cart_total}}</p><p><a href="{{cart_link}}">Continue Shopping</a></p>';
    }

    private function getPaymentSuccessHtml(): string
    {
        return '<h1>Payment Successful!</h1><p>Hi {{customer_name}},</p><p>Your payment for order #{{order_id}} was successful.</p><p><strong>Amount:</strong> ₹{{payment_amount}}</p><p><strong>Method:</strong> {{payment_method}}</p><p><strong>Transaction ID:</strong> {{transaction_id}}</p>';
    }

    private function getPaymentFailedHtml(): string
    {
        return '<h1>Payment Failed</h1><p>Hi {{customer_name}},</p><p>Your payment for order #{{order_id}} failed.</p><p><strong>Reason:</strong> {{failure_reason}}</p><p><a href="{{retry_link}}">Try Again</a></p>';
    }

    private function getNewsletterWelcomeHtml(): string
    {
        return '<h1>Welcome to Our Newsletter!</h1><p>Hi {{subscriber_name}},</p><p>Thank you for subscribing to BookBharat newsletter!</p><p>You can unsubscribe at any time: {{unsubscribe_link}}</p>';
    }
}
