<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Coupon;
use Illuminate\Support\Facades\Hash;

/**
 * Development Environment Seeder
 *
 * Comprehensive seeding for development environment including:
 * - Complete test data for all features
 * - Sample products, categories, users, orders
 * - Realistic test scenarios
 * - Performance-optimized data generation
 */
class DevelopmentSeeder extends BaseSeeder
{
    /**
     * Maximum number of products to create (for performance)
     */
    private int $maxProducts = 15;

    /**
     * Maximum number of orders to create (for performance)
     */
    private int $maxOrders = 20;

    /**
     * Run the development seeding process
     */
    public function run(): void
    {
        $this->command->info('🔧 Starting DEVELOPMENT seeding...');
        $this->command->info("   Target: {$this->maxProducts} products, {$this->maxOrders} orders");
        $this->command->newLine();

        try {
            // Execute seeding phases
            $this->runSeedingPhases();

            // Create additional development-specific data
            $this->createDevelopmentData();

            $this->command->newLine();
            $this->command->info('✅ DEVELOPMENT seeding completed successfully!');
            $this->showDevelopmentSummary();

        } catch (\Exception $e) {
            $this->command->error('❌ Development seeding failed: ' . $e->getMessage());
            $this->command->error('File: ' . $e->getFile() . ':' . $e->getLine());
            throw $e;
        }
    }

    /**
     * Run all seeding phases in organized order
     */
    private function runSeedingPhases(): void
    {
        $phases = [
            'Core System Setup' => [
                RolePermissionSeeder::class,
            ],
            'Payment & Financial' => [
                PaymentMethodSeeder::class,
                PaymentAdminSettingsSeeder::class,
                TaxConfigurationSeeder::class,
                OrderChargeSeeder::class,
            ],
            'Shipping & Logistics' => [
                DefaultWarehouseSeeder::class,
                ShippingWeightSlabSeeder::class,
                ShippingZoneSeeder::class,
                ShippingCarrierSeeder::class,
                ShippingInsuranceSeeder::class,
                PinCodeSeeder::class,
                PincodeZoneSeeder::class,
            ],
            'Marketing & Promotions' => [
                BundleDiscountRuleSeeder::class,
                CouponsTableSeeder::class,
                ProductAssociationsSeeder::class,
                PromotionalCampaignSeeder::class,
            ],
            'Content & Admin' => [
                AdminSettingsSeeder::class,
                HeroConfigurationSeeder::class,
                HomepageSectionSeeder::class,
            ],
            'Test Data & Products' => [
                SystemTestSeeder::class,
                UserGeneratedContentSeeder::class,
            ],
        ];

        foreach ($phases as $phaseName => $seeders) {
            $this->runPhase($phaseName, $seeders);
        }
    }

    /**
     * Run a specific seeding phase
     */
    private function runPhase(string $phaseName, array $seeders): void
    {
        $this->startProgress($phaseName, count($seeders));

        foreach ($seeders as $seeder) {
            $this->safeExecute(
                fn() => $this->call($seeder),
                $phaseName,
                "Failed to run {$seeder}"
            );
            $this->updateProgress($phaseName);
        }

        $this->completeProgress($phaseName);
    }

    /**
     * Create additional development-specific data
     */
    private function createDevelopmentData(): void
    {
        $this->command->newLine();
        $this->command->info('📦 Creating additional development data...');

        // Create test users (beyond what's in SystemTestSeeder)
        $this->createAdditionalTestUsers();

        // Create sample orders for testing
        $this->createSampleOrders();

        // Create development-specific configurations
        $this->createDevelopmentConfig();
    }

    /**
     * Create additional test users for development
     */
    private function createAdditionalTestUsers(): void
    {
        $this->startProgress('Additional Test Users', 6);

        $additionalUsers = [
            [
                'name' => 'VIP Customer',
                'email' => 'vip@bookbharat.com',
                'password' => Hash::make('password'),
                'phone' => '9876543210',
                'first_name' => 'VIP',
                'last_name' => 'Customer',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'customer',
                'group' => 'VIP Customers',
            ],
            [
                'name' => 'Book Club Member',
                'email' => 'club@bookbharat.com',
                'password' => Hash::make('password'),
                'phone' => '9876543211',
                'first_name' => 'Book Club',
                'last_name' => 'Member',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'customer',
                'group' => 'Book Club Members',
            ],
            [
                'name' => 'Store Manager',
                'email' => 'manager@bookbharat.com',
                'password' => Hash::make('password'),
                'phone' => '9876543212',
                'first_name' => 'Store',
                'last_name' => 'Manager',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'manager',
            ],
            [
                'name' => 'Content Editor',
                'email' => 'editor@bookbharat.com',
                'password' => Hash::make('password'),
                'phone' => '9876543213',
                'first_name' => 'Content',
                'last_name' => 'Editor',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'admin',
            ],
            [
                'name' => 'Support Agent',
                'email' => 'support@bookbharat.com',
                'password' => Hash::make('password'),
                'phone' => '9876543214',
                'first_name' => 'Support',
                'last_name' => 'Agent',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'customer', // Support role for testing
            ],
            [
                'name' => 'Test Account',
                'email' => 'test123@bookbharat.com',
                'password' => Hash::make('password'),
                'phone' => '9876543215',
                'first_name' => 'Test',
                'last_name' => 'Account',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'customer',
            ],
        ];

        foreach ($additionalUsers as $userData) {
            $groupName = $userData['group'] ?? null;
            $role = $userData['role'];
            unset($userData['group']);
            unset($userData['role']); // Remove role from database insert

            $user = $this->createOrUpdate(User::class, ['email' => $userData['email']], $userData);

            if ($user) {
                // Assign role using Spatie's permission package
                $user->assignRole($role);

                // Add to customer group if specified
                if ($groupName && $role === 'customer') {
                    $group = \App\Models\CustomerGroup::where('name', $groupName)->first();
                    if ($group) {
                        $user->customerGroups()->syncWithoutDetaching([$group->id]);
                    }
                }

                $this->updateProgress('Additional Test Users');
            }
        }

        $this->completeProgress('Additional Test Users');
    }

