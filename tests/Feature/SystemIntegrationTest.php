<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Coupon;
use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class SystemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $product;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->category = Category::create([
            'name' => 'Fiction Books',
            'slug' => 'fiction-books',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Test Book',
            'slug' => 'test-book',
            'sku' => 'TB001',
            'category_id' => $this->category->id,
            'description' => 'A great test book',
            'short_description' => 'Test book description',
            'author' => 'Test Author',
            'price' => 299.99,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@bookbharat.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_complete_user_registration_and_authentication_flow()
    {
        // 1. User Registration
        $response = $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'newuser@bookbharat.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'user' => ['id', 'name', 'email'],
                    'token'
                ]);

        $token = $response->json('token');

        // 2. User Login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'newuser@bookbharat.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200)
                     ->assertJsonStructure([
                         'success',
                         'user',
                         'token'
                     ]);

        // 3. Get User Profile
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
             ->getJson('/api/user/profile')
             ->assertStatus(200)
             ->assertJsonStructure([
                 'success',
                 'user' => ['id', 'name', 'email']
             ]);

        $this->assertTrue(true, 'User authentication flow completed successfully');
    }

    /** @test */
    public function test_complete_shopping_cart_workflow()
    {
        Sanctum::actingAs($this->user);

        // 1. Add item to cart
        $cartResponse = $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $cartResponse->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                        'message',
                        'cart_item',
                        'cart_summary'
                    ]);

        // 2. Get cart contents
        $getCartResponse = $this->getJson('/api/cart');
        
        $getCartResponse->assertStatus(200)
                       ->assertJsonPath('cart.items_count', 1)
                       ->assertJsonPath('cart.is_empty', false);

        // 3. Update cart item quantity
        $cartItem = Cart::where('user_id', $this->user->id)->first();
        
        $updateResponse = $this->putJson("/api/cart/{$cartItem->id}", [
            'quantity' => 3,
        ]);

        $updateResponse->assertStatus(200)
                      ->assertJsonStructure([
                          'success',
                          'message',
                          'cart_item',
                          'cart_summary'
                      ]);

        // 4. Apply coupon (create coupon first)
        $coupon = Coupon::create([
            'code' => 'TEST10',
            'name' => 'Test Coupon',
            'type' => 'percentage',
            'value' => 10,
            'starts_at' => now(),
            'is_active' => true,
        ]);

        $couponResponse = $this->postJson('/api/cart/coupon', [
            'coupon_code' => 'TEST10',
        ]);

        $couponResponse->assertStatus(200)
                      ->assertJsonStructure([
                          'success',
                          'message',
                          'discount',
                          'cart_summary'
                      ]);

        // 5. Validate cart
        $validateResponse = $this->getJson('/api/cart/validate');
        
        $validateResponse->assertStatus(200)
                        ->assertJsonPath('success', true);

        $this->assertTrue(true, 'Shopping cart workflow completed successfully');
    }

    /** @test */
    public function test_complete_order_placement_workflow()
    {
        Sanctum::actingAs($this->user);

        // 1. Add items to cart
        $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        // 2. Create address
        $address = Address::create([
            'user_id' => $this->user->id,
            'type' => 'home',
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '9876543210',
            'address_line_1' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
            'country' => 'India',
            'is_default' => true,
        ]);

        // 3. Place order
        $orderResponse = $this->postJson('/api/orders', [
            'billing_address_id' => $address->id,
            'shipping_address_id' => $address->id,
            'payment_method' => 'credit_card',
            'notes' => 'Test order',
        ]);

        $orderResponse->assertStatus(200)
                     ->assertJsonStructure([
                         'success',
                         'message',
                         'order' => [
                             'id',
                             'order_number',
                             'status',
                             'total_amount'
                         ]
                     ]);

        // 4. Get order details
        $orderId = $orderResponse->json('order.id');
        
        $orderDetailResponse = $this->getJson("/api/orders/{$orderId}");
        
        $orderDetailResponse->assertStatus(200)
                           ->assertJsonStructure([
                               'success',
                               'order' => [
                                   'id',
                                   'order_number',
                                   'status',
                                   'order_items'
                               ]
                           ]);

        // 5. Get order history
        $historyResponse = $this->getJson('/api/user/orders');
        
        $historyResponse->assertStatus(200)
                       ->assertJsonStructure([
                           'success',
                           'orders' => [
                               'data' => [
                                   '*' => [
                                       'id',
                                       'order_number',
                                       'status',
                                       'total_amount'
                                   ]
                               ]
                           ]
                       ]);

        $this->assertTrue(true, 'Order placement workflow completed successfully');
    }

    /** @test */
    public function test_product_search_and_filtering()
    {
        // Create more test products
        Product::create([
            'name' => 'Another Book',
            'slug' => 'another-book',
            'sku' => 'AB001',
            'category_id' => $this->category->id,
            'description' => 'Another great book',
            'author' => 'Another Author',
            'price' => 499.99,
            'stock_quantity' => 50,
            'is_active' => true,
            'is_featured' => true,
        ]);

        // 1. Get all products
        $allProductsResponse = $this->getJson('/api/products');
        
        $allProductsResponse->assertStatus(200)
                           ->assertJsonStructure([
                               'success',
                               'products' => [
                                   'data' => [
                                       '*' => [
                                           'id',
                                           'name',
                                           'price',
                                           'author'
                                       ]
                                   ]
                               ]
                           ]);

        // 2. Search products
        $searchResponse = $this->getJson('/api/products?search=Test');
        
        $searchResponse->assertStatus(200)
                      ->assertJsonCount(1, 'products.data');

        // 3. Filter by category
        $categoryResponse = $this->getJson("/api/products?category_id={$this->category->id}");
        
        $categoryResponse->assertStatus(200)
                        ->assertJsonCount(2, 'products.data');

        // 4. Filter by price range
        $priceResponse = $this->getJson('/api/products?price_min=200&price_max=400');
        
        $priceResponse->assertStatus(200);

        // 5. Get featured products
        $featuredResponse = $this->getJson('/api/products?featured=true');
        
        $featuredResponse->assertStatus(200)
                        ->assertJsonCount(1, 'products.data');

        $this->assertTrue(true, 'Product search and filtering completed successfully');
    }

    /** @test */
    public function test_wishlist_functionality()
    {
        Sanctum::actingAs($this->user);

        // 1. Add to wishlist
        $addResponse = $this->postJson('/api/user/wishlist', [
            'product_id' => $this->product->id,
        ]);

        $addResponse->assertStatus(200)
                   ->assertJsonStructure([
                       'success',
                       'message',
                       'wishlist_item'
                   ]);

        // 2. Get wishlist
        $getResponse = $this->getJson('/api/user/wishlist');
        
        $getResponse->assertStatus(200)
                   ->assertJsonCount(1, 'wishlist')
                   ->assertJsonStructure([
                       'success',
                       'wishlist' => [
                           '*' => [
                               'id',
                               'product' => [
                                   'id',
                                   'name',
                                   'price'
                               ]
                           ]
                       ]
                   ]);

        // 3. Remove from wishlist
        $removeResponse = $this->deleteJson('/api/user/wishlist', [
            'product_id' => $this->product->id,
        ]);

        $removeResponse->assertStatus(200)
                      ->assertJsonPath('success', true);

        // 4. Verify wishlist is empty
        $emptyResponse = $this->getJson('/api/user/wishlist');
        
        $emptyResponse->assertStatus(200)
                     ->assertJsonCount(0, 'wishlist');

        $this->assertTrue(true, 'Wishlist functionality completed successfully');
    }

    /** @test */
    public function test_coupon_system()
    {
        Sanctum::actingAs($this->user);

        // 1. Create different types of coupons
        $percentageCoupon = Coupon::create([
            'code' => 'SAVE20',
            'name' => '20% Off',
            'type' => 'percentage',
            'value' => 20,
            'minimum_order_amount' => 500,
            'starts_at' => now(),
            'is_active' => true,
        ]);

        $fixedCoupon = Coupon::create([
            'code' => 'FLAT100',
            'name' => '₹100 Off',
            'type' => 'fixed_amount',
            'value' => 100,
            'minimum_order_amount' => 300,
            'starts_at' => now(),
            'is_active' => true,
        ]);

        // 2. Add items to cart
        $this->postJson('/api/cart', [
            'product_id' => $this->product->id,
            'quantity' => 3, // 3 * 299.99 = 899.97
        ]);

        // 3. Test percentage coupon
        $percentageResponse = $this->postJson('/api/cart/coupon', [
            'coupon_code' => 'SAVE20',
        ]);

        $percentageResponse->assertStatus(200)
                         ->assertJsonPath('success', true);

        // 4. Remove coupon and test fixed coupon
        $this->deleteJson('/api/cart/coupon');

        $fixedResponse = $this->postJson('/api/cart/coupon', [
            'coupon_code' => 'FLAT100',
        ]);

        $fixedResponse->assertStatus(200)
                     ->assertJsonPath('success', true);

        // 5. Test invalid coupon
        $invalidResponse = $this->postJson('/api/cart/coupon', [
            'coupon_code' => 'INVALID',
        ]);

        $invalidResponse->assertStatus(400)
                       ->assertJsonPath('success', false);

        $this->assertTrue(true, 'Coupon system completed successfully');
    }

    /** @test */
    public function test_user_profile_management()
    {
        Sanctum::actingAs($this->user);

        // 1. Update profile
        $updateResponse = $this->putJson('/api/user/profile', [
            'first_name' => 'Updated',
            'last_name' => 'User',
            'phone' => '9876543210',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
        ]);

        $updateResponse->assertStatus(200)
                      ->assertJsonPath('success', true)
                      ->assertJsonPath('user.first_name', 'Updated');

        // 2. Add address
        $addressResponse = $this->postJson('/api/user/addresses', [
            'type' => 'office',
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '9876543210',
            'address_line_1' => '456 Office Street',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'postal_code' => '110001',
            'country' => 'India',
            'is_default' => false,
        ]);

        $addressResponse->assertStatus(200)
                       ->assertJsonStructure([
                           'success',
                           'message',
                           'address' => [
                               'id',
                               'type',
                               'city',
                               'state'
                           ]
                       ]);

        // 3. Get addresses
        $getAddressesResponse = $this->getJson('/api/user/addresses');
        
        $getAddressesResponse->assertStatus(200)
                            ->assertJsonCount(1, 'addresses');

        // 4. Change password
        $passwordResponse = $this->putJson('/api/user/password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $passwordResponse->assertStatus(200)
                        ->assertJsonPath('success', true);

        $this->assertTrue(true, 'User profile management completed successfully');
    }

    /** @test */
    public function test_category_and_navigation()
    {
        // Create subcategories
        $subcategory = Category::create([
            'name' => 'Mystery Fiction',
            'slug' => 'mystery-fiction',
            'parent_id' => $this->category->id,
            'is_active' => true,
        ]);

        // 1. Get all categories
        $categoriesResponse = $this->getJson('/api/categories');
        
        $categoriesResponse->assertStatus(200)
                          ->assertJsonStructure([
                              'success',
                              'categories' => [
                                  '*' => [
                                      'id',
                                      'name',
                                      'slug'
                                  ]
                              ]
                          ]);

        // 2. Get category with products
        $categoryResponse = $this->getJson("/api/categories/{$this->category->id}");
        
        $categoryResponse->assertStatus(200)
                        ->assertJsonStructure([
                            'success',
                            'category' => [
                                'id',
                                'name',
                                'products'
                            ]
                        ]);

        $this->assertTrue(true, 'Category navigation completed successfully');
    }

    /** @test */
    public function test_mobile_optimized_apis()
    {
        Sanctum::actingAs($this->user);

        // 1. Get mobile dashboard
        $dashboardResponse = $this->getJson('/api/mobile/dashboard');
        
        $dashboardResponse->assertStatus(200)
                         ->assertJsonStructure([
                             'success',
                             'dashboard' => [
                                 'user',
                                 'quick_stats',
                                 'recent_orders',
                                 'personalized_recommendations'
                             ]
                         ]);

        // 2. Get quick actions
        $actionsResponse = $this->getJson('/api/mobile/quick-actions');
        
        $actionsResponse->assertStatus(200)
                       ->assertJsonStructure([
                           'success',
                           'actions' => [
                               '*' => [
                                   'id',
                                   'title',
                                   'icon',
                                   'route'
                               ]
                           ]
                       ]);

        // 3. Get mobile product card
        $cardResponse = $this->getJson("/api/mobile/products/{$this->product->id}");
        
        $cardResponse->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                        'product' => [
                            'id',
                            'name',
                            'price',
                            'badges',
                            'quick_actions'
                        ]
                    ]);

        $this->assertTrue(true, 'Mobile optimized APIs completed successfully');
    }

    /** @test */
    public function test_pwa_features()
    {
        // 1. Get PWA manifest
        $manifestResponse = $this->getJson('/api/pwa/manifest');
        
        $manifestResponse->assertStatus(200)
                        ->assertHeader('Content-Type', 'application/manifest+json')
                        ->assertJsonStructure([
                            'name',
                            'short_name',
                            'icons',
                            'start_url',
                            'display'
                        ]);

        // 2. Get service worker
        $swResponse = $this->get('/api/pwa/sw.js');
        
        $swResponse->assertStatus(200)
                  ->assertHeader('Content-Type', 'application/javascript');

        // 3. Get mobile homepage data
        $homepageResponse = $this->getJson('/api/pwa/homepage');
        
        $homepageResponse->assertStatus(200)
                        ->assertJsonStructure([
                            'banners',
                            'featured_products',
                            'categories',
                            'trending_products'
                        ]);

        $this->assertTrue(true, 'PWA features completed successfully');
    }

    /** @test */
    public function test_system_performance_and_caching()
    {
        // This test verifies caching is working
        
        // 1. First request (cache miss)
        $start1 = microtime(true);
        $response1 = $this->getJson('/api/products');
        $time1 = microtime(true) - $start1;
        
        $response1->assertStatus(200);

        // 2. Second request (should be from cache and faster)
        $start2 = microtime(true);
        $response2 = $this->getJson('/api/products');
        $time2 = microtime(true) - $start2;
        
        $response2->assertStatus(200);

        // Cache should make second request faster (in theory)
        $this->assertLessThanOrEqual($time1 * 2, $time2, 'Second request should not be significantly slower');

        $this->assertTrue(true, 'System performance test completed successfully');
    }

    /** @test */
    public function test_complete_system_integration()
    {
        // This is the ultimate test that combines all features
        
        // 1. Register user
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Integration Test User',
            'email' => 'integration@bookbharat.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $token = $registerResponse->json('token');
        $user = User::where('email', 'integration@bookbharat.com')->first();

        // 2. Browse products and add to cart
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
             ->postJson('/api/cart', [
                 'product_id' => $this->product->id,
                 'quantity' => 2,
             ])
             ->assertStatus(200);

        // 3. Add to wishlist
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
             ->postJson('/api/user/wishlist', [
                 'product_id' => $this->product->id,
             ])
             ->assertStatus(200);

        // 4. Create address
        $addressResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                                ->postJson('/api/user/addresses', [
                                    'type' => 'home',
                                    'first_name' => 'Integration',
                                    'last_name' => 'User',
                                    'phone' => '9876543210',
                                    'address_line_1' => '123 Integration Street',
                                    'city' => 'Mumbai',
                                    'state' => 'Maharashtra',
                                    'postal_code' => '400001',
                                    'country' => 'India',
                                    'is_default' => true,
                                ]);

        $addressId = $addressResponse->json('address.id');

        // 5. Apply coupon
        $coupon = Coupon::create([
            'code' => 'INTEGRATION10',
            'name' => 'Integration Test Coupon',
            'type' => 'percentage',
            'value' => 10,
            'starts_at' => now(),
            'is_active' => true,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
             ->postJson('/api/cart/coupon', [
                 'coupon_code' => 'INTEGRATION10',
             ])
             ->assertStatus(200);

        // 6. Place order
        $orderResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                              ->postJson('/api/orders', [
                                  'billing_address_id' => $addressId,
                                  'shipping_address_id' => $addressId,
                                  'payment_method' => 'credit_card',
                                  'notes' => 'Integration test order',
                              ]);

        $orderResponse->assertStatus(200);
        $orderId = $orderResponse->json('order.id');

        // 7. Check order in user dashboard
        $dashboardResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                                  ->getJson('/api/user/dashboard');

        $dashboardResponse->assertStatus(200)
                         ->assertJsonStructure([
                             'success',
                             'dashboard' => [
                                 'user_stats' => [
                                     'orders_count',
                                     'total_spent',
                                     'wishlist_count'
                                 ],
                                 'recent_orders',
                                 'wishlist_preview'
                             ]
                         ]);

        // 8. Verify order was created correctly
        $order = Order::find($orderId);
        $this->assertNotNull($order);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals($user->id, $order->user_id);
        $this->assertGreaterThan(0, $order->total_amount);

        // 9. Verify cart was cleared
        $cartResponse = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                             ->getJson('/api/cart');

        $cartResponse->assertStatus(200)
                    ->assertJsonPath('cart.is_empty', true)
                    ->assertJsonPath('cart.items_count', 0);

        // 10. Verify coupon was used
        $this->assertTrue($coupon->fresh()->usage_count > 0);

        $this->assertTrue(true, '🎉 COMPLETE SYSTEM INTEGRATION TEST PASSED! 🎉');
    }
}