<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Product permissions
            'view products',
            'create products',
            'edit products',
            'delete products',
            
            // Category permissions
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',
            
            // Order permissions
            'view orders',
            'create orders',
            'edit orders',
            'cancel orders',
            'manage all orders',
            
            // User permissions
            'view users',
            'edit users',
            'delete users',
            'manage user roles',
            
            // Review permissions
            'view reviews',
            'create reviews',
            'edit reviews',
            'delete reviews',
            'moderate reviews',
            
            // Cart permissions
            'manage cart',
            
            // Analytics permissions
            'view analytics',
            'view reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        
        // Super Admin Role
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin Role
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions([
            'view products',
            'create products',
            'edit products',
            'delete products',
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',
            'view orders',
            'manage all orders',
            'view users',
            'edit users',
            'view reviews',
            'moderate reviews',
            'view analytics',
            'view reports',
        ]);

        // Manager Role
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions([
            'view products',
            'create products',
            'edit products',
            'view categories',
            'create categories',
            'edit categories',
            'view orders',
            'manage all orders',
            'view reviews',
            'moderate reviews',
            'view analytics',
        ]);

        // Customer Role
        $customer = Role::firstOrCreate(['name' => 'customer']);
        $customer->syncPermissions([
            'view products',
            'view categories',
            'view orders',
            'create orders',
            'cancel orders',
            'create reviews',
            'edit reviews',
            'delete reviews',
            'manage cart',
        ]);

        // Guest Role (for unauthenticated users)
        $guest = Role::firstOrCreate(['name' => 'guest']);
        $guest->syncPermissions([
            'view products',
            'view categories',
        ]);

        // Create default admin user
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@bookbharat.com'],
            [
                'name' => 'BookBharat Admin',
                'first_name' => 'BookBharat',
                'last_name' => 'Admin',
                'phone' => '+91-9999999999',
                'password' => Hash::make('admin123456'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (!$adminUser->hasRole('super-admin')) {
            $adminUser->assignRole('super-admin');
        }

        // Create default manager user
        $managerUser = User::firstOrCreate(
            ['email' => 'manager@bookbharat.com'],
            [
                'name' => 'BookBharat Manager',
                'first_name' => 'BookBharat',
                'last_name' => 'Manager',
                'phone' => '+91-8888888888',
                'password' => Hash::make('manager123456'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (!$managerUser->hasRole('manager')) {
            $managerUser->assignRole('manager');
        }

        // Create sample customer
        $customerUser = User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'John Customer',
                'first_name' => 'John',
                'last_name' => 'Customer',
                'phone' => '+91-7777777777',
                'password' => Hash::make('customer123'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (!$customerUser->hasRole('customer')) {
            $customerUser->assignRole('customer');
        }

        $this->command->info('Roles and permissions created successfully!');
        $this->command->info('Default users created:');
        $this->command->info('Super Admin: admin@bookbharat.com / admin123456');
        $this->command->info('Manager: manager@bookbharat.com / manager123456');
        $this->command->info('Customer: customer@example.com / customer123');
    }
}