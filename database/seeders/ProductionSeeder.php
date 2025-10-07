<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    /**
     * Production environment seeding
     * Only essential data required for application to function
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting PRODUCTION seeding...');

        // Essential seeders only - in specific order
        $this->call([
            // 1. Core System Setup
            RolePermissionSeeder::class,           // Roles & permissions (admin, customer, etc.)

            // 2. Payment Configuration (NEW CLEAN SYSTEM)
            PaymentMethodSeeder::class,             // NEW: Clean single-table payment methods
            // OLD SEEDERS REMOVED - Using new PaymentMethodSeeder instead
            // PaymentConfigurationSeeder::class,   // DEPRECATED
            // EnablePaymentGatewaysSeeder::class,  // DEPRECATED
            PaymentAdminSettingsSeeder::class,      // Admin payment settings (still needed)

            // 3. Shipping Configuration
            DefaultWarehouseSeeder::class,          // Default warehouse location
            ShippingWeightSlabSeeder::class,        // Weight-based shipping slabs
            ShippingZoneSeeder::class,              // Shipping zones configuration
            ShippingCarrierSeeder::class,           // Carrier configurations
            ShippingInsuranceSeeder::class,         // Insurance settings

            // 4. Admin Settings
            AdminSettingsSeeder::class,             // Core admin configurations
            HeroConfigurationSeeder::class,          // Homepage hero section config

            // 5. Essential Geographic Data
            PincodeZoneSeeder::class,               // Pincode to zone mapping (limited set)
        ]);

        // Create super admin if not exists
        $this->createSuperAdmin();

        $this->command->info('✅ PRODUCTION seeding completed successfully!');
        $this->command->warn('⚠️  Remember to:');
        $this->command->warn('   - Update payment gateway API keys');
        $this->command->warn('   - Configure shipping carrier accounts');
        $this->command->warn('   - Set proper admin passwords');
        $this->command->warn('   - Import full pincode database if needed');
    }

    private function createSuperAdmin(): void
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@bookbharat.com');

        if (!User::where('email', $adminEmail)->exists()) {
            $admin = User::create([
                'name' => 'Super Admin',
                'email' => $adminEmail,
                'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe@123!')),
                'email_verified_at' => now(),
            ]);

            $admin->assignRole('admin');

            $this->command->info("✅ Super admin created: {$adminEmail}");
            if (!env('ADMIN_PASSWORD')) {
                $this->command->warn("⚠️  Default password used: ChangeMe@123!");
                $this->command->warn("   Please change it immediately!");
            }
        }
    }
}