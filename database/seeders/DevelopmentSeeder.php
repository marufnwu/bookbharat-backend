<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    /**
     * Development environment seeding
     * Includes test data, sample products, test users, etc.
     */
    public function run(): void
    {
        $this->command->info('🔧 Starting DEVELOPMENT seeding...');

        // All seeders including test data
        $this->call([
            // 1. Core System Setup
            RolePermissionSeeder::class,           // Roles & permissions

            // 2. Payment Configuration
            PaymentConfigurationSeeder::class,      // Payment gateway configurations
            EnablePaymentGatewaysSeeder::class,     // Enable payment gateways
            PaymentSettingSeeder::class,            // Additional payment settings
            PaymentAdminSettingsSeeder::class,      // Admin payment settings

            // 3. Shipping Configuration
            DefaultWarehouseSeeder::class,          // Default warehouse
            ShippingWeightSlabSeeder::class,        // Weight slabs
            ShippingZoneSeeder::class,              // Shipping zones
            ShippingCarrierSeeder::class,           // Carrier configurations
            ShippingInsuranceSeeder::class,         // Insurance settings
            PinCodeSeeder::class,                   // Sample pincodes (limited set)
            PincodeZoneSeeder::class,               // Full pincode zone mapping

            // 4. Marketing & Promotions
            BundleDiscountRuleSeeder::class,        // Bundle discount rules
            CouponsTableSeeder::class,              // Sample coupons
            ProductAssociationsSeeder::class,       // Product recommendations

            // 5. Admin & Content
            AdminSettingsSeeder::class,             // Admin configurations
            HeroConfigurationSeeder::class,          // Hero section config

            // 6. Test Data
            SystemTestSeeder::class,                // Complete test data (users, products, categories)
        ]);

        // Create additional test users
        $this->createTestUsers();

        // Create additional sample data
        $this->createSampleOrders();

        $this->command->info('✅ DEVELOPMENT seeding completed successfully!');
        $this->command->info('📋 Test Accounts Created:');
        $this->command->line('   Admin: admin@example.com / password');
        $this->command->line('   Customer: customer@example.com / password');
        $this->command->line('   Test: test@example.com / password');
        $this->command->line('   Demo: demo@example.com / password');
        $this->command->info('📦 Sample Data:');
        $this->command->line('   - 10+ sample products with images');
        $this->command->line('   - 7+ categories with hierarchy');
        $this->command->line('   - Active coupons for testing');
        $this->command->line('   - Product associations for recommendations');
        $this->command->line('   - Sample shipping configurations');
    }

    private function createTestUsers(): void
    {
        $testUsers = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'role' => 'admin'
            ],
            [
                'name' => 'Customer User',
                'email' => 'customer@example.com',
                'password' => Hash::make('password'),
                'role' => 'customer'
            ],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'role' => 'customer'
            ],
            [
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => Hash::make('password'),
                'role' => 'customer'
            ],
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => Hash::make('password'),
                'role' => 'customer'
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'password' => Hash::make('password'),
                'role' => 'customer'
            ]
        ];

        foreach ($testUsers as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            if (!User::where('email', $userData['email'])->exists()) {
                $user = User::create(array_merge($userData, [
                    'email_verified_at' => now(),
                ]));

                $user->assignRole($role);
                $this->command->info("Created test user: {$userData['email']}");
            }
        }
    }

    private function createSampleOrders(): void
    {
        // Get some test users and products
        $customers = User::role('customer')->take(3)->get();
        $products = Product::take(5)->get();

        if ($customers->isEmpty() || $products->isEmpty()) {
            $this->command->warn('Skipping sample orders - no customers or products available');
            return;
        }

        foreach ($customers as $customer) {
            // Create 1-2 orders per customer
            $orderCount = rand(1, 2);

            for ($i = 0; $i < $orderCount; $i++) {
                $order = $customer->orders()->create([
                    'order_number' => 'ORD-' . strtoupper(uniqid()),
                    'status' => collect(['pending', 'processing', 'completed', 'shipped'])->random(),
                    'payment_status' => collect(['pending', 'paid', 'failed'])->random(),
                    'subtotal' => 0,
                    'shipping_amount' => rand(50, 200),
                    'tax_amount' => 0,
                    'total_amount' => 0,
                    'shipping_address' => json_encode([
                        'name' => $customer->name,
                        'phone' => '9876543210',
                        'address_line_1' => '123 Test Street',
                        'city' => 'Mumbai',
                        'state' => 'Maharashtra',
                        'pincode' => '400001',
                        'country' => 'India'
                    ]),
                    'billing_address' => json_encode([
                        'name' => $customer->name,
                        'phone' => '9876543210',
                        'address_line_1' => '123 Test Street',
                        'city' => 'Mumbai',
                        'state' => 'Maharashtra',
                        'pincode' => '400001',
                        'country' => 'India'
                    ]),
                ]);

                // Add random products to order
                $orderProducts = $products->random(rand(1, 3));
                $subtotal = 0;

                foreach ($orderProducts as $product) {
                    $quantity = rand(1, 3);
                    $price = $product->sale_price ?? $product->price;
                    $itemTotal = $price * $quantity;
                    $subtotal += $itemTotal;

                    $order->orderItems()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit_price' => $price,
                        'quantity' => $quantity,
                        'total_price' => $itemTotal,
                    ]);
                }

                // Update order totals
                $taxAmount = $subtotal * 0.18; // 18% GST
                $order->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $subtotal + $taxAmount + $order->shipping_amount,
                ]);
            }
        }

        $this->command->info('Created sample orders for testing');
    }
}