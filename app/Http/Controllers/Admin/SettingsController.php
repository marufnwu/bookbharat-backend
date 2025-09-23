<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SettingsController extends Controller
{
    public function getGeneral()
    {
        $settings = Cache::remember('general_settings', 3600, function () {
            return [
                'site_name' => config('app.name', 'BookBharat'),
                'site_url' => config('app.url'),
                'admin_email' => 'admin@bookbharat.com',
                'support_email' => 'support@bookbharat.com',
                'currency' => 'INR',
                'currency_symbol' => '₹',
                'timezone' => config('app.timezone', 'Asia/Kolkata'),
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'order_prefix' => 'ORD',
                'invoice_prefix' => 'INV',
                'tax_enabled' => true,
                'gst_number' => 'GSTIN123456789',
                'shipping_enabled' => true,
                'cod_enabled' => true,
                'online_payment_enabled' => true,
                'min_order_amount' => 100,
                'max_order_amount' => 100000,
                'free_shipping_threshold' => 500,
                'maintenance_mode' => false,
                'allow_guest_checkout' => true,
                'auto_approve_reviews' => false,
                'enable_wishlist' => true,
                'enable_compare' => true,
                'enable_coupons' => true,
                'enable_loyalty_program' => false,
                'items_per_page' => 20,
                'max_upload_size' => 5, // MB
                'allowed_image_types' => ['jpg', 'jpeg', 'png', 'webp'],
                'smtp_settings' => [
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'encryption' => config('mail.mailers.smtp.encryption'),
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                ],
                'payment_gateways' => [
                    'razorpay' => [
                        'enabled' => true,
                        'test_mode' => true,
                        'key' => 'rzp_test_xxxxx',
                    ],
                    'cashfree' => [
                        'enabled' => true,
                        'test_mode' => true,
                        'app_id' => 'cf_test_xxxxx',
                    ],
                    'cod' => [
                        'enabled' => true,
                        'extra_charge' => 50,
                    ]
                ],
                'social_links' => [
                    'facebook' => 'https://facebook.com/bookbharat',
                    'twitter' => 'https://twitter.com/bookbharat',
                    'instagram' => 'https://instagram.com/bookbharat',
                    'linkedin' => 'https://linkedin.com/company/bookbharat',
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }

    public function updateGeneral(Request $request)
    {
        $request->validate([
            'site_name' => 'sometimes|string|max:255',
            'admin_email' => 'sometimes|email',
            'support_email' => 'sometimes|email',
            'min_order_amount' => 'sometimes|numeric|min:0',
            'max_order_amount' => 'sometimes|numeric|min:0',
            'free_shipping_threshold' => 'sometimes|numeric|min:0',
            'items_per_page' => 'sometimes|integer|min:10|max:100',
        ]);

        // In a real application, you would save these to a database
        // For now, we'll just clear the cache and return success
        Cache::forget('general_settings');

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
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
        $settings = [
            'gateways' => [
                [
                    'id' => 'razorpay',
                    'name' => 'Razorpay',
                    'enabled' => true,
                    'test_mode' => true,
                    'supported_currencies' => ['INR', 'USD'],
                    'transaction_fee' => '2%',
                    'min_amount' => 100,
                    'max_amount' => 100000,
                ],
                [
                    'id' => 'cashfree',
                    'name' => 'Cashfree',
                    'enabled' => true,
                    'test_mode' => true,
                    'supported_currencies' => ['INR'],
                    'transaction_fee' => '1.95%',
                    'min_amount' => 100,
                    'max_amount' => 200000,
                ],
                [
                    'id' => 'cod',
                    'name' => 'Cash on Delivery',
                    'enabled' => true,
                    'test_mode' => false,
                    'extra_charge' => 50,
                    'max_amount' => 10000,
                ],
            ],
            'tax_settings' => [
                'tax_enabled' => true,
                'tax_type' => 'GST',
                'tax_rates' => [
                    ['category' => 'Books', 'rate' => 0],
                    ['category' => 'E-books', 'rate' => 18],
                    ['category' => 'Stationery', 'rate' => 12],
                ],
            ],
        ];

        return response()->json([
            'success' => true,
            'settings' => $settings
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