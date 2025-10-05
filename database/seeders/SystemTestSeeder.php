<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Coupon;
use App\Models\CustomerGroup;
// use App\Models\LoyaltyProgram;
// use App\Models\PromotionalCampaign;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SystemTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->createCustomerGroups();
        $this->createCategories();
        $this->createProducts();
        $this->createTestUsers();
        $this->createCoupons();
        // $this->createLoyaltyProgram();
        // $this->createPromotionalCampaigns();
    }

    private function createCustomerGroups()
    {
        $groups = [
            ['name' => 'Regular Customers', 'description' => 'Standard customer group'],
            ['name' => 'VIP Customers', 'description' => 'Premium customer group with special benefits'],
            ['name' => 'Book Club Members', 'description' => 'Members of our book club program'],
        ];

        foreach ($groups as $group) {
            CustomerGroup::firstOrCreate(
                ['name' => $group['name']],
                $group
            );
        }
    }

    private function createCategories()
    {
        $categories = [
            [
                'name' => 'Fiction',
                'slug' => 'fiction',
                'description' => 'Fictional books including novels, short stories, and more',
                'is_active' => true,
            ],
            [
                'name' => 'Non-Fiction',
                'slug' => 'non-fiction',
                'description' => 'Educational and informational books',
                'is_active' => true,
            ],
            [
                'name' => 'Mystery & Thriller',
                'slug' => 'mystery-thriller',
                'description' => 'Suspenseful and mystery books',
                'is_active' => true,
            ],
            [
                'name' => 'Romance',
                'slug' => 'romance',
                'description' => 'Romantic novels and stories',
                'is_active' => true,
            ],
            [
                'name' => 'Science Fiction',
                'slug' => 'science-fiction',
                'description' => 'Sci-fi and futuristic stories',
                'is_active' => true,
            ],
            [
                'name' => 'Biography',
                'slug' => 'biography',
                'description' => 'Life stories of famous personalities',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }

        // Create subcategories
        $fictionCategory = Category::where('slug', 'fiction')->first();
        if ($fictionCategory) {
            Category::firstOrCreate(
                ['slug' => 'historical-fiction'],
                [
                    'name' => 'Historical Fiction',
                    'slug' => 'historical-fiction',
                    'parent_id' => $fictionCategory->id,
                    'description' => 'Fiction set in historical periods',
                    'is_active' => true,
                ]
            );
        }
    }

    private function createProducts()
    {
        $categories = Category::all();

        // Use actual working placeholder images from picsum or placeholder services
        // These are real URLs that will work for demo/test purposes
        $bookImages = [
            'fiction' => [
                'https://picsum.photos/400/600?random=1',
                'https://picsum.photos/400/600?random=2',
                'https://picsum.photos/400/600?random=3'
            ],
            'non-fiction' => [
                'https://picsum.photos/400/600?random=4',
                'https://picsum.photos/400/600?random=5',
                'https://picsum.photos/400/600?random=6'
            ],
            'mystery' => [
                'https://picsum.photos/400/600?random=7',
                'https://picsum.photos/400/600?random=8',
                'https://picsum.photos/400/600?random=9'
            ],
            'romance' => [
                'https://picsum.photos/400/600?random=10',
                'https://picsum.photos/400/600?random=11',
                'https://picsum.photos/400/600?random=12'
            ],
            'scifi' => [
                'https://picsum.photos/400/600?random=13',
                'https://picsum.photos/400/600?random=14',
                'https://picsum.photos/400/600?random=15'
            ],
            'biography' => [
                'https://picsum.photos/400/600?random=16',
                'https://picsum.photos/400/600?random=17',
                'https://picsum.photos/400/600?random=18'
            ]
        ];

        $products = [
            [
                'name' => 'The Great Indian Novel',
                'slug' => 'the-great-indian-novel',
                'sku' => 'GIN001',
                'category_id' => $categories->where('slug', 'fiction')->first()->id,
                'description' => 'A masterpiece of Indian literature that weaves together mythology and history. Written by Shashi Tharoor, published by Penguin Books India.',
                'short_description' => 'An epic tale of modern India through the lens of the Mahabharata.',
                'price' => 450.00,
                'compare_price' => 500.00,
                'cost_price' => 300.00,
                'stock_quantity' => 50,
                'status' => 'active',
                'is_featured' => true,
                'metadata' => json_encode([
                    'author' => 'Shashi Tharoor',
                    'publisher' => 'Penguin Books India',
                    'isbn' => '9780140115666',
                    'publication_date' => '1989-01-01',
                    'language' => 'English',
                    'pages' => 423
                ])
            ],
            [
                'name' => 'Midnight\'s Children',
                'slug' => 'midnights-children',
                'sku' => 'MC001',
                'category_id' => $categories->where('slug', 'fiction')->first()->id,
                'description' => 'Salman Rushdie\'s Booker Prize-winning novel about India\'s independence and the story of Saleem Sinai.',
                'short_description' => 'The story of Saleem Sinai and the partition of India.',
                'price' => 599.00,
                'compare_price' => 699.00,
                'cost_price' => 400.00,
                'stock_quantity' => 30,
                'status' => 'active',
                'is_featured' => true,
                'metadata' => json_encode([
                    'author' => 'Salman Rushdie',
                    'publisher' => 'Jonathan Cape',
                    'isbn' => '9780224016651',
                    'publication_date' => '1981-04-01',
                    'language' => 'English',
                    'pages' => 647
                ])
            ],
            [
                'name' => 'The Mahabharata: A Modern Rendering',
                'slug' => 'the-mahabharata-modern-rendering',
                'sku' => 'MAH001',
                'category_id' => $categories->where('slug', 'fiction')->first()->id,
                'description' => 'Ramesh Menon\'s accessible retelling of the great Indian epic.',
                'short_description' => 'The timeless epic made accessible for modern readers.',
                'price' => 799.00,
                'compare_price' => 999.00,
                'cost_price' => 500.00,
                'stock_quantity' => 25,
                'status' => 'active',
                'is_featured' => false,
                'metadata' => json_encode([
                    'author' => 'Ramesh Menon',
                    'publisher' => 'Rupa Publications',
                    'isbn' => '9788129108647',
                    'publication_date' => '2006-05-01',
                    'language' => 'English',
                    'pages' => 890
                ])
            ],
            [
                'name' => 'The God of Small Things',
                'slug' => 'the-god-of-small-things',
                'sku' => 'GST001',
                'category_id' => $categories->where('slug', 'fiction')->first()->id,
                'description' => 'Arundhati Roy\'s Booker Prize-winning debut novel.',
                'short_description' => 'A haunting story of love, loss, and family secrets.',
                'price' => 399.00,
                'compare_price' => 450.00,
                'cost_price' => 250.00,
                'stock_quantity' => 40,
                'status' => 'active',
                'is_featured' => true,
                'metadata' => json_encode([
                    'author' => 'Arundhati Roy',
                    'publisher' => 'IndiaInk',
                    'isbn' => '9780060977498',
                    'publication_date' => '1997-04-01',
                    'language' => 'English',
                    'pages' => 340
                ])
            ],
            [
                'name' => 'Sapiens: A Brief History of Humankind',
                'slug' => 'sapiens-brief-history-humankind',
                'sku' => 'SAP001',
                'category_id' => $categories->where('slug', 'non-fiction')->first()->id,
                'description' => 'Yuval Noah Harari explores the history and impact of Homo sapiens.',
                'short_description' => 'How humans conquered the world and what it means for our future.',
                'price' => 650.00,
                'compare_price' => 750.00,
                'cost_price' => 450.00,
                'stock_quantity' => 60,
                'status' => 'active',
                'is_featured' => true,
                'metadata' => json_encode([
                    'author' => 'Yuval Noah Harari',
                    'publisher' => 'Harper',
                    'isbn' => '9780062316097',
                    'publication_date' => '2014-02-01',
                    'language' => 'English',
                    'pages' => 443
                ])
            ],
            [
                'name' => 'The Alchemist',
                'slug' => 'the-alchemist',
                'sku' => 'ALC001',
                'category_id' => $categories->where('slug', 'fiction')->first()->id,
                'description' => 'Paulo Coelho\'s philosophical novel about following your dreams.',
                'short_description' => 'A shepherd\'s journey to find his personal legend.',
                'price' => 299.00,
                'compare_price' => 350.00,
                'cost_price' => 200.00,
                'stock_quantity' => 80,
                'status' => 'active',
                'is_featured' => false,
                'metadata' => json_encode([
                    'author' => 'Paulo Coelho',
                    'publisher' => 'HarperOne',
                    'isbn' => '9780062315007',
                    'publication_date' => '1988-01-01',
                    'language' => 'English',
                    'pages' => 163
                ])
            ],
            [
                'name' => 'The Da Vinci Code',
                'slug' => 'the-da-vinci-code',
                'sku' => 'DVC001',
                'category_id' => $categories->where('slug', 'mystery-thriller')->first()->id,
                'description' => 'Dan Brown\'s thriller about religious mysteries and secret societies.',
                'short_description' => 'A symbologist uncovers a deadly secret in Paris.',
                'price' => 499.00,
                'compare_price' => 599.00,
                'cost_price' => 350.00,
                'stock_quantity' => 35,
                'status' => 'active',
                'is_featured' => false,
                'metadata' => json_encode([
                    'author' => 'Dan Brown',
                    'publisher' => 'Doubleday',
                    'isbn' => '9780385504201',
                    'publication_date' => '2003-03-18',
                    'language' => 'English',
                    'pages' => 454
                ])
            ],
            [
                'name' => 'Pride and Prejudice',
                'slug' => 'pride-and-prejudice',
                'sku' => 'PAP001',
                'category_id' => $categories->where('slug', 'romance')->first()->id,
                'description' => 'Jane Austen\'s timeless romance novel.',
                'short_description' => 'The story of Elizabeth Bennet and Mr. Darcy.',
                'price' => 349.00,
                'compare_price' => 400.00,
                'cost_price' => 220.00,
                'stock_quantity' => 45,
                'status' => 'active',
                'is_featured' => true,
                'metadata' => json_encode([
                    'author' => 'Jane Austen',
                    'publisher' => 'T. Egerton',
                    'isbn' => '9780141439518',
                    'publication_date' => '1813-01-28',
                    'language' => 'English',
                    'pages' => 432
                ])
            ],
            [
                'name' => 'Dune',
                'slug' => 'dune',
                'sku' => 'DUN001',
                'category_id' => $categories->where('slug', 'science-fiction')->first()->id,
                'description' => 'Frank Herbert\'s epic science fiction masterpiece.',
                'short_description' => 'The desert planet Arrakis and the spice melange.',
                'price' => 549.00,
                'compare_price' => 650.00,
                'cost_price' => 380.00,
                'stock_quantity' => 28,
                'status' => 'active',
                'is_featured' => true,
                'metadata' => json_encode([
                    'author' => 'Frank Herbert',
                    'publisher' => 'Chilton Books',
                    'isbn' => '9780441172719',
                    'publication_date' => '1965-08-01',
                    'language' => 'English',
                    'pages' => 688
                ])
            ],
            [
                'name' => 'Steve Jobs',
                'slug' => 'steve-jobs-biography',
                'sku' => 'SJ001',
                'category_id' => $categories->where('slug', 'biography')->first()->id,
                'description' => 'Walter Isaacson\'s definitive biography of Steve Jobs.',
                'short_description' => 'The life and innovations of Apple\'s co-founder.',
                'price' => 699.00,
                'compare_price' => 799.00,
                'cost_price' => 500.00,
                'stock_quantity' => 20,
                'status' => 'active',
                'is_featured' => false,
                'metadata' => json_encode([
                    'author' => 'Walter Isaacson',
                    'publisher' => 'Simon & Schuster',
                    'isbn' => '9781451648539',
                    'publication_date' => '2011-10-24',
                    'language' => 'English',
                    'pages' => 656
                ])
            ],
        ];

        foreach ($products as $productData) {
            $product = Product::firstOrCreate(
                ['sku' => $productData['sku']],
                $productData
            );

            // Add product images if they don't exist
            if ($product->images()->count() == 0) {
                // Determine which image set to use based on category
                $categorySlug = $product->category->slug ?? 'fiction';
                $imageSet = 'fiction'; // Default

                if (str_contains($categorySlug, 'fiction')) {
                    $imageSet = 'fiction';
                } elseif (str_contains($categorySlug, 'non-fiction')) {
                    $imageSet = 'non-fiction';
                } elseif (str_contains($categorySlug, 'mystery') || str_contains($categorySlug, 'thriller')) {
                    $imageSet = 'mystery';
                } elseif (str_contains($categorySlug, 'romance')) {
                    $imageSet = 'romance';
                } elseif (str_contains($categorySlug, 'science-fiction')) {
                    $imageSet = 'scifi';
                } elseif (str_contains($categorySlug, 'biography')) {
                    $imageSet = 'biography';
                }

                // Get appropriate images for this book type
                $images = $bookImages[$imageSet] ?? $bookImages['fiction'];

                // Add primary image - use the storage path directly
                $product->images()->create([
                    'image_path' => $images[0],
                    'alt_text' => $product->name . ' - Cover',
                    'sort_order' => 1,
                    'is_primary' => true
                ]);

                // Add secondary images (back cover, inside pages)
                if (count($images) > 1) {
                    $product->images()->create([
                        'image_path' => $images[1],
                        'alt_text' => $product->name . ' - Back Cover',
                        'sort_order' => 2,
                        'is_primary' => false
                    ]);
                }

                if (count($images) > 2) {
                    $product->images()->create([
                        'image_path' => $images[2],
                        'alt_text' => $product->name . ' - Sample Page',
                        'sort_order' => 3,
                        'is_primary' => false
                    ]);
                }
            }
        }
    }

    private function createTestUsers()
    {
        $regularGroup = CustomerGroup::where('name', 'Regular Customers')->first();
        $vipGroup = CustomerGroup::where('name', 'VIP Customers')->first();

        $users = [
            [
                'name' => 'John Doe',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@bookbharat.com',
                'password' => Hash::make('password123'),
                'phone' => '9876543210',
                'date_of_birth' => '1985-06-15',
                'gender' => 'male',
                'is_active' => true,
                'email_verified_at' => now(),
                'group' => $regularGroup,
                'role' => 'customer',
            ],
            [
                'name' => 'Jane Smith',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@bookbharat.com',
                'password' => Hash::make('password123'),
                'phone' => '9876543211',
                'date_of_birth' => '1990-03-20',
                'gender' => 'female',
                'is_active' => true,
                'email_verified_at' => now(),
                'group' => $vipGroup,
                'role' => 'customer',
            ],
            [
                'name' => 'Store Admin',
                'first_name' => 'Store',
                'last_name' => 'Admin',
                'email' => 'store-admin@bookbharat.com',
                'password' => Hash::make('admin123'),
                'phone' => '9876543212',
                'is_active' => true,
                'email_verified_at' => now(),
                'group' => null, // Admin doesn't need customer group
                'role' => 'admin', // Assign admin role
            ],
        ];

        foreach ($users as $userData) {
            $group = $userData['group'] ?? null;
            $role = $userData['role'] ?? null;
            unset($userData['group']);
            unset($userData['role']);

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            // Assign role if specified
            if ($role && !$user->hasRole($role)) {
                $user->assignRole($role);
            }

            if ($group && !$user->customerGroups()->where('customer_group_id', $group->id)->exists()) {
                $user->customerGroups()->attach($group->id);
            }

            // Create addresses for users (skip admin users)
            if ($user->email !== 'store-admin@bookbharat.com') {
                $user->addresses()->create([
                    'type' => 'home',
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'phone' => $user->phone,
                    'address_line_1' => '123 Test Street',
                    'address_line_2' => 'Apartment 4B',
                    'city' => 'Mumbai',
                    'state' => 'Maharashtra',
                    'postal_code' => '400001',
                    'country' => 'India',
                    'is_default' => true,
                ]);
            }
        }
    }

    private function createCoupons()
    {
        $coupons = [
            [
                'code' => 'WELCOME10',
                'name' => 'Welcome Discount',
                'description' => '10% off for new customers',
                'type' => 'percentage',
                'value' => 10.00,
                'minimum_order_amount' => 500.00,
                'usage_limit' => 1000,
                'usage_limit_per_customer' => 1,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(6),
                'is_active' => true,
                'first_order_only' => 'yes',
            ],
            [
                'code' => 'SAVE20',
                'name' => '20% Off Books',
                'description' => '20% discount on all books',
                'type' => 'percentage',
                'value' => 20.00,
                'minimum_order_amount' => 1000.00,
                'usage_limit' => 500,
                'usage_limit_per_customer' => 3,
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'is_active' => true,
                'applicable_categories' => [1, 2], // Fiction and Non-Fiction
            ],
            [
                'code' => 'FLAT100',
                'name' => '₹100 Off',
                'description' => 'Flat ₹100 discount',
                'type' => 'fixed_amount',
                'value' => 100.00,
                'minimum_order_amount' => 500.00,
                'usage_limit' => 200,
                'usage_limit_per_customer' => 2,
                'starts_at' => now(),
                'expires_at' => now()->addWeeks(2),
                'is_active' => true,
            ],
            [
                'code' => 'FREESHIP',
                'name' => 'Free Shipping',
                'description' => 'Free shipping on all orders',
                'type' => 'free_shipping',
                'minimum_order_amount' => 299.00,
                'usage_limit' => 1000,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(3),
                'is_active' => true,
            ],
            [
                'code' => 'BUY2GET1',
                'name' => 'Buy 2 Get 1 Free',
                'description' => 'Buy 2 books and get 1 free',
                'type' => 'buy_x_get_y',
                'buy_x_get_y_config' => [
                    'buy_quantity' => 2,
                    'get_quantity' => 1,
                ],
                'usage_limit' => 100,
                'starts_at' => now(),
                'expires_at' => now()->addWeeks(4),
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $couponData) {
            Coupon::firstOrCreate(
                ['code' => $couponData['code']],
                $couponData
            );
        }
    }

    private function createLoyaltyProgram()
    {
        LoyaltyProgram::create([
            'name' => 'BookBharat Rewards',
            'description' => 'Earn points for every purchase and get amazing rewards',
            'is_active' => true,
            'points_per_rupee' => 1,
            'minimum_redemption_points' => 100,
            'point_value' => 0.10, // 10 paisa per point
            'expiry_months' => 12,
            'tiers' => [
                'Bronze' => ['min_points' => 0, 'multiplier' => 1.0, 'benefits' => ['Basic rewards']],
                'Silver' => ['min_points' => 1000, 'multiplier' => 1.2, 'benefits' => ['Priority support', '20% bonus points']],
                'Gold' => ['min_points' => 5000, 'multiplier' => 1.5, 'benefits' => ['Free shipping', 'Early access', '50% bonus points']],
                'Platinum' => ['min_points' => 10000, 'multiplier' => 2.0, 'benefits' => ['VIP support', 'Exclusive offers', '100% bonus points']],
            ],
        ]);
    }

    private function createPromotionalCampaigns()
    {
        $campaigns = [
            [
                'name' => 'Summer Reading Festival',
                'slug' => 'summer-reading-festival',
                'description' => 'Biggest sale of the year with amazing discounts on all books',
                'type' => 'seasonal_offer',
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
                'campaign_rules' => [
                    'coupon_type' => 'percentage',
                    'discount_value' => 25,
                    'min_order_amount' => 750,
                    'auto_generate_coupons' => true,
                    'coupon_count' => 50,
                ],
                'target_audience' => [
                    'customer_groups' => [1, 2],
                    'order_criteria' => [
                        'min_orders' => 1,
                    ],
                ],
                'banner_config' => [
                    'title' => 'Summer Reading Festival',
                    'subtitle' => 'Up to 25% off on all books',
                    'background_color' => '#FF6B6B',
                    'text_color' => '#FFFFFF',
                ],
                'budget_limit' => 100000.00,
                'target_participants' => 500,
                'target_revenue' => 500000.00,
                'priority' => 10,
                'auto_apply' => false,
                'created_by' => 1,
            ],
            [
                'name' => 'New Customer Welcome',
                'slug' => 'new-customer-welcome',
                'description' => 'Special offers for first-time customers',
                'type' => 'loyalty_bonus',
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonths(6),
                'campaign_rules' => [
                    'coupon_type' => 'percentage',
                    'discount_value' => 15,
                    'min_order_amount' => 300,
                    'first_order_only' => 'yes',
                    'usage_limit_per_customer' => 1,
                ],
                'target_audience' => [
                    'order_criteria' => [
                        'max_orders' => 0,
                    ],
                ],
                'budget_limit' => 50000.00,
                'target_participants' => 200,
                'target_revenue' => 100000.00,
                'priority' => 8,
                'auto_apply' => true,
                'created_by' => 1,
            ],
        ];

        foreach ($campaigns as $campaignData) {
            PromotionalCampaign::create($campaignData);
        }
    }
}
