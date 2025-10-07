<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    /**
     * CLEAN SINGLE TABLE SEEDER - SINGLE SOURCE OF TRUTH
     *
     * Simple approach: One gateway at a time with fallback
     * - razorpay (default online payment)
     * - cashfree (fallback if Razorpay fails)
     * - cod (cash on delivery)
     *
     * THE ONLY SWITCH: is_enabled
     */
    public function run(): void
    {
        // Clear existing data
        PaymentMethod::truncate();

        // Get credential schemas
        $schemas = PaymentMethod::getCredentialSchemas();

        // 1. Cash on Delivery (SYSTEM - Cannot be deleted)
        PaymentMethod::create([
            'payment_method' => 'cod',
            'display_name' => 'Cash on Delivery',
            'description' => 'Pay when your order arrives at your doorstep',
            'is_enabled' => true,
            'is_system' => true, // PREDEFINED - CANNOT DELETE
            'is_default' => false,
            'is_fallback' => false,
            'gateway_type' => 'cod',
            'credentials' => null, // COD doesn't need API credentials
            'credential_schema' => $schemas['cod'],
            'configuration' => [
                'advance_payment' => [
                    'required' => false,
                    'type' => 'fixed', // 'fixed' or 'percentage'
                    'value' => 0,
                    'description' => 'No advance payment required',
                ],
                'service_charges' => [
                    'enabled' => false,
                    'type' => 'fixed',
                    'value' => 0,
                    'description' => 'No COD charges',
                ],
            ],
            'restrictions' => [
                'min_order_amount' => 100,
                'max_order_amount' => 50000,
            ],
            'priority' => 8,
            'is_production' => true,
            'supported_currencies' => ['INR'],
            'webhook_config' => null,
        ]);

        // 2. Razorpay (DEFAULT - Primary online gateway, SYSTEM)
        PaymentMethod::create([
            'payment_method' => 'razorpay',
            'display_name' => 'Pay Online (Cards, UPI, NetBanking, Wallets)',
            'description' => 'Pay securely using Credit/Debit Cards, UPI, Net Banking, or Mobile Wallets',
            'is_enabled' => true, // ENABLED BY DEFAULT
            'is_system' => true, // PREDEFINED - CANNOT DELETE, ONLY EDIT
            'is_default' => true, // DEFAULT ONLINE PAYMENT GATEWAY
            'is_fallback' => false,
            'gateway_type' => 'razorpay',
            'credentials' => [
                'key_id' => env('RAZORPAY_KEY_ID', ''),
                'key_secret' => env('RAZORPAY_KEY_SECRET', ''),
                'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET', ''),
            ],
            'credential_schema' => $schemas['razorpay'],
            'configuration' => [
                'supported_methods' => ['card', 'netbanking', 'wallet', 'upi'],
                'auto_capture' => true,
                'theme_color' => '#1e40af',
            ],
            'restrictions' => [
                'min_order_amount' => 1,
                'max_order_amount' => null,
            ],
            'priority' => 10,
            'is_production' => env('RAZORPAY_PRODUCTION', false),
            'supported_currencies' => ['INR'],
            'webhook_config' => [
                'url' => env('APP_URL', 'http://localhost:8000') . '/api/v1/payment/webhook/razorpay',
                'events' => ['payment.captured', 'payment.failed', 'order.paid'],
            ],
        ]);

        // 3. Cashfree (FALLBACK - Alternative gateway, SYSTEM)
        PaymentMethod::create([
            'payment_method' => 'cashfree',
            'display_name' => 'Pay Online (Cashfree)',
            'description' => 'Alternate online payment gateway - Cards, UPI, NetBanking',
            'is_enabled' => false, // DISABLED BY DEFAULT - Use as fallback
            'is_system' => true, // PREDEFINED - CANNOT DELETE, ONLY EDIT
            'is_default' => false,
            'is_fallback' => true, // FALLBACK GATEWAY IF DEFAULT FAILS
            'gateway_type' => 'cashfree',
            'credentials' => [
                'app_id' => env('CASHFREE_APP_ID', ''),
                'secret_key' => env('CASHFREE_SECRET_KEY', ''),
            ],
            'credential_schema' => $schemas['cashfree'],
            'configuration' => [
                'mode' => env('CASHFREE_MODE', 'test'),
                'supported_methods' => ['card', 'netbanking', 'upi', 'wallet'],
            ],
            'restrictions' => [
                'min_order_amount' => 1,
                'max_order_amount' => null,
            ],
            'priority' => 5,
            'is_production' => env('CASHFREE_PRODUCTION', false),
            'supported_currencies' => ['INR'],
            'webhook_config' => [
                'url' => env('APP_URL', 'http://localhost:8000') . '/api/v1/payment/webhook/cashfree',
            ],
        ]);

        // 4. PayU (DISABLED - Additional option, SYSTEM)
        PaymentMethod::create([
            'payment_method' => 'payu',
            'display_name' => 'Pay Online (PayU)',
            'description' => 'Secure payment gateway - Cards, Net Banking, UPI',
            'is_enabled' => false, // DISABLED BY DEFAULT
            'is_system' => true, // PREDEFINED - CANNOT DELETE, ONLY EDIT
            'is_default' => false,
            'is_fallback' => false,
            'gateway_type' => 'payu',
            'credentials' => [
                'merchant_key' => env('PAYU_MERCHANT_KEY', ''),
                'merchant_salt' => env('PAYU_MERCHANT_SALT', ''),
            ],
            'credential_schema' => $schemas['payu'],
            'configuration' => [
                'supported_methods' => ['card', 'netbanking', 'upi', 'wallet'],
            ],
            'restrictions' => [
                'min_order_amount' => 10,
                'max_order_amount' => null,
            ],
            'priority' => 3,
            'is_production' => env('PAYU_PRODUCTION', false),
            'supported_currencies' => ['INR'],
            'webhook_config' => [
                'url' => env('APP_URL', 'http://localhost:8000') . '/api/v1/payment/webhook/payu',
            ],
        ]);

        // 5. PhonePe (DISABLED - Additional option, SYSTEM)
        PaymentMethod::create([
            'payment_method' => 'phonepe',
            'display_name' => 'Pay with PhonePe',
            'description' => 'Fast payments with PhonePe - UPI, Cards, Wallets',
            'is_enabled' => false, // DISABLED BY DEFAULT
            'is_system' => true, // PREDEFINED - CANNOT DELETE, ONLY EDIT
            'is_default' => false,
            'is_fallback' => false,
            'gateway_type' => 'phonepe',
            'credentials' => [
                'merchant_id' => env('PHONEPE_MERCHANT_ID', ''),
                'salt_key' => env('PHONEPE_SALT_KEY', ''),
                'salt_index' => env('PHONEPE_SALT_INDEX', '1'),
            ],
            'credential_schema' => $schemas['phonepe'],
            'configuration' => [
                'supported_methods' => ['upi', 'card', 'wallet'],
            ],
            'restrictions' => [
                'min_order_amount' => 1,
                'max_order_amount' => null,
            ],
            'priority' => 2,
            'is_production' => env('PHONEPE_PRODUCTION', false),
            'supported_currencies' => ['INR'],
            'webhook_config' => [
                'url' => env('APP_URL', 'http://localhost:8000') . '/api/v1/payment/webhook/phonepe',
            ],
        ]);

        $this->command->info('✅ Payment methods seeded successfully!');
        $this->command->info('');
        $this->command->info('📌 Gateway Configuration (5 gateways seeded):');
        $this->command->info('   • Razorpay: ENABLED + DEFAULT (primary online gateway)');
        $this->command->info('   • Cashfree: DISABLED + FALLBACK (backup gateway)');
        $this->command->info('   • PayU: DISABLED (additional option)');
        $this->command->info('   • PhonePe: DISABLED (additional option)');
        $this->command->info('   • COD: ENABLED');
        $this->command->info('');
        $this->command->info('🔒 Gateway Protection:');
        $this->command->info('   • All gateways marked as SYSTEM (is_system = true)');
        $this->command->info('   • Cannot be deleted, only edited');
        $this->command->info('   • Admin can only modify credentials and toggle on/off');
        $this->command->info('');
        $this->command->info('🎯 Gateway Roles:');
        $this->command->info('   • DEFAULT (is_default): First gateway tried for online payments');
        $this->command->info('   • FALLBACK (is_fallback): Used if default gateway fails');
        $this->command->info('   • ENABLED (is_enabled): THE ONLY SWITCH for visibility');
        $this->command->info('');
        $this->command->info('🔧 Credentials Storage:');
        $this->command->info('   • Stored in credentials JSON column');
        $this->command->info('   • Each gateway has its own schema (credential_schema)');
        $this->command->info('   • Razorpay needs: key_id, key_secret, webhook_secret');
        $this->command->info('   • Cashfree needs: app_id, secret_key');
        $this->command->info('   • COD needs: no credentials');
        $this->command->info('');
        $this->command->info('💡 Next steps:');
        $this->command->info('   1. Update .env or database credentials:');
        $this->command->info('      RAZORPAY_KEY_ID=your_key');
        $this->command->info('      RAZORPAY_KEY_SECRET=your_secret');
        $this->command->info('   2. Use admin panel to edit gateway credentials');
        $this->command->info('   3. Toggle is_enabled to show/hide gateways');
        $this->command->info('   4. Set different gateway as default/fallback if needed');
    }
}
