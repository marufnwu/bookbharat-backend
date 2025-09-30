<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AdminSetting;

class PaymentAdminSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Razorpay Gateway Settings
            [
                'key' => 'razorpay_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment_razorpay',
                'label' => 'Enable Razorpay',
                'description' => 'Enable Razorpay payment gateway',
                'input_type' => 'switch',
                'sort_order' => 1,
                'is_public' => true
            ],
            [
                'key' => 'razorpay_test_mode',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment_razorpay',
                'label' => 'Test Mode',
                'description' => 'Enable test mode for Razorpay (disable in production)',
                'input_type' => 'switch',
                'sort_order' => 2,
            ],
            [
                'key' => 'razorpay_key_id',
                'value' => 'rzp_test_xxxxx',
                'type' => 'string',
                'group' => 'payment_razorpay',
                'label' => 'Key ID',
                'description' => 'Razorpay API Key ID (get from Razorpay dashboard)',
                'input_type' => 'password',
                'sort_order' => 3,
            ],
            [
                'key' => 'razorpay_key_secret',
                'value' => 'xxxxxxxxxxxx',
                'type' => 'string',
                'group' => 'payment_razorpay',
                'label' => 'Key Secret',
                'description' => 'Razorpay API Key Secret (keep this secure)',
                'input_type' => 'password',
                'sort_order' => 4,
            ],
            [
                'key' => 'razorpay_webhook_secret',
                'value' => '',
                'type' => 'string',
                'group' => 'payment_razorpay',
                'label' => 'Webhook Secret',
                'description' => 'Webhook secret for secure webhook verification',
                'input_type' => 'password',
                'sort_order' => 5,
            ],
            [
                'key' => 'razorpay_min_amount',
                'value' => '10',
                'type' => 'integer',
                'group' => 'payment_razorpay',
                'label' => 'Minimum Amount (₹)',
                'description' => 'Minimum order amount for Razorpay payments',
                'input_type' => 'number',
                'sort_order' => 6,
                'is_public' => true
            ],
            [
                'key' => 'razorpay_max_amount',
                'value' => '500000',
                'type' => 'integer',
                'group' => 'payment_razorpay',
                'label' => 'Maximum Amount (₹)',
                'description' => 'Maximum order amount for Razorpay payments',
                'input_type' => 'number',
                'sort_order' => 7,
                'is_public' => true
            ],
            [
                'key' => 'razorpay_theme_color',
                'value' => '#2563eb',
                'type' => 'string',
                'group' => 'payment_razorpay',
                'label' => 'Theme Color',
                'description' => 'Customize Razorpay checkout theme color',
                'input_type' => 'color',
                'sort_order' => 8,
                'is_public' => true
            ],

            // Cashfree Gateway Settings
            [
                'key' => 'cashfree_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment_cashfree',
                'label' => 'Enable Cashfree',
                'description' => 'Enable Cashfree payment gateway',
                'input_type' => 'switch',
                'sort_order' => 1,
                'is_public' => true
            ],
            [
                'key' => 'cashfree_test_mode',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment_cashfree',
                'label' => 'Test Mode',
                'description' => 'Enable test mode for Cashfree (disable in production)',
                'input_type' => 'switch',
                'sort_order' => 2,
            ],
            [
                'key' => 'cashfree_client_id',
                'value' => 'CF_TEST_XXXXXX',
                'type' => 'string',
                'group' => 'payment_cashfree',
                'label' => 'Client ID',
                'description' => 'Cashfree Client ID (get from Cashfree dashboard)',
                'input_type' => 'password',
                'sort_order' => 3,
            ],
            [
                'key' => 'cashfree_client_secret',
                'value' => 'cfsk_ma_test_xxxxxxxxxxxx',
                'type' => 'string',
                'group' => 'payment_cashfree',
                'label' => 'Client Secret',
                'description' => 'Cashfree Client Secret (keep this secure)',
                'input_type' => 'password',
                'sort_order' => 4,
            ],
            [
                'key' => 'cashfree_min_amount',
                'value' => '10',
                'type' => 'integer',
                'group' => 'payment_cashfree',
                'label' => 'Minimum Amount (₹)',
                'description' => 'Minimum order amount for Cashfree payments',
                'input_type' => 'number',
                'sort_order' => 5,
                'is_public' => true
            ],
            [
                'key' => 'cashfree_max_amount',
                'value' => '1000000',
                'type' => 'integer',
                'group' => 'payment_cashfree',
                'label' => 'Maximum Amount (₹)',
                'description' => 'Maximum order amount for Cashfree payments',
                'input_type' => 'number',
                'sort_order' => 6,
                'is_public' => true
            ],

            // PayU Gateway Settings
            [
                'key' => 'payu_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'payment_payu',
                'label' => 'Enable PayU',
                'description' => 'Enable PayU payment gateway',
                'input_type' => 'switch',
                'sort_order' => 1,
                'is_public' => true
            ],
            [
                'key' => 'payu_test_mode',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment_payu',
                'label' => 'Test Mode',
                'description' => 'Enable test mode for PayU',
                'input_type' => 'switch',
                'sort_order' => 2,
            ],
            [
                'key' => 'payu_merchant_key',
                'value' => 'WUZvzd',
                'type' => 'string',
                'group' => 'payment_payu',
                'label' => 'Merchant Key',
                'description' => 'PayU Merchant Key',
                'input_type' => 'password',
                'sort_order' => 3,
            ],
            [
                'key' => 'payu_merchant_salt',
                'value' => 'PbRzWelxHkpP7xtF1heA9ZgTo2C2RRLZ',
                'type' => 'string',
                'group' => 'payment_payu',
                'label' => 'Merchant Salt',
                'description' => 'PayU Merchant Salt (keep secure)',
                'input_type' => 'password',
                'sort_order' => 4,
            ],

            // Bank Transfer Settings
            [
                'key' => 'bank_transfer_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'payment_bank',
                'label' => 'Enable Bank Transfer',
                'description' => 'Allow customers to pay via bank transfer/NEFT',
                'input_type' => 'switch',
                'sort_order' => 1,
                'is_public' => true
            ],
            [
                'key' => 'bank_account_name',
                'value' => 'BookBharat Pvt Ltd',
                'type' => 'string',
                'group' => 'payment_bank',
                'label' => 'Account Name',
                'description' => 'Bank account holder name',
                'input_type' => 'text',
                'sort_order' => 2,
                'is_public' => true
            ],
            [
                'key' => 'bank_account_number',
                'value' => '1234567890',
                'type' => 'string',
                'group' => 'payment_bank',
                'label' => 'Account Number',
                'description' => 'Bank account number',
                'input_type' => 'text',
                'sort_order' => 3,
                'is_public' => true
            ],
            [
                'key' => 'bank_ifsc_code',
                'value' => 'HDFC0000123',
                'type' => 'string',
                'group' => 'payment_bank',
                'label' => 'IFSC Code',
                'description' => 'Bank IFSC code',
                'input_type' => 'text',
                'sort_order' => 4,
                'is_public' => true
            ],
            [
                'key' => 'bank_name',
                'value' => 'HDFC Bank',
                'type' => 'string',
                'group' => 'payment_bank',
                'label' => 'Bank Name',
                'description' => 'Name of the bank',
                'input_type' => 'text',
                'sort_order' => 5,
                'is_public' => true
            ],
            [
                'key' => 'bank_transfer_min_amount',
                'value' => '1000',
                'type' => 'integer',
                'group' => 'payment_bank',
                'label' => 'Minimum Amount (₹)',
                'description' => 'Minimum order amount for bank transfer',
                'input_type' => 'number',
                'sort_order' => 6,
                'is_public' => true
            ],

            // Payment Method Priorities
            [
                'key' => 'payment_method_order',
                'value' => '["razorpay","cashfree","cod","bank_transfer"]',
                'type' => 'array',
                'group' => 'payment_general',
                'label' => 'Payment Method Order',
                'description' => 'Order in which payment methods are displayed to customers',
                'input_type' => 'sortable',
                'options' => [
                    'razorpay' => 'Razorpay',
                    'cashfree' => 'Cashfree',
                    'cod' => 'Cash on Delivery',
                    'bank_transfer' => 'Bank Transfer',
                    'payu' => 'PayU'
                ],
                'sort_order' => 1,
                'is_public' => true
            ],

            // Payment Security Settings
            [
                'key' => 'payment_timeout',
                'value' => '15',
                'type' => 'integer',
                'group' => 'payment_security',
                'label' => 'Payment Timeout (minutes)',
                'description' => 'How long to wait for payment completion',
                'input_type' => 'number',
                'sort_order' => 1,
            ],
            [
                'key' => 'auto_cancel_failed_payments',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment_security',
                'label' => 'Auto-cancel Failed Payments',
                'description' => 'Automatically cancel orders with failed payments after timeout',
                'input_type' => 'switch',
                'sort_order' => 2,
            ],
            [
                'key' => 'require_address_verification',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment_security',
                'label' => 'Verify Shipping Address',
                'description' => 'Require address verification for high-value orders',
                'input_type' => 'switch',
                'sort_order' => 3,
            ],
            [
                'key' => 'fraud_detection_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment_security',
                'label' => 'Fraud Detection',
                'description' => 'Enable basic fraud detection checks',
                'input_type' => 'switch',
                'sort_order' => 4,
            ],

            // Refund Settings
            [
                'key' => 'auto_refund_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment_refunds',
                'label' => 'Auto Refunds',
                'description' => 'Enable automatic refund processing',
                'input_type' => 'switch',
                'sort_order' => 1,
            ],
            [
                'key' => 'refund_processing_time',
                'value' => '7',
                'type' => 'integer',
                'group' => 'payment_refunds',
                'label' => 'Refund Processing Time (days)',
                'description' => 'Expected time for refund processing',
                'input_type' => 'number',
                'sort_order' => 2,
                'is_public' => true
            ],
            [
                'key' => 'partial_refunds_allowed',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment_refunds',
                'label' => 'Allow Partial Refunds',
                'description' => 'Allow partial refunds for orders',
                'input_type' => 'switch',
                'sort_order' => 3,
            ],
        ];

        foreach ($settings as $setting) {
            AdminSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('Payment admin settings seeded successfully!');
    }
}