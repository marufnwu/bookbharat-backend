<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use App\Models\SiteConfiguration;

class ConfigurationController extends Controller
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get site configuration for frontend
     */
    public function getSiteConfig()
    {
        $config = Cache::remember('site_config', 3600, function () {
            // Get saved configurations or fallback to defaults
            $siteConfig = SiteConfiguration::getByGroup('site');
            $themeConfig = SiteConfiguration::getByGroup('theme');
            $featuresConfig = SiteConfiguration::getByGroup('features');
            $socialConfig = SiteConfiguration::getByGroup('social');
            $seoConfig = SiteConfiguration::getByGroup('seo');

            return [
                'site' => [
                    'name' => $siteConfig['name'] ?? config('app.name', 'BookBharat'),
                    'description' => $siteConfig['description'] ?? 'India\'s Premier Online Bookstore',
                    'logo' => $siteConfig['logo'] ?? asset('images/logo.png'),
                    'favicon' => $siteConfig['favicon'] ?? asset('images/favicon.ico'),
                    'contact_email' => $siteConfig['contact_email'] ?? 'support@bookbharat.com',
                    'contact_phone' => $siteConfig['contact_phone'] ?? '+91 9876543210',
                    'address' => $siteConfig['address'] ?? [
                        'line1' => 'BookBharat HQ',
                        'line2' => '123 Knowledge Street',
                        'city' => 'Mumbai',
                        'state' => 'Maharashtra',
                        'pincode' => '400001',
                        'country' => 'India'
                    ]
                ],
                'theme' => [
                    'primary_color' => $themeConfig['primary_color'] ?? '#1e40af',
                    'secondary_color' => $themeConfig['secondary_color'] ?? '#f59e0b',
                    'accent_color' => $themeConfig['accent_color'] ?? '#10b981',
                    'success_color' => $themeConfig['success_color'] ?? '#10b981',
                    'warning_color' => $themeConfig['warning_color'] ?? '#f59e0b',
                    'error_color' => $themeConfig['error_color'] ?? '#ef4444',
                    'font_family' => $themeConfig['font_family'] ?? 'Inter, sans-serif',
                    'header_style' => $themeConfig['header_style'] ?? 'standard',
                    'footer_style' => $themeConfig['footer_style'] ?? 'standard',
                    'layout' => $themeConfig['layout'] ?? 'standard',
                    'banner_style' => $themeConfig['banner_style'] ?? 'gradient'
                ],
                'features' => [
                    'wishlist_enabled' => $featuresConfig['wishlist_enabled'] ?? true,
                    'reviews_enabled' => $featuresConfig['reviews_enabled'] ?? true,
                    'chat_support_enabled' => $featuresConfig['chat_support_enabled'] ?? true,
                    'notifications_enabled' => $featuresConfig['notifications_enabled'] ?? true,
                    'newsletter_enabled' => $featuresConfig['newsletter_enabled'] ?? true,
                    'social_login_enabled' => $featuresConfig['social_login_enabled'] ?? true,
                    'guest_checkout_enabled' => $featuresConfig['guest_checkout_enabled'] ?? true,
                    'multi_currency_enabled' => $featuresConfig['multi_currency_enabled'] ?? false,
                    'inventory_tracking_enabled' => $featuresConfig['inventory_tracking_enabled'] ?? true,
                    'promotional_banners_enabled' => $featuresConfig['promotional_banners_enabled'] ?? true
                ],
                'payment' => [
                    'methods_enabled' => [
                        'cod' => true,
                        'razorpay' => true,
                        'paypal' => false,
                        'stripe' => false,
                        'bank_transfer' => true
                    ],
                    'currency' => 'INR',
                    'currency_symbol' => '₹',
                    'min_order_amount' => 99,
                    'free_shipping_threshold' => 499
                ],
                'shipping' => [
                    'zones_enabled' => true,
                    'weight_based_shipping' => true,
                    'flat_rate_shipping' => false,
                    'local_pickup_enabled' => true,
                    'express_delivery_enabled' => true,
                    'cod_available' => true,
                    'insurance_enabled' => true
                ],
                'social' => [
                    'facebook_url' => 'https://facebook.com/bookbharat',
                    'twitter_url' => 'https://twitter.com/bookbharat',
                    'instagram_url' => 'https://instagram.com/bookbharat',
                    'youtube_url' => 'https://youtube.com/bookbharat',
                    'linkedin_url' => 'https://linkedin.com/company/bookbharat'
                ],
                'seo' => [
                    'meta_title' => 'BookBharat - Your Knowledge Partner for Life',
                    'meta_description' => 'Discover millions of books across all genres. From bestselling novels to academic texts, find your next great read with India\'s most trusted bookstore.',
                    'meta_keywords' => ['books', 'online bookstore', 'india', 'novels', 'academic books', 'textbooks'],
                    'og_image' => asset('images/og-image.jpg'),
                    'twitter_card' => 'summary_large_image'
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $config
        ])->header('Access-Control-Allow-Origin', '*')
          ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
          ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Request-With');
    }

    /**
     * Get homepage content configuration
     */
    public function getHomepageConfig()
    {
        $config = Cache::remember('homepage_config', 1800, function () {
            return [
                'hero_section' => [
                    'enabled' => true,
                    'title' => 'Your Knowledge Partner for Life',
                    'subtitle' => 'Discover millions of books across all genres. From bestselling novels to academic texts, find your next great read with India\'s most trusted bookstore.',
                    'background_image' => asset('images/hero-bg.jpg'),
                    'background_video' => null,
                    'cta_primary' => [
                        'text' => 'Explore Books',
                        'url' => '/products',
                        'style' => 'primary'
                    ],
                    'cta_secondary' => [
                        'text' => 'Browse Categories',
                        'url' => '/categories',
                        'style' => 'outline'
                    ],
                    'stats' => [
                        ['label' => 'Books', 'value' => '500K+'],
                        ['label' => 'Customers', 'value' => '100K+'],
                        ['label' => 'Rating', 'value' => '4.8★']
                    ]
                ],
                'featured_sections' => [
                    [
                        'id' => 'featured_books',
                        'title' => 'Featured Books',
                        'subtitle' => 'Discover our handpicked selection of must-read books',
                        'type' => 'featured_products',
                        'enabled' => true,
                        'settings' => [
                            'limit' => 8,
                            'layout' => 'grid',
                            'show_category' => true,
                            'show_rating' => true,
                            'show_discount' => true
                        ]
                    ],
                    [
                        'id' => 'categories',
                        'title' => 'Browse by Category',
                        'subtitle' => 'Find books in your favorite genres',
                        'type' => 'categories',
                        'enabled' => true,
                        'settings' => [
                            'limit' => 8,
                            'layout' => 'grid',
                            'show_count' => true,
                            'show_icons' => true
                        ]
                    ],
                    [
                        'id' => 'category_products',
                        'title' => 'Shop by Categories',
                        'subtitle' => 'Explore our collection of books in different categories',
                        'type' => 'category_products',
                        'enabled' => true,
                        'settings' => [
                            'show_all_categories' => false, // If true, shows all categories; if false, shows only specific ones
                            'categories_limit' => 6, // Number of categories to show
                            'products_per_category' => 4, // Products to show per category (admin configurable)
                            'lazy_load' => true, // Enable lazy loading
                            'show_see_all_button' => true, // Show "See All" button for each category
                            'layout' => 'carousel', // grid or carousel
                            'show_category_description' => true,
                            'show_product_rating' => true,
                            'show_product_discount' => true
                        ]
                    ],
                    [
                        'id' => 'testimonials',
                        'title' => 'What Our Customers Say',
                        'subtitle' => 'Join thousands of satisfied book lovers',
                        'type' => 'testimonials',
                        'enabled' => true,
                        'settings' => [
                            'limit' => 6,
                            'layout' => 'carousel',
                            'show_ratings' => true,
                            'auto_rotate' => true
                        ]
                    ]
                ],
                'promotional_banners' => [
                    [
                        'id' => 'free_shipping',
                        'title' => 'Free Shipping',
                        'description' => 'On orders above ₹499',
                        'icon' => 'truck',
                        'color' => 'primary',
                        'enabled' => true
                    ],
                    [
                        'id' => 'easy_returns',
                        'title' => 'Easy Returns',
                        'description' => '30-day return policy',
                        'icon' => 'refresh',
                        'color' => 'success',
                        'enabled' => true
                    ],
                    [
                        'id' => 'secure_payment',
                        'title' => 'Secure Payment',
                        'description' => '100% secure transactions',
                        'icon' => 'shield',
                        'color' => 'accent',
                        'enabled' => true
                    ]
                ],
                'newsletter' => [
                    'enabled' => true,
                    'title' => 'Stay Updated',
                    'subtitle' => 'Subscribe to our newsletter for latest book releases and exclusive offers',
                    'placeholder' => 'Enter your email address',
                    'button_text' => 'Subscribe',
                    'privacy_text' => 'We respect your privacy and never share your email.',
                    'background_color' => 'primary'
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $config
        ])->header('Access-Control-Allow-Origin', '*')
          ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
          ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Request-With');
    }

    /**
     * Get content pages (About, Contact, Privacy, etc.)
     */
    public function getContentPage($slug)
    {
        $pages = [
            'about' => [
                'title' => 'About BookBharat',
                'content' => 'We are India\'s premier online bookstore, dedicated to making knowledge accessible to everyone...',
                'meta_title' => 'About Us - BookBharat',
                'meta_description' => 'Learn about BookBharat, India\'s premier online bookstore.'
            ],
            'contact' => [
                'title' => 'Contact Us',
                'content' => 'Get in touch with us for any queries or support...',
                'meta_title' => 'Contact Us - BookBharat',
                'meta_description' => 'Contact BookBharat for customer support and inquiries.'
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'content' => 'Your privacy is important to us. This policy explains how we collect and use your data...',
                'meta_title' => 'Privacy Policy - BookBharat',
                'meta_description' => 'Read our privacy policy to understand how we handle your data.'
            ],
            'terms' => [
                'title' => 'Terms of Service',
                'content' => 'By using our service, you agree to these terms and conditions...',
                'meta_title' => 'Terms of Service - BookBharat',
                'meta_description' => 'Read our terms of service and conditions of use.'
            ]
        ];

        if (!isset($pages[$slug])) {
            return response()->json([
                'success' => false,
                'message' => 'Page not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $pages[$slug]
        ]);
    }

    /**
     * Get navigation menu configuration
     */
    public function getNavigationConfig()
    {
        $navigation = Cache::remember('navigation_config', 3600, function () {
            return [
                'header_menu' => [
                    [
                        'label' => 'Home',
                        'url' => '/',
                        'external' => false,
                        'children' => []
                    ],
                    [
                        'label' => 'Categories',
                        'url' => '/categories',
                        'external' => false,
                        'children' => [
                            ['label' => 'Fiction', 'url' => '/categories/fiction'],
                            ['label' => 'Non-Fiction', 'url' => '/categories/non-fiction'],
                            ['label' => 'Academic', 'url' => '/categories/academic'],
                            ['label' => 'Children\'s Books', 'url' => '/categories/childrens']
                        ]
                    ],
                    [
                        'label' => 'Products',
                        'url' => '/products',
                        'external' => false,
                        'children' => []
                    ],
                    [
                        'label' => 'About',
                        'url' => '/about',
                        'external' => false,
                        'children' => []
                    ],
                    [
                        'label' => 'Contact',
                        'url' => '/contact',
                        'external' => false,
                        'children' => []
                    ]
                ],
                'footer_menu' => [
                    [
                        'title' => 'Quick Links',
                        'links' => [
                            ['label' => 'About Us', 'url' => '/about'],
                            ['label' => 'Contact', 'url' => '/contact'],
                            ['label' => 'Privacy Policy', 'url' => '/privacy'],
                            ['label' => 'Terms of Service', 'url' => '/terms']
                        ]
                    ],
                    [
                        'title' => 'Categories',
                        'links' => [
                            ['label' => 'Fiction', 'url' => '/categories/fiction'],
                            ['label' => 'Non-Fiction', 'url' => '/categories/non-fiction'],
                            ['label' => 'Academic', 'url' => '/categories/academic'],
                            ['label' => 'Children\'s Books', 'url' => '/categories/childrens']
                        ]
                    ],
                    [
                        'title' => 'Customer Service',
                        'links' => [
                            ['label' => 'Help Center', 'url' => '/help'],
                            ['label' => 'Track Order', 'url' => '/orders'],
                            ['label' => 'Returns', 'url' => '/returns'],
                            ['label' => 'Shipping Info', 'url' => '/shipping-info']
                        ]
                    ]
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $navigation
        ])->header('Access-Control-Allow-Origin', '*')
          ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
          ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Request-With');
    }

    /**
     * Update site configuration
     */
    public function updateSiteConfig(Request $request)
    {
        try {
            \Log::info('Site config update request data:', $request->all());

            $validated = $request->validate([
                'site' => 'sometimes|array',
                'theme' => 'sometimes|array',
                'features' => 'sometimes|array',
                'social' => 'sometimes|array',
                'seo' => 'sometimes|array',
            ]);

            \Log::info('Validated data:', $validated);

            // Get old configuration for audit log
            $oldConfig = [];
            foreach ($validated as $group => $config) {
                $oldConfig[$group] = SiteConfiguration::getByGroup($group);
            }

            // Update each configuration group
            foreach ($validated as $group => $config) {
                \Log::info("Processing group: $group with config:", $config);
                foreach ($config as $key => $value) {
                    \Log::info("Setting key: $key = $value in group: $group");
                    $result = SiteConfiguration::setValue($key, $value, $group);
                    \Log::info("Save result:", $result->toArray());
                }
            }

            // Log configuration change
            $this->auditLogService->logConfigChange('site', $oldConfig, $validated);

            // Clear the cache to force refresh
            Cache::forget('site_config');

            return response()->json([
                'success' => true,
                'message' => 'Site configuration updated successfully'
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Request-With');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating configuration'
            ], 500);
        }
    }
}
