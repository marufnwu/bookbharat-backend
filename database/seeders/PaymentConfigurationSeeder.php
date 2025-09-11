<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PaymentConfiguration;

class PaymentConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $configurations = [
            [
                'payment_method' => 'cod',
                'display_name' => 'Cash on Delivery',
                'description' => 'Pay when your order is delivered to your doorstep',
                'is_enabled' => true,
                'priority' => 1,
                'configuration' => [
                    'advance_payment' => [
                        'required' => false,
                        'type' => 'percentage', // or 'fixed'
                        'value' => 0, // 20% or ₹200
                        'description' => 'Advance payment required for high-value COD orders'
                    ],
                    'service_charges' => [
                        'type' => 'fixed',
                        'value' => 40, // ₹40 service charge for COD
                        'description' => 'COD handling charges'
                    ]
                ],
                'restrictions' => [
                    'min_order_amount' => 100, // Minimum ₹100 for COD
                    'max_order_amount' => 50000, // Maximum ₹50,000 for COD
                    'excluded_categories' => [], // Category IDs that don't support COD
                    'excluded_pincodes' => [] // Pincodes where COD is not available
                ]
            ],
            [
                'payment_method' => 'cod_with_advance',
                'display_name' => 'Cash on Delivery (Pay ₹200 Advance)',
                'description' => 'Pay small advance amount online, rest on delivery',
                'is_enabled' => true,
                'priority' => 2,
                'configuration' => [
                    'advance_payment' => [
                        'required' => true,
                        'type' => 'fixed',
                        'value' => 200,
                        'description' => 'Fixed ₹200 advance payment'
                    ],
                    'service_charges' => [
                        'type' => 'fixed',
                        'value' => 0,
                        'description' => 'No additional charges'
                    ]
                ],
                'restrictions' => [
                    'min_order_amount' => 500,
                    'max_order_amount' => null,
                    'excluded_categories' => [],
                    'excluded_pincodes' => []
                ]
            ],
            [
                'payment_method' => 'cod_percentage_advance',
                'display_name' => 'Cash on Delivery (20% Advance)',
                'description' => 'Pay 20% advance online, rest on delivery',
                'is_enabled' => false,
                'priority' => 3,
                'configuration' => [
                    'advance_payment' => [
                        'required' => true,
                        'type' => 'percentage',
                        'value' => 20,
                        'description' => '20% advance payment required'
                    ],
                    'service_charges' => [
                        'type' => 'fixed',
                        'value' => 0,
                        'description' => 'No additional charges'
                    ]
                ],
                'restrictions' => [
                    'min_order_amount' => 2000,
                    'max_order_amount' => null,
                    'excluded_categories' => [],
                    'excluded_pincodes' => []
                ]
            ],
            [
                'payment_method' => 'razorpay',
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
                    'excluded_categories' => [],
                    'excluded_pincodes' => []
                ]
            ],
            [
                'payment_method' => 'cashfree',
                'display_name' => 'Online Payment (Cashfree)',
                'description' => 'Pay securely using UPI, Cards, Net Banking',
                'is_enabled' => true,
                'priority' => 9,
                'configuration' => [
                    'gateway' => 'cashfree',
                    'instant_refund' => true,
                    'supported_methods' => ['card', 'netbanking', 'upi']
                ],
                'restrictions' => [
                    'min_order_amount' => 10,
                    'max_order_amount' => null,
                    'excluded_categories' => [],
                    'excluded_pincodes' => []
                ]
            ],
            [
                'payment_method' => 'bank_transfer',
                'display_name' => 'Bank Transfer / NEFT',
                'description' => 'Transfer amount directly to our bank account',
                'is_enabled' => false,
                'priority' => 5,
                'configuration' => [
                    'bank_details' => [
                        'account_name' => 'BookBharat Pvt Ltd',
                        'account_number' => '1234567890',
                        'ifsc_code' => 'HDFC0000123',
                        'bank_name' => 'HDFC Bank'
                    ],
                    'verification_required' => true
                ],
                'restrictions' => [
                    'min_order_amount' => 1000,
                    'max_order_amount' => null,
                    'excluded_categories' => [],
                    'excluded_pincodes' => []
                ]
            ]
        ];

        foreach ($configurations as $config) {
            PaymentConfiguration::updateOrCreate(
                ['payment_method' => $config['payment_method']],
                $config
            );
        }
    }
}