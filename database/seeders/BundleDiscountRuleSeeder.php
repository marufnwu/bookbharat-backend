<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BundleDiscountRule;

class BundleDiscountRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data first (except default ones from migration)
        BundleDiscountRule::where('name', 'like', 'Custom%')->delete();

        $rules = [
            // Enhanced bundle discounts with shipping benefits
            [
                'name' => 'Textbook Bundle (3+ Books)',
                'min_products' => 3,
                'max_products' => null,
                'discount_percentage' => 15.00,
                'discount_type' => 'percentage',
                'category_id' => null, // Would be set to textbook category ID if available
                'customer_tier' => null,
                'is_active' => true,
                'priority' => 5,
                'conditions' => json_encode([
                    'type' => 'category_bundle',
                    'categories' => ['Textbooks', 'Academic'],
                    'free_shipping' => true,
                    'free_shipping_threshold' => 699
                ]),
                'description' => 'Get 15% off on 3+ textbooks + Free shipping above ₹699',
            ],

            // Premium customer benefits
            [
                'name' => 'Premium Member Bundle',
                'min_products' => 2,
                'max_products' => null,
                'discount_percentage' => 20.00,
                'discount_type' => 'percentage',
                'category_id' => null,
                'customer_tier' => 'premium',
                'is_active' => true,
                'priority' => 8,
                'conditions' => json_encode([
                    'type' => 'membership_bundle',
                    'tiers' => ['premium', 'platinum'],
                    'free_shipping' => true,
                    'free_shipping_threshold' => 299
                ]),
                'description' => 'Premium members get 20% off on 2+ books + Free shipping above ₹299',
            ],

            // Seasonal/Promotional bundles
            [
                'name' => 'Back to School Special',
                'min_products' => 5,
                'max_products' => null,
                'discount_percentage' => 25.00,
                'discount_type' => 'percentage',
                'category_id' => null,
                'customer_tier' => null,
                'is_active' => false, // Can be activated during season
                'priority' => 15,
                'valid_from' => '2024-06-01 00:00:00',
                'valid_until' => '2024-08-31 23:59:59',
                'conditions' => json_encode([
                    'type' => 'seasonal',
                    'season' => 'back_to_school',
                    'free_shipping' => true,
                    'free_shipping_all_zones' => true
                ]),
                'description' => 'Back to School: 25% off on 5+ books + Free shipping to all zones',
            ],

            // Bulk purchase discount
            [
                'name' => 'Institutional Bulk Order',
                'min_products' => 10,
                'max_products' => null,
                'discount_percentage' => 30.00,
                'discount_type' => 'percentage',
                'category_id' => null,
                'customer_tier' => null,
                'is_active' => true,
                'priority' => 20,
                'conditions' => json_encode([
                    'type' => 'bulk_order',
                    'min_order_value' => 5000,
                    'free_shipping' => true,
                    'express_processing' => true,
                    'dedicated_support' => true
                ]),
                'description' => 'Bulk orders (10+ books): 30% off + Free express shipping',
            ],

            // Fixed discount bundles
            [
                'name' => 'Starter Pack',
                'min_products' => 3,
                'max_products' => 3,
                'discount_percentage' => 0.00, // Required field even for fixed discount
                'fixed_discount' => 100.00,
                'discount_type' => 'fixed',
                'category_id' => null,
                'customer_tier' => null,
                'is_active' => true,
                'priority' => 3,
                'conditions' => json_encode([
                    'type' => 'starter_pack',
                    'max_uses_per_customer' => 1,
                    'new_customers_only' => true,
                    'free_shipping_zone_a_b' => true
                ]),
                'description' => 'New customers: ₹100 off on your first 3-book bundle + Free metro shipping',
            ],

            // Category-specific bundles
            [
                'name' => 'Fiction Lovers Bundle',
                'min_products' => 4,
                'max_products' => null,
                'discount_percentage' => 18.00,
                'discount_type' => 'percentage',
                'category_id' => null, // Would be set to fiction category ID
                'customer_tier' => null,
                'is_active' => true,
                'priority' => 6,
                'conditions' => json_encode([
                    'type' => 'genre_bundle',
                    'genres' => ['Fiction', 'Literature', 'Novels'],
                    'combine_with_coupon' => true,
                    'free_shipping_threshold' => 799
                ]),
                'description' => 'Fiction bundle: 18% off on 4+ fiction books',
            ],

            // Weekend special (can be activated on weekends)
            [
                'name' => 'Weekend Flash Sale',
                'min_products' => 2,
                'max_products' => null,
                'discount_percentage' => 22.00,
                'discount_type' => 'percentage',
                'category_id' => null,
                'customer_tier' => null,
                'is_active' => false,
                'priority' => 12,
                'conditions' => json_encode([
                    'type' => 'flash_sale',
                    'days' => ['Saturday', 'Sunday'],
                    'free_shipping' => true,
                    'free_shipping_threshold' => 499,
                    'combine_with_wallet' => true
                ]),
                'description' => 'Weekend only: 22% off on 2+ books + Free shipping above ₹499',
            ],

            // Combo deals
            [
                'name' => 'Study Combo',
                'min_products' => 2,
                'max_products' => 4,
                'discount_percentage' => 12.00,
                'discount_type' => 'percentage',
                'category_id' => null,
                'customer_tier' => null,
                'is_active' => true,
                'priority' => 4,
                'conditions' => json_encode([
                    'type' => 'combo_deal',
                    'required_categories' => ['Textbook', 'Reference'],
                    'free_shipping_student' => true,
                    'student_id_required' => true
                ]),
                'description' => 'Student combo: 12% off on textbook + reference book combos',
            ],
        ];

        foreach ($rules as $rule) {
            BundleDiscountRule::create($rule);
        }

        $this->command->info('Enhanced bundle discount rules with shipping benefits seeded successfully!');
    }
}