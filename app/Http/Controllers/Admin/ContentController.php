<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Models\SiteConfiguration;

class ContentController extends Controller
{
    /**
     * Get site configuration
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
        ]);
    }

    /**
     * Get homepage configuration
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
        ]);
    }

    /**
     * Get navigation configuration
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
                            ['label' => 'Academic', 'url' => '/categories/academic']
                        ]
                    ],
                    [
                        'label' => 'Products',
                        'url' => '/products',
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
                    ]
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $navigation
        ]);
    }

    /**
     * Get all content pages
     */
    public function getPages()
    {
        $pages = [
            [
                'slug' => 'about',
                'title' => 'About BookBharat',
                'meta_title' => 'About Us - BookBharat',
            ],
            [
                'slug' => 'contact',
                'title' => 'Contact Us',
                'meta_title' => 'Contact Us - BookBharat',
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'meta_title' => 'Privacy Policy - BookBharat',
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms of Service',
                'meta_title' => 'Terms of Service - BookBharat',
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $pages
        ]);
    }

    /**
     * Get content page by slug
     */
    public function getPage($slug)
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
     * Update site configuration
     */
    public function updateSiteConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site.name' => 'required|string|max:255',
            'site.description' => 'required|string|max:500',
            'site.contact_email' => 'required|email',
            'site.contact_phone' => 'required|string',
            'theme.primary_color' => 'required|string',
            'theme.secondary_color' => 'required|string',
            'theme.accent_color' => 'required|string',
            'features' => 'required|array',
            'payment' => 'required|array',
            'shipping' => 'required|array',
            'social' => 'required|array',
            'seo' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update configuration
        $configData = $request->all();

        // Store in database or config file
        // For now, we'll use cache and assume config is stored elsewhere
        Cache::forget('site_config');
        Cache::put('site_config', $configData, 3600);

        return response()->json([
            'success' => true,
            'message' => 'Site configuration updated successfully',
            'data' => $configData
        ]);
    }

    /**
     * Update homepage configuration
     */
    public function updateHomepageConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'hero_section' => 'required|array',
            'hero_section.title' => 'required|string|max:255',
            'hero_section.subtitle' => 'required|string|max:500',
            'featured_sections' => 'required|array',
            'promotional_banners' => 'required|array',
            'newsletter' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $configData = $request->all();

        Cache::forget('homepage_config');
        Cache::put('homepage_config', $configData, 1800);

        return response()->json([
            'success' => true,
            'message' => 'Homepage configuration updated successfully',
            'data' => $configData
        ]);
    }

    /**
     * Update navigation configuration
     */
    public function updateNavigationConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'header_menu' => 'required|array',
            'footer_menu' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $configData = $request->all();

        Cache::forget('navigation_config');
        Cache::put('navigation_config', $configData, 3600);

        return response()->json([
            'success' => true,
            'message' => 'Navigation configuration updated successfully',
            'data' => $configData
        ]);
    }

    /**
     * Update content page
     */
    public function updateContentPage(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'meta_title' => 'required|string|max:255',
            'meta_description' => 'required|string|max:160'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $pageData = $request->all();

        // In a real implementation, this would be stored in database
        Cache::forget("content_page_{$slug}");
        Cache::put("content_page_{$slug}", $pageData, 3600);

        return response()->json([
            'success' => true,
            'message' => 'Content page updated successfully',
            'data' => $pageData
        ]);
    }

    /**
     * Upload media files
     */
    public function uploadMedia(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'type' => 'required|string|in:image,video,document'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $type = $request->input('type');

        // Store file
        $path = $file->store("media/{$type}", 'public');
        $url = asset("storage/{$path}");

        return response()->json([
            'success' => true,
            'message' => 'Media uploaded successfully',
            'data' => [
                'url' => $url,
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getMimeType()
            ]
        ]);
    }

    /**
     * Get media library
     */
    public function getMediaLibrary(Request $request)
    {
        $type = $request->input('type', 'all');
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

        // In a real implementation, this would query the database
        // For now, return mock data
        $media = [
            [
                'id' => 1,
                'url' => asset('storage/media/image/hero-bg.jpg'),
                'name' => 'hero-bg.jpg',
                'type' => 'image/jpeg',
                'size' => 245760,
                'created_at' => now()->subDays(5)->toISOString()
            ],
            [
                'id' => 2,
                'url' => asset('storage/media/image/logo.png'),
                'name' => 'logo.png',
                'type' => 'image/png',
                'size' => 12840,
                'created_at' => now()->subDays(3)->toISOString()
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $media,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => count($media),
                'last_page' => 1
            ]
        ]);
    }

    /**
     * Delete media file
     */
    public function deleteMedia($id)
    {
        // In a real implementation, this would delete from storage and database
        return response()->json([
            'success' => true,
            'message' => 'Media deleted successfully'
        ]);
    }

    /**
     * Get theme presets
     */
    public function getThemePresets()
    {
        $presets = [
            'default' => [
                'name' => 'Default',
                'primary_color' => '#1e40af',
                'secondary_color' => '#f59e0b',
                'accent_color' => '#10b981',
                'success_color' => '#10b981',
                'warning_color' => '#f59e0b',
                'error_color' => '#ef4444'
            ],
            'dark' => [
                'name' => 'Dark Mode',
                'primary_color' => '#3b82f6',
                'secondary_color' => '#fbbf24',
                'accent_color' => '#34d399',
                'success_color' => '#34d399',
                'warning_color' => '#fbbf24',
                'error_color' => '#f87171'
            ],
            'elegant' => [
                'name' => 'Elegant',
                'primary_color' => '#6366f1',
                'secondary_color' => '#f43f5e',
                'accent_color' => '#06b6d4',
                'success_color' => '#10b981',
                'warning_color' => '#f59e0b',
                'error_color' => '#ef4444'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $presets
        ]);
    }
}
