<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentSetting;
use App\Models\PaymentConfiguration;

class EnablePaymentGatewaysSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Enable Razorpay as primary gateway
        PaymentSetting::updateOrCreate(
            ['unique_keyword' => 'razorpay'],
            [
                'name' => 'Razorpay',
                'description' => 'Accept payments via Razorpay payment gateway',
                'configuration' => [
                    'key' => env('RAZORPAY_KEY', 'rzp_test_example'),
                    'secret' => env('RAZORPAY_SECRET', 'secret_example'),
                    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET', ''),
                ],
                'is_active' => true, // Enable this gateway
                'is_production' => false,
                'supported_currencies' => ['INR'],
                'webhook_config' => [
                    'webhook_url' => '/api/v1/payment/webhook/razorpay',
                    'events' => ['payment.captured', 'payment.failed', 'order.paid']
                ],
                'priority' => 10
            ]
        );

        // Enable COD
        PaymentSetting::updateOrCreate(
            ['unique_keyword' => 'cod'],
            [
                'name' => 'Cash on Delivery',
                'description' => 'Pay when your order is delivered',
                'configuration' => [
                    'service_charge' => 0,
                    'min_order_amount' => 100,
                    'max_order_amount' => 50000,
                ],
                'is_active' => true, // Enable COD
                'is_production' => true,
                'supported_currencies' => ['INR'],
                'priority' => 5
            ]
        );

        // Also update PaymentConfiguration for backward compatibility
        PaymentConfiguration::updateOrCreate(
            ['payment_method' => 'razorpay'],
            [
                'display_name' => 'Online Payment (Razorpay)',
                'description' => 'Pay securely using UPI, Cards, Net Banking, or Wallets',
                'is_enabled' => true,
                'priority' => 10,
                'configuration' => [
                    'gateway' => 'razorpay',
                    'instant_refund' => true,
                    'supported_methods' => ['card', 'netbanking', 'wallet', 'upi']
                ],
                'restrictions' => [
                    'min_order_amount' => 10,
                    'max_order_amount' => null,
                ]
            ]
        );

        PaymentConfiguration::updateOrCreate(
            ['payment_method' => 'cod'],
            [
                'display_name' => 'Cash on Delivery',
                'description' => 'Pay when your order is delivered to your doorstep',
                'is_enabled' => true,
                'priority' => 5,
                'configuration' => [
                    'advance_payment' => [
                        'required' => false,
                        'type' => 'percentage',
                        'value' => 0
                    ],
                    'service_charges' => [
                        'type' => 'fixed',
                        'value' => 0
                    ]
                ],
                'restrictions' => [
                    'min_order_amount' => 100,
                    'max_order_amount' => 50000,
                ]
            ]
        );

        // Enable PayU for testing
        PaymentSetting::updateOrCreate(
            ['unique_keyword' => 'payu'],
            [
                'name' => 'PayU',
                'description' => 'Accept payments via PayU payment gateway',
                'configuration' => [
                    'merchant_key' => env('PAYU_MERCHANT_KEY', 'WUZvzd'),
                    'salt' => env('PAYU_MERCHANT_SALT', 'PbRzWelxHkpP7xtF1heA9ZgTo2C2RRLZ'),
                ],
                'is_active' => true, // Enable this gateway
                'is_production' => false,
                'supported_currencies' => ['INR'],
                'webhook_config' => [
                    'webhook_url' => '/api/v1/payment/webhook/payu',
                    'success_url' => '/api/v1/payment/callback/payu',
                    'failure_url' => '/api/v1/payment/callback/payu'
                ],
                'priority' => 9
            ]
        );

        echo "✅ Payment gateways enabled successfully!\n";
        echo "- Razorpay (Online Payment)\n";
        echo "- PayU (Online Payment)\n";
        echo "- Cash on Delivery\n";
    }
}