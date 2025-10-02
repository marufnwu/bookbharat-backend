<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminSetting;
use App\Models\PaymentSetting;
use App\Models\PaymentConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SettingsController extends Controller
{
    /**
     * Get all settings grouped by category
     */
    public function getGeneral()
    {
        $settings = AdminSetting::getAllGrouped();

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }

    /**
     * Get settings for a specific group
     */
    public function getByGroup(Request $request, string $group)
    {
        $settings = AdminSetting::getByGroup($group);

        return response()->json([
            'success' => true,
            'group' => $group,
            'settings' => $settings
        ]);
    }

    /**
     * Get public settings (for frontend)
     */
    public function getPublicSettings()
    {
        $settings = AdminSetting::where('is_public', true)
            ->get()
            ->keyBy('key')
            ->map(function ($setting) {
                return AdminSetting::get($setting->key);
            });

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }

    /**
     * Update multiple settings
     */
    public function updateGeneral(Request $request)
    {
        $settings = $request->input('settings', []);
        $updated = [];
        $errors = [];

        foreach ($settings as $key => $value) {
            // Validate that the setting exists and is editable
            $setting = AdminSetting::where('key', $key)->where('is_editable', true)->first();

            if (!$setting) {
                $errors[$key] = 'Setting not found or not editable';
                continue;
            }

            // Basic validation based on setting type
            $validator = $this->validateSettingValue($key, $value, $setting);

            if ($validator && $validator->fails()) {
                $errors[$key] = $validator->errors()->first();
                continue;
            }

            // Update the setting
            if (AdminSetting::set($key, $value)) {
                $updated[$key] = $value;
            } else {
                $errors[$key] = 'Failed to update setting';
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Some settings failed to update',
                'updated' => $updated,
                'errors' => $errors
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'updated' => $updated
        ]);
    }

    /**
     * Update a single setting
     */
    public function updateSetting(Request $request, string $key)
    {
        $setting = AdminSetting::where('key', $key)->where('is_editable', true)->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found or not editable'
            ], 404);
        }

        $value = $request->input('value');

        // Validate the value
        $validator = $this->validateSettingValue($key, $value, $setting);

        if ($validator && $validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update the setting
        if (AdminSetting::set($key, $value)) {
            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully',
                'key' => $key,
                'value' => AdminSetting::get($key)
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update setting'
        ], 500);
    }

    /**
     * Validate setting value based on type
     */
    private function validateSettingValue(string $key, $value, $setting)
    {
        $rules = [];

        switch ($setting->type) {
            case 'string':
                $rules[$key] = 'required|string|max:500';
                break;
            case 'integer':
                $rules[$key] = 'required|integer';
                break;
            case 'boolean':
                $rules[$key] = 'required|boolean';
                break;
            case 'array':
            case 'json':
                $rules[$key] = 'required|array';
                break;
        }

        // Additional validation based on input type
        switch ($setting->input_type) {
            case 'email':
                $rules[$key] = 'required|email|max:255';
                break;
            case 'url':
                $rules[$key] = 'required|url|max:500';
                break;
            case 'number':
                $rules[$key] = 'required|numeric|min:0';
                break;
            case 'select':
            case 'radio':
                if ($setting->options && is_array($setting->options)) {
                    $rules[$key] = 'required|in:' . implode(',', array_keys($setting->options));
                }
                break;
        }

        if (empty($rules)) {
            return null;
        }

        return Validator::make([$key => $value], $rules);
    }

    public function getRoles()
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => ucwords(str_replace('-', ' ', $role->name)),
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => $role->users()->count(),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ];
        });

        $permissions = Permission::all()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => ucwords(str_replace(['_', '-'], ' ', $permission->name)),
                'guard_name' => $permission->guard_name,
            ];
        });

        return response()->json([
            'success' => true,
            'roles' => $roles,
            'permissions' => $permissions,
            'stats' => [
                'total_roles' => $roles->count(),
                'total_permissions' => $permissions->count(),
                'total_admin_users' => User::role(['admin', 'super-admin'])->count(),
            ]
        ]);
    }

    public function createRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'role' => $role->load('permissions')
        ]);
    }

    public function updateRole(Request $request, Role $role)
    {
        // Prevent editing super-admin role
        if ($role->name === 'super-admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify super-admin role'
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        if ($request->has('name')) {
            $role->update(['name' => $request->name]);
        }

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'role' => $role->load('permissions')
        ]);
    }

    public function deleteRole(Role $role)
    {
        // Prevent deleting system roles
        if (in_array($role->name, ['super-admin', 'admin', 'customer'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system roles'
            ], 403);
        }

        // Check if role has users
        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role with assigned users'
            ], 400);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }

    public function getEmailTemplates()
    {
        $templates = [
            [
                'id' => 1,
                'name' => 'welcome_email',
                'subject' => 'Welcome to BookBharat!',
                'description' => 'Sent when a new user registers',
                'variables' => ['user_name', 'site_name', 'login_url'],
                'enabled' => true,
            ],
            [
                'id' => 2,
                'name' => 'order_confirmation',
                'subject' => 'Order Confirmation - #{{order_number}}',
                'description' => 'Sent when an order is placed',
                'variables' => ['order_number', 'customer_name', 'order_total', 'order_items'],
                'enabled' => true,
            ],
            [
                'id' => 3,
                'name' => 'order_shipped',
                'subject' => 'Your order has been shipped!',
                'description' => 'Sent when an order is shipped',
                'variables' => ['order_number', 'tracking_number', 'estimated_delivery'],
                'enabled' => true,
            ],
            [
                'id' => 4,
                'name' => 'abandoned_cart',
                'subject' => 'You left something in your cart!',
                'description' => 'Sent to remind about abandoned cart',
                'variables' => ['customer_name', 'cart_items', 'cart_total', 'return_url'],
                'enabled' => false,
            ],
        ];

        return response()->json([
            'success' => true,
            'templates' => $templates
        ]);
    }

    public function getPayment()
    {
        // Get PaymentSettings (Gateway configurations)
        $paymentSettings = PaymentSetting::orderBy('priority', 'asc')->get()->map(function ($setting) {
            return [
                'id' => $setting->id,
                'keyword' => $setting->unique_keyword,
                'name' => $setting->name,
                'description' => $setting->description,
                'is_active' => $setting->is_active,
                'is_production' => $setting->is_production,
                'supported_currencies' => $setting->supported_currencies,
                'configuration' => $setting->configuration,
                'webhook_config' => $setting->webhook_config,
                'priority' => $setting->priority,
                'created_at' => $setting->created_at,
                'updated_at' => $setting->updated_at,
            ];
        });

        // Get PaymentConfigurations (Method configurations for orders)
        $paymentMethods = PaymentConfiguration::orderBy('priority', 'desc')->get()->map(function ($config) {
            return [
                'id' => $config->id,
                'payment_method' => $config->payment_method,
                'display_name' => $config->display_name,
                'description' => $config->description,
                'is_enabled' => $config->is_enabled,
                'priority' => $config->priority,
                'configuration' => $config->configuration,
                'restrictions' => $config->restrictions,
                'created_at' => $config->created_at,
                'updated_at' => $config->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'payment_settings' => $paymentSettings,
                'payment_methods' => $paymentMethods,
                'stats' => [
                    'active_gateways' => PaymentSetting::where('is_active', true)->count(),
                    'enabled_methods' => PaymentConfiguration::where('is_enabled', true)->count(),
                    'production_gateways' => PaymentSetting::where('is_production', true)->count(),
                ]
            ]
        ]);
    }

    public function updatePaymentSetting(Request $request, PaymentSetting $paymentSetting)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'configuration' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
            'is_production' => 'sometimes|boolean',
            'supported_currencies' => 'sometimes|array',
            'webhook_config' => 'sometimes|array',
            'priority' => 'sometimes|integer',
        ]);

        $paymentSetting->update($request->only([
            'name', 'description', 'configuration', 'is_active',
            'is_production', 'supported_currencies', 'webhook_config', 'priority'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Payment setting updated successfully',
            'data' => $paymentSetting
        ]);
    }

    public function updatePaymentConfiguration(Request $request, PaymentConfiguration $paymentConfiguration)
    {
        $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'is_enabled' => 'sometimes|boolean',
            'priority' => 'sometimes|integer',
            'configuration' => 'sometimes|array',
            'restrictions' => 'sometimes|array',
        ]);

        $paymentConfiguration->update($request->only([
            'display_name', 'description', 'is_enabled',
            'priority', 'configuration', 'restrictions'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Payment method updated successfully',
            'data' => $paymentConfiguration
        ]);
    }

    public function togglePaymentSetting(Request $request, PaymentSetting $paymentSetting)
    {
        $paymentSetting->update(['is_active' => !$paymentSetting->is_active]);

        return response()->json([
            'success' => true,
            'message' => $paymentSetting->is_active ? 'Payment gateway enabled' : 'Payment gateway disabled',
            'data' => $paymentSetting
        ]);
    }

    public function togglePaymentConfiguration(Request $request, PaymentConfiguration $paymentConfiguration)
    {
        $paymentConfiguration->update(['is_enabled' => !$paymentConfiguration->is_enabled]);

        return response()->json([
            'success' => true,
            'message' => $paymentConfiguration->is_enabled ? 'Payment method enabled' : 'Payment method disabled',
            'data' => $paymentConfiguration
        ]);
    }

    public function getShipping()
    {
        $settings = [
            'providers' => [
                [
                    'id' => 1,
                    'name' => 'Standard Shipping',
                    'enabled' => true,
                    'base_rate' => 40,
                    'per_kg_rate' => 10,
                    'estimated_days' => '5-7',
                ],
                [
                    'id' => 2,
                    'name' => 'Express Shipping',
                    'enabled' => true,
                    'base_rate' => 80,
                    'per_kg_rate' => 20,
                    'estimated_days' => '2-3',
                ],
                [
                    'id' => 3,
                    'name' => 'Same Day Delivery',
                    'enabled' => false,
                    'base_rate' => 150,
                    'per_kg_rate' => 30,
                    'estimated_days' => '0-1',
                    'available_cities' => ['Mumbai', 'Delhi', 'Bangalore'],
                ],
            ],
            'free_shipping' => [
                'enabled' => true,
                'min_order_amount' => 500,
                'excluded_zones' => [],
            ],
            'shipping_zones' => [
                ['zone' => 'Zone A', 'states' => ['Maharashtra', 'Gujarat'], 'multiplier' => 1.0],
                ['zone' => 'Zone B', 'states' => ['Karnataka', 'Tamil Nadu'], 'multiplier' => 1.2],
                ['zone' => 'Zone C', 'states' => ['Delhi', 'Haryana'], 'multiplier' => 1.1],
                ['zone' => 'Zone D', 'states' => ['West Bengal', 'Odisha'], 'multiplier' => 1.3],
            ],
        ];

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }

    // ==================== System Management ====================

    public function systemHealth()
    {
        try {
            $health = [
                'database' => $this->checkDatabaseConnection(),
                'cache' => $this->checkCacheConnection(),
                'storage' => $this->checkStorageAccess(),
                'queue' => $this->checkQueueStatus(),
                'memory_usage' => memory_get_usage(true),
                'memory_limit' => ini_get('memory_limit'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_time' => now()->toDateTimeString(),
                'timezone' => config('app.timezone'),
            ];

            return response()->json([
                'success' => true,
                'health' => $health,
                'overall_status' => $this->determineOverallStatus($health)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check system health',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function checkDatabaseConnection()
    {
        try {
            \DB::connection()->getPdo();
            return [
                'status' => 'connected',
                'database' => \DB::connection()->getDatabaseName(),
                'driver' => config('database.default')
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkCacheConnection()
    {
        try {
            Cache::put('health_check', true, 10);
            $result = Cache::get('health_check');
            return [
                'status' => $result ? 'active' : 'error',
                'driver' => config('cache.default')
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkStorageAccess()
    {
        try {
            $disk = \Storage::disk('public');
            return [
                'status' => $disk->exists('') ? 'accessible' : 'error',
                'driver' => config('filesystems.default')
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkQueueStatus()
    {
        try {
            return [
                'status' => 'active',
                'driver' => config('queue.default'),
                'jobs_pending' => \DB::table('jobs')->count(),
                'jobs_failed' => \DB::table('failed_jobs')->count(),
            ];
        } catch (\Exception $e) {
            return ['status' => 'unknown', 'message' => $e->getMessage()];
        }
    }

    private function determineOverallStatus($health)
    {
        if ($health['database']['status'] !== 'connected') return 'critical';
        if ($health['cache']['status'] !== 'active') return 'warning';
        if ($health['storage']['status'] !== 'accessible') return 'warning';
        return 'healthy';
    }

    public function clearCache()
    {
        try {
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            \Artisan::call('route:clear');
            \Artisan::call('view:clear');

            return response()->json([
                'success' => true,
                'message' => 'All caches cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function optimize()
    {
        try {
            \Artisan::call('optimize');

            return response()->json([
                'success' => true,
                'message' => 'Application optimized successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to optimize application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getBackups()
    {
        // Placeholder - would need actual backup implementation
        $backups = [
            [
                'id' => 1,
                'filename' => 'backup_2025_09_30_database.sql',
                'type' => 'database',
                'size' => '45.2 MB',
                'created_at' => now()->subDays(1)->toDateTimeString(),
            ],
            [
                'id' => 2,
                'filename' => 'backup_2025_09_29_full.zip',
                'type' => 'full',
                'size' => '120.5 MB',
                'created_at' => now()->subDays(2)->toDateTimeString(),
            ],
        ];

        return response()->json([
            'success' => true,
            'backups' => $backups,
            'message' => 'Backup functionality coming soon'
        ]);
    }

    public function createBackup(Request $request)
    {
        $request->validate([
            'type' => 'required|in:database,files,full'
        ]);

        // Placeholder - would need actual backup implementation
        return response()->json([
            'success' => true,
            'message' => 'Backup creation functionality coming soon',
            'type' => $request->type
        ]);
    }

    public function restoreBackup(Request $request)
    {
        $request->validate([
            'backup_id' => 'required|integer'
        ]);

        // Placeholder - would need actual restore implementation
        return response()->json([
            'success' => true,
            'message' => 'Backup restore functionality coming soon',
            'backup_id' => $request->backup_id
        ]);
    }

    public function getSystemLogs(Request $request)
    {
        try {
            $logPath = storage_path('logs/laravel.log');

            if (!file_exists($logPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Log file not found'
                ], 404);
            }

            $lines = $request->input('lines', 100);
            $content = $this->tailLog($logPath, $lines);

            return response()->json([
                'success' => true,
                'logs' => $content,
                'file' => 'laravel.log',
                'lines' => $lines
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to read logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function tailLog($file, $lines = 100)
    {
        $handle = fopen($file, "r");
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = [];

        while ($linecounter > 0) {
            $t = " ";
            while ($t != "\n") {
                if (fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }
            $linecounter--;
            if ($beginning) {
                rewind($handle);
            }
            $text[$lines - $linecounter - 1] = fgets($handle);
            if ($beginning) break;
        }
        fclose($handle);
        return array_reverse($text);
    }

    public function getQueueStatus()
    {
        try {
            return response()->json([
                'success' => true,
                'queue' => [
                    'driver' => config('queue.default'),
                    'jobs_pending' => \DB::table('jobs')->count(),
                    'jobs_failed' => \DB::table('failed_jobs')->count(),
                    'recent_failures' => \DB::table('failed_jobs')
                        ->orderBy('failed_at', 'desc')
                        ->limit(5)
                        ->get()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get queue status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== Email & SMS Settings ====================

    public function getEmail()
    {
        return response()->json([
            'success' => true,
            'settings' => [
                'mail_driver' => AdminSetting::get('mail_driver', config('mail.default')),
                'mail_host' => AdminSetting::get('mail_host', config('mail.mailers.smtp.host')),
                'mail_port' => AdminSetting::get('mail_port', config('mail.mailers.smtp.port')),
                'mail_username' => AdminSetting::get('mail_username', config('mail.mailers.smtp.username')),
                'mail_encryption' => AdminSetting::get('mail_encryption', config('mail.mailers.smtp.encryption')),
                'mail_from_address' => AdminSetting::get('mail_from_address', config('mail.from.address')),
                'mail_from_name' => AdminSetting::get('mail_from_name', config('mail.from.name')),
            ]
        ]);
    }

    public function updateEmail(Request $request)
    {
        $request->validate([
            'mail_host' => 'required|string',
            'mail_port' => 'required|integer',
            'mail_username' => 'required|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|in:tls,ssl',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string',
        ]);

        try {
            AdminSetting::set('mail_driver', 'smtp');
            AdminSetting::set('mail_host', $request->mail_host);
            AdminSetting::set('mail_port', $request->mail_port);
            AdminSetting::set('mail_username', $request->mail_username);
            if ($request->filled('mail_password')) {
                AdminSetting::set('mail_password', encrypt($request->mail_password));
            }
            AdminSetting::set('mail_encryption', $request->mail_encryption ?? 'tls');
            AdminSetting::set('mail_from_address', $request->mail_from_address);
            AdminSetting::set('mail_from_name', $request->mail_from_name);

            return response()->json([
                'success' => true,
                'message' => 'Email settings updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSms()
    {
        return response()->json([
            'success' => true,
            'settings' => [
                'sms_provider' => AdminSetting::get('sms_provider', 'twilio'),
                'sms_api_key' => AdminSetting::get('sms_api_key', ''),
                'sms_api_secret' => AdminSetting::get('sms_api_secret', ''),
                'sms_from_number' => AdminSetting::get('sms_from_number', ''),
                'sms_enabled' => AdminSetting::get('sms_enabled', false),
            ]
        ]);
    }

    public function updateSms(Request $request)
    {
        $request->validate([
            'sms_provider' => 'required|in:twilio,nexmo,sns',
            'sms_api_key' => 'required|string',
            'sms_api_secret' => 'required|string',
            'sms_from_number' => 'required|string',
            'sms_enabled' => 'boolean',
        ]);

        try {
            AdminSetting::set('sms_provider', $request->sms_provider);
            AdminSetting::set('sms_api_key', $request->sms_api_key);
            AdminSetting::set('sms_api_secret', encrypt($request->sms_api_secret));
            AdminSetting::set('sms_from_number', $request->sms_from_number);
            AdminSetting::set('sms_enabled', $request->input('sms_enabled', false));

            return response()->json([
                'success' => true,
                'message' => 'SMS settings updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update SMS settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== Tax Management ====================

    public function getTaxes()
    {
        return response()->json([
            'success' => true,
            'settings' => [
                'tax_enabled' => AdminSetting::get('tax_enabled', true),
                'tax_rate' => AdminSetting::get('tax_rate', 18), // GST 18%
                'tax_name' => AdminSetting::get('tax_name', 'GST'),
                'tax_calculation_method' => AdminSetting::get('tax_calculation_method', 'inclusive'),
                'tax_classes' => [
                    ['id' => 1, 'name' => 'Standard', 'rate' => 18],
                    ['id' => 2, 'name' => 'Reduced', 'rate' => 5],
                    ['id' => 3, 'name' => 'Zero Rated', 'rate' => 0],
                ]
            ]
        ]);
    }

    public function updateTaxes(Request $request)
    {
        $request->validate([
            'tax_enabled' => 'boolean',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'tax_name' => 'required|string|max:50',
            'tax_calculation_method' => 'required|in:inclusive,exclusive',
        ]);

        try {
            AdminSetting::set('tax_enabled', $request->input('tax_enabled', true));
            AdminSetting::set('tax_rate', $request->tax_rate);
            AdminSetting::set('tax_name', $request->tax_name);
            AdminSetting::set('tax_calculation_method', $request->tax_calculation_method);

            return response()->json([
                'success' => true,
                'message' => 'Tax settings updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tax settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== Currency Management ====================

    public function getCurrencies()
    {
        return response()->json([
            'success' => true,
            'settings' => [
                'default_currency' => AdminSetting::get('default_currency', 'INR'),
                'supported_currencies' => AdminSetting::get('supported_currencies', ['INR', 'USD', 'EUR']),
                'currency_symbol' => AdminSetting::get('currency_symbol', '₹'),
                'currency_position' => AdminSetting::get('currency_position', 'left'),
                'available_currencies' => [
                    ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹'],
                    ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
                    ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
                    ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
                ]
            ]
        ]);
    }

    public function updateCurrencies(Request $request)
    {
        $request->validate([
            'default_currency' => 'required|string|size:3',
            'supported_currencies' => 'required|array',
            'currency_symbol' => 'required|string|max:5',
            'currency_position' => 'required|in:left,right',
        ]);

        try {
            AdminSetting::set('default_currency', $request->default_currency);
            AdminSetting::set('supported_currencies', $request->supported_currencies);
            AdminSetting::set('currency_symbol', $request->currency_symbol);
            AdminSetting::set('currency_position', $request->currency_position);

            return response()->json([
                'success' => true,
                'message' => 'Currency settings updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update currency settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== Activity Logs ====================

    public function getActivityLogs(Request $request)
    {
        try {
            $query = \Spatie\Activitylog\Models\Activity::with('causer', 'subject')
                ->orderBy('created_at', 'desc');

            if ($request->filled('causer_id')) {
                $query->where('causer_id', $request->causer_id);
            }

            if ($request->filled('subject_type')) {
                $query->where('subject_type', $request->subject_type);
            }

            if ($request->filled('event')) {
                $query->where('event', $request->event);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $logs = $query->paginate($request->input('per_page', 50));

            return response()->json([
                'success' => true,
                'logs' => $logs,
                'stats' => [
                    'total_activities' => \Spatie\Activitylog\Models\Activity::count(),
                    'today_activities' => \Spatie\Activitylog\Models\Activity::whereDate('created_at', today())->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activity logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}