<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SystemFlexibilityController extends Controller
{
    /**
     * Get all feature flags
     */
    public function getFeatureFlags()
    {
        $flags = [
            // E-commerce Features
            ['key' => 'enable_reviews', 'name' => 'Enable Product Reviews', 'value' => AdminSetting::get('enable_reviews', true), 'category' => 'ecommerce'],
            ['key' => 'enable_wishlist', 'name' => 'Enable Wishlist', 'value' => AdminSetting::get('enable_wishlist', true), 'category' => 'ecommerce'],
            ['key' => 'enable_recommendations', 'name' => 'Enable Product Recommendations', 'value' => AdminSetting::get('enable_recommendations', true), 'category' => 'ecommerce'],
            ['key' => 'enable_bundle_discounts', 'name' => 'Enable Bundle Discounts', 'value' => AdminSetting::get('enable_bundle_discounts', true), 'category' => 'ecommerce'],
            ['key' => 'enable_coupons', 'name' => 'Enable Coupons', 'value' => AdminSetting::get('enable_coupons', true), 'category' => 'ecommerce'],
            ['key' => 'enable_guest_checkout', 'name' => 'Allow Guest Checkout', 'value' => AdminSetting::get('enable_guest_checkout', true), 'category' => 'ecommerce'],

            // User Features
            ['key' => 'enable_user_registration', 'name' => 'Enable User Registration', 'value' => AdminSetting::get('enable_user_registration', true), 'category' => 'users'],
            ['key' => 'enable_social_login', 'name' => 'Enable Social Login', 'value' => AdminSetting::get('enable_social_login', false), 'category' => 'users'],
            ['key' => 'require_email_verification', 'name' => 'Require Email Verification', 'value' => AdminSetting::get('require_email_verification', false), 'category' => 'users'],
            ['key' => 'enable_two_factor_auth', 'name' => 'Enable 2FA', 'value' => AdminSetting::get('enable_two_factor_auth', false), 'category' => 'users'],

            // Notifications
            ['key' => 'enable_email_notifications', 'name' => 'Email Notifications', 'value' => AdminSetting::get('enable_email_notifications', true), 'category' => 'notifications'],
            ['key' => 'enable_sms_notifications', 'name' => 'SMS Notifications', 'value' => AdminSetting::get('enable_sms_notifications', false), 'category' => 'notifications'],
            ['key' => 'enable_push_notifications', 'name' => 'Push Notifications', 'value' => AdminSetting::get('enable_push_notifications', false), 'category' => 'notifications'],

            // Payment
            ['key' => 'enable_cod', 'name' => 'Cash on Delivery', 'value' => AdminSetting::get('enable_cod', true), 'category' => 'payment'],
            ['key' => 'enable_online_payment', 'name' => 'Online Payment', 'value' => AdminSetting::get('enable_online_payment', true), 'category' => 'payment'],

            // Analytics
            ['key' => 'enable_analytics', 'name' => 'Enable Analytics Tracking', 'value' => AdminSetting::get('enable_analytics', true), 'category' => 'analytics'],
            ['key' => 'enable_google_analytics', 'name' => 'Google Analytics', 'value' => AdminSetting::get('enable_google_analytics', false), 'category' => 'analytics'],

            // Performance
            ['key' => 'enable_caching', 'name' => 'Enable Caching', 'value' => AdminSetting::get('enable_caching', true), 'category' => 'performance'],
            ['key' => 'enable_query_logging', 'name' => 'Enable Query Logging', 'value' => AdminSetting::get('enable_query_logging', false), 'category' => 'performance'],
            ['key' => 'enable_api_rate_limiting', 'name' => 'API Rate Limiting', 'value' => AdminSetting::get('enable_api_rate_limiting', true), 'category' => 'performance'],

            // Security
            ['key' => 'enable_ip_whitelist', 'name' => 'IP Whitelist', 'value' => AdminSetting::get('enable_ip_whitelist', false), 'category' => 'security'],
            ['key' => 'enable_login_attempt_limit', 'name' => 'Login Attempt Limiting', 'value' => AdminSetting::get('enable_login_attempt_limit', true), 'category' => 'security'],
            ['key' => 'enable_admin_approval', 'name' => 'Require Admin Approval for New Users', 'value' => AdminSetting::get('enable_admin_approval', false), 'category' => 'security'],
        ];

        return response()->json([
            'success' => true,
            'flags' => $flags,
            'categories' => [
                'ecommerce' => 'E-commerce',
                'users' => 'Users',
                'notifications' => 'Notifications',
                'payment' => 'Payment',
                'analytics' => 'Analytics',
                'performance' => 'Performance',
                'security' => 'Security',
            ]
        ]);
    }

    /**
     * Update feature flag
     */
    public function updateFeatureFlag(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'required|boolean'
        ]);

        AdminSetting::set($request->key, $request->value);

        // Clear relevant caches
        Cache::forget('feature_flags');

        return response()->json([
            'success' => true,
            'message' => 'Feature flag updated successfully',
            'key' => $request->key,
            'value' => $request->value
        ]);
    }

    /**
     * Get maintenance mode status
     */
    public function getMaintenanceMode()
    {
        $isDown = app()->isDownForMaintenance();

        return response()->json([
            'success' => true,
            'maintenance_mode' => [
                'enabled' => $isDown,
                'message' => AdminSetting::get('maintenance_message', 'We are currently performing scheduled maintenance. Please check back soon.'),
                'allowed_ips' => AdminSetting::get('maintenance_allowed_ips', []),
                'retry_after' => AdminSetting::get('maintenance_retry_after', 3600),
                'redirect' => AdminSetting::get('maintenance_redirect_url', null),
            ]
        ]);
    }

    /**
     * Toggle maintenance mode
     */
    public function toggleMaintenanceMode(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'message' => 'nullable|string',
            'allowed_ips' => 'nullable|array',
            'retry_after' => 'nullable|integer',
            'redirect' => 'nullable|url',
        ]);

        // Save settings
        if ($request->has('message')) {
            AdminSetting::set('maintenance_message', $request->message);
        }
        if ($request->has('allowed_ips')) {
            AdminSetting::set('maintenance_allowed_ips', $request->allowed_ips);
        }
        if ($request->has('retry_after')) {
            AdminSetting::set('maintenance_retry_after', $request->retry_after);
        }
        if ($request->has('redirect')) {
            AdminSetting::set('maintenance_redirect_url', $request->redirect);
        }

        // Toggle maintenance mode
        if ($request->enabled) {
            Artisan::call('down', [
                '--retry' => $request->input('retry_after', 60),
                '--secret' => AdminSetting::get('maintenance_secret', config('app.key')),
            ]);
        } else {
            Artisan::call('up');
        }

        return response()->json([
            'success' => true,
            'message' => $request->enabled ? 'Maintenance mode enabled' : 'Maintenance mode disabled',
            'maintenance_mode' => $request->enabled
        ]);
    }

    /**
     * Get API rate limiting configuration
     */
    public function getApiRateLimits()
    {
        return response()->json([
            'success' => true,
            'rate_limits' => [
                'guest' => [
                    'requests_per_minute' => AdminSetting::get('rate_limit_guest_rpm', 60),
                    'requests_per_hour' => AdminSetting::get('rate_limit_guest_rph', 1000),
                ],
                'authenticated' => [
                    'requests_per_minute' => AdminSetting::get('rate_limit_auth_rpm', 120),
                    'requests_per_hour' => AdminSetting::get('rate_limit_auth_rph', 5000),
                ],
                'admin' => [
                    'requests_per_minute' => AdminSetting::get('rate_limit_admin_rpm', 300),
                    'requests_per_hour' => AdminSetting::get('rate_limit_admin_rph', 10000),
                ],
            ]
        ]);
    }

    /**
     * Alias for getRateLimiting route compatibility
     */
    public function getRateLimiting()
    {
        return $this->getApiRateLimits();
    }

    /**
     * Update API rate limits
     */
    public function updateApiRateLimits(Request $request)
    {
        $request->validate([
            'guest_rpm' => 'required|integer|min:1|max:1000',
            'guest_rph' => 'required|integer|min:10|max:100000',
            'auth_rpm' => 'required|integer|min:1|max:1000',
            'auth_rph' => 'required|integer|min:10|max:100000',
            'admin_rpm' => 'required|integer|min:1|max:1000',
            'admin_rph' => 'required|integer|min:10|max:100000',
        ]);

        AdminSetting::set('rate_limit_guest_rpm', $request->guest_rpm);
        AdminSetting::set('rate_limit_guest_rph', $request->guest_rph);
        AdminSetting::set('rate_limit_auth_rpm', $request->auth_rpm);
        AdminSetting::set('rate_limit_auth_rph', $request->auth_rph);
        AdminSetting::set('rate_limit_admin_rpm', $request->admin_rpm);
        AdminSetting::set('rate_limit_admin_rph', $request->admin_rph);

        return response()->json([
            'success' => true,
            'message' => 'API rate limits updated successfully'
        ]);
    }

    /**
     * Get module settings
     */
    public function getModules()
    {
        $modules = [
            [
                'key' => 'products',
                'name' => 'Product Management',
                'enabled' => true,
                'locked' => true, // Core module
                'description' => 'Core product catalog and management',
                'routes_count' => 15,
            ],
            [
                'key' => 'orders',
                'name' => 'Order Management',
                'enabled' => true,
                'locked' => true, // Core module
                'description' => 'Order processing and fulfillment',
                'routes_count' => 20,
            ],
            [
                'key' => 'reviews',
                'name' => 'Reviews & Ratings',
                'enabled' => AdminSetting::get('module_reviews_enabled', true),
                'locked' => false,
                'description' => 'Product reviews and ratings system',
                'routes_count' => 8,
            ],
            [
                'key' => 'coupons',
                'name' => 'Coupons & Discounts',
                'enabled' => AdminSetting::get('module_coupons_enabled', true),
                'locked' => false,
                'description' => 'Promotional coupons and discount codes',
                'routes_count' => 6,
            ],
            [
                'key' => 'loyalty',
                'name' => 'Loyalty Program',
                'enabled' => AdminSetting::get('module_loyalty_enabled', false),
                'locked' => false,
                'description' => 'Customer loyalty points and rewards',
                'routes_count' => 10,
            ],
            [
                'key' => 'referrals',
                'name' => 'Referral Program',
                'enabled' => AdminSetting::get('module_referrals_enabled', false),
                'locked' => false,
                'description' => 'Customer referral tracking and rewards',
                'routes_count' => 7,
            ],
            [
                'key' => 'analytics',
                'name' => 'Advanced Analytics',
                'enabled' => AdminSetting::get('module_analytics_enabled', true),
                'locked' => false,
                'description' => 'Advanced business intelligence and reporting',
                'routes_count' => 12,
            ],
            [
                'key' => 'social_commerce',
                'name' => 'Social Commerce',
                'enabled' => AdminSetting::get('module_social_enabled', false),
                'locked' => false,
                'description' => 'Social media integration and sharing',
                'routes_count' => 8,
            ],
        ];

        return response()->json([
            'success' => true,
            'modules' => $modules,
            'stats' => [
                'total' => count($modules),
                'enabled' => collect($modules)->where('enabled', true)->count(),
                'locked' => collect($modules)->where('locked', true)->count(),
            ]
        ]);
    }

    /**
     * Toggle module
     */
    public function toggleModule(Request $request)
    {
        $request->validate([
            'module_key' => 'required|string',
            'enabled' => 'required|boolean'
        ]);

        // Prevent disabling core modules
        $coreModules = ['products', 'orders', 'users', 'payments'];
        if (in_array($request->module_key, $coreModules)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot disable core modules'
            ], 403);
        }

        AdminSetting::set("module_{$request->module_key}_enabled", $request->enabled);

        return response()->json([
            'success' => true,
            'message' => $request->enabled ? 'Module enabled' : 'Module disabled'
        ]);
    }

    /**
     * Get system presets/configurations
     */
    public function getPresets()
    {
        $presets = [
            [
                'id' => 'default',
                'name' => 'Default Configuration',
                'description' => 'Standard e-commerce setup',
                'settings' => [
                    'enable_reviews' => true,
                    'enable_wishlist' => true,
                    'enable_coupons' => true,
                    'enable_guest_checkout' => true,
                ]
            ],
            [
                'id' => 'minimal',
                'name' => 'Minimal Setup',
                'description' => 'Only essential features enabled',
                'settings' => [
                    'enable_reviews' => false,
                    'enable_wishlist' => false,
                    'enable_coupons' => false,
                    'enable_guest_checkout' => true,
                ]
            ],
            [
                'id' => 'advanced',
                'name' => 'Advanced E-commerce',
                'description' => 'All features enabled for maximum functionality',
                'settings' => [
                    'enable_reviews' => true,
                    'enable_wishlist' => true,
                    'enable_coupons' => true,
                    'enable_bundle_discounts' => true,
                    'enable_recommendations' => true,
                    'module_loyalty_enabled' => true,
                    'module_referrals_enabled' => true,
                ]
            ],
        ];

        return response()->json([
            'success' => true,
            'presets' => $presets
        ]);
    }

    /**
     * Apply preset configuration
     */
    public function applyPreset(Request $request)
    {
        $request->validate([
            'preset_id' => 'required|string|in:default,minimal,advanced'
        ]);

        $presets = [
            'default' => [
                'enable_reviews' => true,
                'enable_wishlist' => true,
                'enable_coupons' => true,
                'enable_guest_checkout' => true,
                'enable_recommendations' => true,
            ],
            'minimal' => [
                'enable_reviews' => false,
                'enable_wishlist' => false,
                'enable_coupons' => false,
                'enable_guest_checkout' => true,
                'enable_recommendations' => false,
            ],
            'advanced' => [
                'enable_reviews' => true,
                'enable_wishlist' => true,
                'enable_coupons' => true,
                'enable_bundle_discounts' => true,
                'enable_recommendations' => true,
                'module_loyalty_enabled' => true,
                'module_referrals_enabled' => true,
                'enable_analytics' => true,
            ],
        ];

        $settings = $presets[$request->preset_id];

        foreach ($settings as $key => $value) {
            AdminSetting::set($key, $value);
        }

        // Clear cache
        Cache::flush();

        return response()->json([
            'success' => true,
            'message' => 'Preset applied successfully',
            'preset_id' => $request->preset_id,
            'settings_applied' => count($settings)
        ]);
    }

    /**
     * Get IP restrictions
     */
    public function getIpRestrictions()
    {
        return response()->json([
            'success' => true,
            'restrictions' => [
                'enabled' => AdminSetting::get('ip_restrictions_enabled', false),
                'whitelist' => AdminSetting::get('ip_whitelist', []),
                'blacklist' => AdminSetting::get('ip_blacklist', []),
                'admin_whitelist_only' => AdminSetting::get('admin_ip_whitelist_only', false),
            ]
        ]);
    }

    /**
     * Update IP restrictions
     */
    public function updateIpRestrictions(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'whitelist' => 'nullable|array',
            'whitelist.*' => 'ip',
            'blacklist' => 'nullable|array',
            'blacklist.*' => 'ip',
            'admin_whitelist_only' => 'boolean',
        ]);

        AdminSetting::set('ip_restrictions_enabled', $request->enabled);
        AdminSetting::set('ip_whitelist', $request->input('whitelist', []));
        AdminSetting::set('ip_blacklist', $request->input('blacklist', []));
        AdminSetting::set('admin_ip_whitelist_only', $request->input('admin_whitelist_only', false));

        return response()->json([
            'success' => true,
            'message' => 'IP restrictions updated successfully'
        ]);
    }

    /**
     * Export system configuration
     */
    public function exportConfiguration()
    {
        $config = AdminSetting::all()->mapWithKeys(function ($setting) {
            return [$setting->key => $setting->value];
        });

        return response()->json([
            'success' => true,
            'configuration' => $config,
            'exported_at' => now()->toDateTimeString(),
            'version' => config('app.version', '1.0.0')
        ]);
    }

    /**
     * Import system configuration
     */
    public function importConfiguration(Request $request)
    {
        $request->validate([
            'configuration' => 'required|array',
            'overwrite' => 'boolean',
        ]);

        $overwrite = $request->input('overwrite', false);
        $imported = 0;
        $skipped = 0;

        foreach ($request->configuration as $key => $value) {
            if (!$overwrite && AdminSetting::where('key', $key)->exists()) {
                $skipped++;
                continue;
            }

            AdminSetting::set($key, $value);
            $imported++;
        }

        // Clear cache after import
        Cache::flush();

        return response()->json([
            'success' => true,
            'message' => 'Configuration imported successfully',
            'stats' => [
                'imported' => $imported,
                'skipped' => $skipped,
                'total' => count($request->configuration)
            ]
        ]);
    }
}
