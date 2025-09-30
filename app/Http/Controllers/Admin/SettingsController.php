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
}