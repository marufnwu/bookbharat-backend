<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentSetting;

class PaymentSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentSettings = [
            [
                'unique_keyword' => 'razorpay',
                'name' => 'Razorpay',
                'description' => 'Accept payments via Razorpay payment gateway',
                'configuration' => [
                    'key' => env('RAZORPAY_KEY', ''),
                    'secret' => env('RAZORPAY_SECRET', ''),
                    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET', ''),
                ],
                'is_active' => false,
                'is_production' => false,
                'supported_currencies' => ['INR'],
                'webhook_config' => [
                    'webhook_url' => '/api/v1/payment/razorpay/webhook',
                    'events' => ['payment.captured', 'payment.failed', 'order.paid']
                ],
                'priority' => 1
            ],
            [
                'unique_keyword' => 'payu',
                'name' => 'PayU',
                'description' => 'Accept payments via PayU payment gateway',
                'configuration' => [
                    'merchant_key' => env('PAYU_MERCHANT_KEY', ''),
                    'salt' => env('PAYU_SALT', ''),
                    'salt_32_bit' => env('PAYU_SALT', ''),
                    'production' => false,
                ],
                'is_active' => false,
                'is_production' => false,
                'supported_currencies' => ['INR'],
                'webhook_config' => [
                    'webhook_url' => '/api/v1/payment/payu/webhook',
                    'success_url' => '/api/v1/payment/payu/callback',
                    'failure_url' => '/api/v1/payment/payu/callback'
                ],
                'priority' => 2
            ],
            [
                'unique_keyword' => 'phonepe',
                'name' => 'PhonePe',
                'description' => 'Accept payments via PhonePe payment gateway',
                'configuration' => [
                    'merchant_id' => env('PHONEPE_MERCHANT_ID', ''),
                    'salt_key' => env('PHONEPE_SALT_KEY', ''),
                    'salt_index' => env('PHONEPE_SALT_INDEX', 1),
                    'production' => false,
                    'production_url' => 'https://api.phonepe.com/apis/hermes',
                    'sandbox_url' => 'https://api-preprod.phonepe.com/apis/pg-sandbox'
                ],
                'is_active' => false,
                'is_production' => false,
                'supported_currencies' => ['INR'],
                'webhook_config' => [
                    'webhook_url' => '/api/v1/payment/phonepe/webhook',
                    'callback_url' => '/api/v1/payment/phonepe/callback'
                ],
                'priority' => 3
            ],
            [
                'unique_keyword' => 'cashfree',
                'name' => 'Cashfree',
                'description' => 'Accept payments via Cashfree payment gateway',
                'configuration' => [
                    'app_id' => env('CASHFREE_APP_ID', ''),
                    'secret_key' => env('CASHFREE_SECRET_KEY', ''),
                    'production' => false,
                ],
                'is_active' => false,
                'is_production' => false,
                'supported_currencies' => ['INR'],
                'webhook_config' => [
                    'webhook_url' => '/api/v1/payment/cashfree/webhook',
                    'return_url' => '/api/v1/payment/cashfree/callback'
                ],
                'priority' => 4
            ],
            [
                'unique_keyword' => 'stripe',
                'name' => 'Stripe',
                'description' => 'Accept international payments via Stripe',
                'configuration' => [
                    'publishable_key' => env('STRIPE_PUBLISHABLE_KEY', ''),
                    'secret_key' => env('STRIPE_SECRET_KEY', ''),
                    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
                ],
                'is_active' => false,
                'is_production' => false,
                'supported_currencies' => ['USD', 'EUR', 'GBP', 'INR'],
                'webhook_config' => [
                    'webhook_url' => '/api/v1/payment/stripe/webhook',
                    'events' => ['payment_intent.succeeded', 'payment_intent.payment_failed']
                ],
                'priority' => 5
            ],
            [
                'unique_keyword' => 'paypal',
                'name' => 'PayPal',
                'description' => 'Accept international payments via PayPal',
                'configuration' => [
                    'client_id' => env('PAYPAL_CLIENT_ID', ''),
                    'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
                    'mode' => 'sandbox', // sandbox or live
                ],
                'is_active' => false,
                'is_production' => false,
                'supported_currencies' => ['USD', 'EUR', 'GBP'],
                'webhook_config' => [
                    'webhook_url' => '/api/v1/payment/paypal/webhook',
                    'webhook_id' => ''
                ],
                'priority' => 6
            ]
        ];

        foreach ($paymentSettings as $setting) {
            PaymentSetting::updateOrCreate(
                ['unique_keyword' => $setting['unique_keyword']],
                $setting
            );
        }
    }
}