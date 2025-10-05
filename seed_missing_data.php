<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\DeliveryOption;
use App\Models\Review;
use App\Models\Product;
use App\Models\User;

echo "Seeding missing test data...\n\n";

// Seed Delivery Options
echo "Creating delivery options...\n";
if (DeliveryOption::count() == 0) {
    DeliveryOption::create([
        'name' => 'Standard Delivery',
        'code' => 'standard',
        'description' => 'Delivery within 5-7 business days',
        'delivery_days_min' => 5,
        'delivery_days_max' => 7,
        'price_multiplier' => 1.00,
        'fixed_surcharge' => 0,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    DeliveryOption::create([
        'name' => 'Express Delivery',
        'code' => 'express',
        'description' => 'Delivery within 2-3 business days',
        'delivery_days_min' => 2,
        'delivery_days_max' => 3,
        'price_multiplier' => 1.50,
        'fixed_surcharge' => 50,
        'is_active' => true,
        'sort_order' => 2,
    ]);
    echo "  ✓ Created 2 delivery options\n";
} else {
    echo "  ⚠️  Delivery options already exist\n";
}

// Seed Reviews (if reviews table has status column)
echo "\nCreating reviews...\n";
$products = Product::take(3)->get();
$users = User::where('email', '!=', 'admin@bookbharat.com')->take(2)->get();

if ($products->count() > 0 && $users->count() > 0) {
    foreach ($products as $index => $product) {
        $user = $users[$index % $users->count()];

        try {
            Review::create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'rating' => rand(4, 5),
                'title' => 'Great book!',
                'comment' => 'Really enjoyed reading this book. Highly recommended!',
                'status' => 'approved',
                'is_verified_purchase' => true,
            ]);
        } catch (\Exception $e) {
            echo "  ⚠️  Could not create review: " . $e->getMessage() . "\n";
            break;
        }
    }
    echo "  ✓ Created reviews\n";
} else {
    echo "  ⚠️  No products or users found\n";
}

echo "\n✅ Seeding complete!\n";
