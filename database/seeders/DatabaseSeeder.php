<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call essential seeders in proper order
        $this->call([
            // First: Roles and Permissions (foundational)
            RolePermissionSeeder::class,

            // Payment Configuration (essential for checkout)
            PaymentConfigurationSeeder::class,

            // Geographic and Shipping Configuration
            // Note: Order is important - weight slabs must be created before zones
            ShippingWeightSlabSeeder::class,  // Create weight slabs first
            ShippingZoneSeeder::class,         // Then create zone rates for each weight slab
            PincodeZoneSeeder::class,          // Map pincodes to zones
            BundleDiscountRuleSeeder::class,   // Add shipping discount rules

            // Marketing and Discounts
            CouponsTableSeeder::class,

            // Test Data
             SystemTestSeeder::class,
        ]);

        // Create a test user if it doesn't exist
        if (!User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        $this->command->info('All seeders have been executed successfully!');
    }
}
