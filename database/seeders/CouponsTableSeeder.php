<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Coupon;

class CouponsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'SAVE10',
                'name' => '10% Off',
                'description' => 'Get 10% off on your order',
                'type' => 'percentage',
                'value' => 10.00,
                'minimum_order_amount' => 100.00,
                'maximum_discount_amount' => 500.00,
                'usage_limit' => null,
                'usage_limit_per_customer' => 1,
                'usage_count' => 0,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(3),
                'is_active' => true,
                'is_stackable' => false,
            ],
            [
                'code' => 'FLAT50',
                'name' => '₹50 Off',
                'description' => 'Get flat ₹50 off on your order',
                'type' => 'fixed_amount',
                'value' => 50.00,
                'minimum_order_amount' => 200.00,
                'maximum_discount_amount' => null,
                'usage_limit' => 100,
                'usage_limit_per_customer' => 3,
                'usage_count' => 0,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(2),
                'is_active' => true,
                'is_stackable' => false,
            ],
            [
                'code' => 'FREESHIP',
                'name' => 'Free Shipping',
                'description' => 'Get free shipping on your order',
                'type' => 'free_shipping',
                'value' => 0.00,
                'minimum_order_amount' => 300.00,
                'maximum_discount_amount' => null,
                'usage_limit' => null,
                'usage_limit_per_customer' => null,
                'usage_count' => 0,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(1),
                'is_active' => true,
                'is_stackable' => true,
            ],
            [
                'code' => 'BIGDEAL',
                'name' => '20% Off - Big Deal',
                'description' => 'Get 20% off on orders above ₹1000',
                'type' => 'percentage',
                'value' => 20.00,
                'minimum_order_amount' => 1000.00,
                'maximum_discount_amount' => 1000.00,
                'usage_limit' => 50,
                'usage_limit_per_customer' => 1,
                'usage_count' => 0,
                'starts_at' => now(),
                'expires_at' => now()->addWeeks(2),
                'is_active' => true,
                'is_stackable' => false,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::updateOrCreate(
                ['code' => $coupon['code']], // Check by code
                $coupon // Update or create with all data
            );
        }
    }
}
