<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AdminSetting;

class AdminSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // General Settings
            [
                'key' => 'site_name',
                'value' => 'BookBharat',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Site Name',
                'description' => 'The name of your website',
                'input_type' => 'text',
                'sort_order' => 1,
                'is_public' => true
            ],
            [
                'key' => 'admin_email',
                'value' => 'admin@bookbharat.com',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Admin Email',
                'description' => 'Primary admin email address',
                'input_type' => 'email',
                'sort_order' => 2,
            ],
            [
                'key' => 'support_email',
                'value' => 'support@bookbharat.com',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Support Email',
                'description' => 'Customer support email address',
                'input_type' => 'email',
                'sort_order' => 3,
                'is_public' => true
            ],
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'general',
                'label' => 'Maintenance Mode',
                'description' => 'Enable maintenance mode to disable site access',
                'input_type' => 'switch',
                'sort_order' => 4,
            ],

            // Currency & Pricing
            [
                'key' => 'currency',
                'value' => 'INR',
                'type' => 'string',
                'group' => 'currency',
                'label' => 'Currency Code',
                'description' => 'Default currency code (ISO 4217)',
                'input_type' => 'select',
                'options' => [
                    'INR' => 'Indian Rupee (₹)',
                    'USD' => 'US Dollar ($)',
                    'EUR' => 'Euro (€)',
                    'GBP' => 'British Pound (£)'
                ],
                'sort_order' => 1,
                'is_public' => true
            ],
            [
                'key' => 'currency_symbol',
                'value' => '₹',
                'type' => 'string',
                'group' => 'currency',
                'label' => 'Currency Symbol',
                'description' => 'Currency symbol to display',
                'input_type' => 'text',
                'sort_order' => 2,
                'is_public' => true
            ],
            [
                'key' => 'min_order_amount',
                'value' => '100',
                'type' => 'integer',
                'group' => 'orders',
                'label' => 'Minimum Order Amount',
                'description' => 'Minimum amount required for placing an order',
                'input_type' => 'number',
                'sort_order' => 1,
                'is_public' => true
            ],
            [
                'key' => 'max_order_amount',
                'value' => '100000',
                'type' => 'integer',
                'group' => 'orders',
                'label' => 'Maximum Order Amount',
                'description' => 'Maximum amount allowed for a single order',
                'input_type' => 'number',
                'sort_order' => 2,
                'is_public' => true
            ],
            [
                'key' => 'free_shipping_threshold',
                'value' => '500',
                'type' => 'integer',
                'group' => 'shipping',
                'label' => 'Free Shipping Threshold',
                'description' => 'Order amount above which shipping is free',
                'input_type' => 'number',
                'sort_order' => 1,
                'is_public' => true
            ],

            // Payment Settings
            [
                'key' => 'cod_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment',
                'label' => 'Cash on Delivery',
                'description' => 'Enable Cash on Delivery payment option',
                'input_type' => 'switch',
                'sort_order' => 1,
                'is_public' => true
            ],
            [
                'key' => 'cod_extra_charge',
                'value' => '50',
                'type' => 'integer',
                'group' => 'payment',
                'label' => 'COD Extra Charge',
                'description' => 'Additional charge for Cash on Delivery',
                'input_type' => 'number',
                'sort_order' => 2,
                'is_public' => true
            ],
            [
                'key' => 'online_payment_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment',
                'label' => 'Online Payments',
                'description' => 'Enable online payment options',
                'input_type' => 'switch',
                'sort_order' => 3,
                'is_public' => true
            ],

            // Tax Settings
            [
                'key' => 'tax_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'tax',
                'label' => 'Enable Tax',
                'description' => 'Enable tax calculations',
                'input_type' => 'switch',
                'sort_order' => 1,
                'is_public' => true
            ],
            [
                'key' => 'gst_number',
                'value' => 'GSTIN123456789',
                'type' => 'string',
                'group' => 'tax',
                'label' => 'GST Number',
                'description' => 'Your business GST registration number',
                'input_type' => 'text',
                'sort_order' => 2,
                'is_public' => true
            ],

            // Features
            [
                'key' => 'enable_wishlist',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'features',
                'label' => 'Wishlist Feature',
                'description' => 'Allow customers to save items to wishlist',
                'input_type' => 'switch',
                'sort_order' => 1,
                'is_public' => true
            ],
            [
                'key' => 'enable_compare',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'features',
                'label' => 'Product Compare',
                'description' => 'Allow customers to compare products',
                'input_type' => 'switch',
                'sort_order' => 2,
                'is_public' => true
            ],
            [
                'key' => 'enable_coupons',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'features',
                'label' => 'Coupon System',
                'description' => 'Enable discount coupons',
                'input_type' => 'switch',
                'sort_order' => 3,
                'is_public' => true
            ],
            [
                'key' => 'allow_guest_checkout',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'features',
                'label' => 'Guest Checkout',
                'description' => 'Allow customers to checkout without registration',
                'input_type' => 'switch',
                'sort_order' => 4,
                'is_public' => true
            ],
            [
                'key' => 'auto_approve_reviews',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'features',
                'label' => 'Auto-approve Reviews',
                'description' => 'Automatically approve customer reviews',
                'input_type' => 'switch',
                'sort_order' => 5,
            ],

            // Display Settings
            [
                'key' => 'items_per_page',
                'value' => '20',
                'type' => 'integer',
                'group' => 'display',
                'label' => 'Items Per Page',
                'description' => 'Number of products to show per page',
                'input_type' => 'select',
                'options' => [
                    '10' => '10 items',
                    '20' => '20 items',
                    '30' => '30 items',
                    '50' => '50 items'
                ],
                'sort_order' => 1,
                'is_public' => true
            ],
            [
                'key' => 'date_format',
                'value' => 'd/m/Y',
                'type' => 'string',
                'group' => 'display',
                'label' => 'Date Format',
                'description' => 'Date display format',
                'input_type' => 'select',
                'options' => [
                    'd/m/Y' => '31/12/2023',
                    'm/d/Y' => '12/31/2023',
                    'Y-m-d' => '2023-12-31',
                    'd M Y' => '31 Dec 2023'
                ],
                'sort_order' => 2,
            ],

            // File Upload Settings
            [
                'key' => 'max_upload_size',
                'value' => '5',
                'type' => 'integer',
                'group' => 'uploads',
                'label' => 'Max Upload Size (MB)',
                'description' => 'Maximum file size for uploads in megabytes',
                'input_type' => 'number',
                'sort_order' => 1,
            ],
            [
                'key' => 'allowed_image_types',
                'value' => '["jpg","jpeg","png","webp"]',
                'type' => 'array',
                'group' => 'uploads',
                'label' => 'Allowed Image Types',
                'description' => 'Allowed file extensions for image uploads',
                'input_type' => 'multiselect',
                'options' => [
                    'jpg' => 'JPG',
                    'jpeg' => 'JPEG',
                    'png' => 'PNG',
                    'webp' => 'WebP',
                    'gif' => 'GIF'
                ],
                'sort_order' => 2,
            ],

            // Social Links
            [
                'key' => 'facebook_url',
                'value' => 'https://facebook.com/bookbharat',
                'type' => 'string',
                'group' => 'social',
                'label' => 'Facebook URL',
                'description' => 'Your Facebook page URL',
                'input_type' => 'url',
                'sort_order' => 1,
                'is_public' => true
            ],
            [
                'key' => 'twitter_url',
                'value' => 'https://twitter.com/bookbharat',
                'type' => 'string',
                'group' => 'social',
                'label' => 'Twitter URL',
                'description' => 'Your Twitter profile URL',
                'input_type' => 'url',
                'sort_order' => 2,
                'is_public' => true
            ],
            [
                'key' => 'instagram_url',
                'value' => 'https://instagram.com/bookbharat',
                'type' => 'string',
                'group' => 'social',
                'label' => 'Instagram URL',
                'description' => 'Your Instagram profile URL',
                'input_type' => 'url',
                'sort_order' => 3,
                'is_public' => true
            ],

            // Abandoned Cart Recovery Settings
            [
                'key' => 'recovery_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'abandoned_cart',
                'label' => 'Enable Abandoned Cart Recovery',
                'description' => 'Enable automated cart recovery emails',
                'input_type' => 'switch',
                'sort_order' => 1,
            ],
            [
                'key' => 'recovery_min_cart_value',
                'value' => '100',
                'type' => 'integer',
                'group' => 'abandoned_cart',
                'label' => 'Minimum Cart Value for Recovery',
                'description' => 'Only recover carts with value above this amount',
                'input_type' => 'number',
                'sort_order' => 2,
            ],
            [
                'key' => 'recovery_abandonment_time',
                'value' => '1',
                'type' => 'integer',
                'group' => 'abandoned_cart',
                'label' => 'Mark as Abandoned After (hours)',
                'description' => 'Hours of inactivity before marking cart as abandoned',
                'input_type' => 'number',
                'sort_order' => 3,
            ],
            [
                'key' => 'recovery_discount_second_email',
                'value' => '5',
                'type' => 'integer',
                'group' => 'abandoned_cart',
                'label' => 'Second Email Discount %',
                'description' => 'Discount percentage offered in second recovery email',
                'input_type' => 'number',
                'sort_order' => 4,
            ],
            [
                'key' => 'recovery_discount_final_email',
                'value' => '10',
                'type' => 'integer',
                'group' => 'abandoned_cart',
                'label' => 'Final Email Discount %',
                'description' => 'Discount percentage offered in final recovery email',
                'input_type' => 'number',
                'sort_order' => 5,
            ],
            [
                'key' => 'recovery_email_interval_first',
                'value' => '1',
                'type' => 'integer',
                'group' => 'abandoned_cart',
                'label' => 'First Email After (hours)',
                'description' => 'Send first recovery email after X hours',
                'input_type' => 'number',
                'sort_order' => 6,
            ],
            [
                'key' => 'recovery_email_interval_second',
                'value' => '24',
                'type' => 'integer',
                'group' => 'abandoned_cart',
                'label' => 'Second Email After (hours)',
                'description' => 'Send second recovery email after X hours',
                'input_type' => 'number',
                'sort_order' => 7,
            ],
            [
                'key' => 'recovery_email_interval_final',
                'value' => '48',
                'type' => 'integer',
                'group' => 'abandoned_cart',
                'label' => 'Final Email After (hours)',
                'description' => 'Send final recovery email after X hours',
                'input_type' => 'number',
                'sort_order' => 8,
            ],
            [
                'key' => 'recovery_discount_code_validity',
                'value' => '7',
                'type' => 'integer',
                'group' => 'abandoned_cart',
                'label' => 'Discount Code Validity (days)',
                'description' => 'Number of days discount codes remain valid',
                'input_type' => 'number',
                'sort_order' => 9,
            ],
            [
                'key' => 'recovery_sms_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'abandoned_cart',
                'label' => 'Enable SMS Recovery for High-Value Carts',
                'description' => 'Send SMS for carts above ₹1000',
                'input_type' => 'switch',
                'sort_order' => 10,
            ],
            [
                'key' => 'recovery_data_retention_days',
                'value' => '90',
                'type' => 'integer',
                'group' => 'abandoned_cart',
                'label' => 'Data Retention (days)',
                'description' => 'Delete abandoned carts after X days',
                'input_type' => 'number',
                'sort_order' => 11,
            ],
            [
                'key' => 'recovery_enable_analytics',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'abandoned_cart',
                'label' => 'Enable Recovery Analytics',
                'description' => 'Track recovery conversion rates and revenue',
                'input_type' => 'switch',
                'sort_order' => 12,
            ],
        ];

        foreach ($settings as $setting) {
            AdminSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('Admin settings seeded successfully!');
    }
}
