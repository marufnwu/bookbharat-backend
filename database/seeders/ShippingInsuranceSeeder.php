<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ShippingInsurance;

class ShippingInsuranceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $insurancePlans = [
            [
                'name' => 'Basic Protection',
                'description' => 'Basic coverage for orders up to ₹5,000. Ideal for everyday purchases.',
                'min_order_value' => 100.00,
                'max_order_value' => 5000.00,
                'coverage_percentage' => 100.00,
                'premium_percentage' => 1.50,
                'minimum_premium' => 15.00,
                'maximum_premium' => 75.00,
                'is_mandatory' => false,
                'claim_processing_days' => 5,
                'conditions' => [
                    [
                        'type' => 'zone_multiplier',
                        'zones' => [
                            'A' => 1.0,
                            'B' => 1.1,
                            'C' => 1.2,
                            'D' => 1.3,
                            'E' => 1.5
                        ]
                    ]
                ],
                'is_active' => true,
            ],

            [
                'name' => 'Standard Coverage',
                'description' => 'Comprehensive coverage for orders up to ₹25,000. Best value for most customers.',
                'min_order_value' => 500.00,
                'max_order_value' => 25000.00,
                'coverage_percentage' => 100.00,
                'premium_percentage' => 2.00,
                'minimum_premium' => 25.00,
                'maximum_premium' => 500.00,
                'is_mandatory' => false,
                'claim_processing_days' => 7,
                'conditions' => [
                    [
                        'type' => 'high_value_discount',
                        'threshold' => 10000,
                        'discount_percent' => 15
                    ],
                    [
                        'type' => 'remote_surcharge',
                        'amount' => 30
                    ],
                    [
                        'type' => 'electronics_surcharge',
                        'multiplier' => 1.25
                    ]
                ],
                'is_active' => true,
            ],

            [
                'name' => 'Premium Protection',
                'description' => 'Premium coverage for high-value orders. Includes priority claim processing.',
                'min_order_value' => 2500.00,
                'max_order_value' => 100000.00,
                'coverage_percentage' => 100.00,
                'premium_percentage' => 2.50,
                'minimum_premium' => 50.00,
                'maximum_premium' => 2500.00,
                'is_mandatory' => false,
                'claim_processing_days' => 3,
                'conditions' => [
                    [
                        'type' => 'high_value_discount',
                        'threshold' => 15000,
                        'discount_percent' => 20
                    ],
                    [
                        'type' => 'fragile_item_surcharge',
                        'multiplier' => 1.4
                    ],
                    [
                        'type' => 'electronics_surcharge',
                        'multiplier' => 1.3
                    ]
                ],
                'is_active' => true,
            ],

            [
                'name' => 'Mandatory High-Value',
                'description' => 'Mandatory insurance for orders above ₹10,000 or fragile items.',
                'min_order_value' => 10000.00,
                'max_order_value' => null,
                'coverage_percentage' => 100.00,
                'premium_percentage' => 1.75,
                'minimum_premium' => 175.00,
                'maximum_premium' => 5000.00,
                'is_mandatory' => true,
                'claim_processing_days' => 7,
                'conditions' => [
                    [
                        'type' => 'high_value_mandatory',
                        'threshold' => 10000
                    ],
                    [
                        'type' => 'fragile_mandatory'
                    ],
                    [
                        'type' => 'remote_area_mandatory'
                    ]
                ],
                'is_active' => true,
            ],

            [
                'name' => 'Electronics Special',
                'description' => 'Specialized coverage for electronics and gadgets with enhanced protection.',
                'min_order_value' => 1000.00,
                'max_order_value' => 150000.00,
                'coverage_percentage' => 100.00,
                'premium_percentage' => 3.00,
                'minimum_premium' => 50.00,
                'maximum_premium' => 4500.00,
                'is_mandatory' => false,
                'claim_processing_days' => 5,
                'conditions' => [
                    [
                        'type' => 'electronics_surcharge',
                        'multiplier' => 1.0 // No additional surcharge since this is electronics-specific
                    ],
                    [
                        'type' => 'high_value_discount',
                        'threshold' => 25000,
                        'discount_percent' => 25
                    ]
                ],
                'is_active' => true,
            ],

            [
                'name' => 'Books & Media',
                'description' => 'Affordable coverage specifically designed for books, media, and educational materials.',
                'min_order_value' => 50.00,
                'max_order_value' => 5000.00,
                'coverage_percentage' => 100.00,
                'premium_percentage' => 1.00,
                'minimum_premium' => 8.00,
                'maximum_premium' => 50.00,
                'is_mandatory' => false,
                'claim_processing_days' => 5,
                'conditions' => [
                    [
                        'type' => 'minimum_charge',
                        'amount' => 8.00
                    ]
                ],
                'is_active' => true,
            ],
        ];

        foreach ($insurancePlans as $plan) {
            ShippingInsurance::updateOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}