    /**
     * Create sample orders for testing various scenarios
     */
    private function createSampleOrders(): void
    {
        $this->startProgress('Sample Orders', $this->maxOrders);

        $customers = User::role('customer')->take(5)->get();
        $products = Product::take(10)->get();

        if ($customers->isEmpty() || $products->isEmpty()) {
            $this->logError('Sample Orders', 'No customers or products available for order creation');
            return;
        }

        $orderStatuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
        $paymentStatuses = ['pending', 'paid', 'failed', 'refunded'];

        for ($i = 0; $i < $this->maxOrders; $i++) {
            $customer = $customers->random();
            $orderProducts = $products->random(rand(1, 4));

            $subtotal = 0;
            $orderItems = [];

            foreach ($orderProducts as $product) {
                $quantity = rand(1, 3);
                $price = $product->sale_price ?? $product->price;
                $subtotal += $price * $quantity;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price' => $price,
                    'quantity' => $quantity,
                    'total_price' => $price * $quantity,
                ];
            }

            $shippingAmount = rand(50, 200);
            $taxAmount = $subtotal * 0.18; // 18% GST
            $totalAmount = $subtotal + $taxAmount + $shippingAmount;

            // Create order
            $order = $this->createOrUpdate(\App\Models\Order::class, [
                'order_number' => 'DEV-' . strtoupper(uniqid()),
                'user_id' => $customer->id,
            ], [
                'status' => collect($orderStatuses)->random(),
                'payment_status' => collect($paymentStatuses)->random(),
                'subtotal' => $subtotal,
                'shipping_amount' => $shippingAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'shipping_address' => json_encode([
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'address_line_1' => '123 Test Street',
                    'city' => 'Mumbai',
                    'state' => 'Maharashtra',
                    'pincode' => '400001',
                    'country' => 'India'
                ]),
                'billing_address' => json_encode([
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'address_line_1' => '123 Test Street',
                    'city' => 'Mumbai',
                    'state' => 'Maharashtra',
                    'pincode' => '400001',
                    'country' => 'India'
                ]),
                'notes' => rand(0, 1) ? 'Development test order - handle with care' : null,
            ]);

            if ($order && !empty($orderItems)) {
                // Create order items
                foreach ($orderItems as $item) {
                    $order->orderItems()->create($item);
                }
            }

            $this->updateProgress('Sample Orders');
        }

        $this->completeProgress('Sample Orders');
    }

    /**
     * Create development-specific configuration
     */
    private function createDevelopmentConfig(): void
    {
        $this->startProgress('Development Configuration', 1);

        // Update site configuration for development mode
        $this->safeExecute(function() {
            \App\Models\SiteConfiguration::updateOrCreate(
                ['key' => 'app_mode'],
                [
                    'value' => 'development',
                    'group' => 'system',
                    'description' => 'Application environment mode'
                ]
            );
        }, 'Development Configuration', 'Failed to set development mode');

        $this->completeProgress('Development Configuration');
    }

    /**
     * Show development environment summary
     */
    private function showDevelopmentSummary(): void
    {
        $this->command->newLine();
        $this->command->info('🎯 Development Environment Summary:');
        $this->command->newLine();

        // Test Accounts
        $this->command->info('👥 Test Accounts:');
        $testAccounts = [
            'admin@bookbharat.com' => 'Super Admin',
            'manager@bookbharat.com' => 'Manager',
            'customer@example.com' => 'Customer',
            'vip@bookbharat.com' => 'VIP Customer',
            'club@bookbharat.com' => 'Book Club Member',
        ];

        foreach ($testAccounts as $email => $role) {
            $this->command->line("   • {$role}: {$email} / password");
        }

        $this->command->newLine();

        // Sample Data
        $this->command->info('📦 Sample Data Created:');
        $this->command->line('   • Products: ' . Product::count() . ' books with images');
        $this->command->line('   • Categories: ' . Category::count() . ' categories');
        $this->command->line('   • Users: ' . User::count() . ' users across all roles');
        $this->command->line('   • Coupons: ' . Coupon::count() . ' active coupons');
        $this->command->line('   • Orders: ' . \App\Models\Order::count() . ' sample orders');
        $this->command->line('   • Payment Methods: Configured and ready');
        $this->command->line('   • Shipping Zones: Configured for testing');

        $this->command->newLine();

        // Development Tips
        $this->command->info('🚀 Development Tips:');
        $this->command->line('   • Start backend: php artisan serve');
        $this->command->line('   • Start frontend: npm run dev');
        $this->command->line('   • Access admin: http://localhost:8000/admin');
        $this->command->line('   • API docs: http://localhost:8000/api/documentation');
        $this->command->line('   • Test payments: Use Razorpay test credentials');
        $this->command->line('   • Debug mode: APP_DEBUG=true in .env');

        $this->command->newLine();

        // Available Coupons
        $this->command->info('🎫 Available Coupons:');
        $coupons = Coupon::where('is_active', true)->take(5)->get();
        foreach ($coupons as $coupon) {
            $this->command->line("   • {$coupon->code}: {$coupon->description}");
        }
    }
}
