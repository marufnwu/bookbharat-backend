<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\StaticPageController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ShippingConfigController;
use App\Http\Controllers\Admin\DeliveryOptionController;
use App\Http\Controllers\Admin\ShippingInsuranceController;
use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\BundleDiscountController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes
Route::prefix('v1')->group(function () {
    
    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        
        // Protected auth routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [AuthController::class, 'user']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::put('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/revoke-tokens', [AuthController::class, 'revokeAllTokens']);
        });
    });

    // Product Routes (Public)
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/featured', [ProductController::class, 'featured']);
        Route::get('/by-categories', [ProductController::class, 'getByCategories']);
        Route::get('/search', [ProductController::class, 'search']);
        Route::get('/suggestions', [ProductController::class, 'suggestions']);
        Route::get('/filters', [ProductController::class, 'filters']);
        Route::get('/{id}', [ProductController::class, 'show']);
        Route::get('/{id}/related', [ProductController::class, 'getRelatedProducts']);
        Route::get('/{id}/frequently-bought-together', [ProductController::class, 'getFrequentlyBoughtTogether']);
        Route::get('/category/{categoryId}', [ProductController::class, 'byCategory']);
    });

    // Bundle Discount Rules (Public - for display)
    Route::get('/bundle-discounts/active', [BundleDiscountController::class, 'getActiveRules']);

    // Category Routes (Public)
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);

    // Shipping Routes (Public)
    Route::prefix('shipping')->group(function () {
        Route::get('/zones', [ShippingController::class, 'getShippingZones']);
        Route::post('/check-pincode', [ShippingController::class, 'checkPincode']);
        Route::get('/rates', [ShippingController::class, 'getShippingRates']);
        Route::post('/calculate', [ShippingController::class, 'calculateShipping']);
        Route::post('/calculate-cart', [ShippingController::class, 'calculateCartShipping']);
    });

    // Payment Routes (Public)
    Route::prefix('payment')->group(function () {
        Route::get('/gateways', [PaymentController::class, 'getAvailablePaymentMethods']);
        Route::get('/methods', [PaymentController::class, 'getAvailablePaymentMethods']); // Alias for compatibility

        // Callback routes (when user returns from payment gateway)
        Route::any('/callback/{gateway}', [PaymentController::class, 'callback'])->name('payment.callback');

        // Webhook routes (backend notifications from payment gateway)
        Route::post('/webhook/{gateway}', [PaymentController::class, 'webhook'])->name('payment.webhook');

        // Status check
        Route::get('/status/{orderId}', [PaymentController::class, 'getPaymentStatus']);
    });

    // Configuration Routes (Public)
    Route::prefix('config')->group(function () {
        Route::get('/site', [ConfigurationController::class, 'getSiteConfig']);
        Route::get('/homepage', [ConfigurationController::class, 'getHomepageConfig']);
        Route::get('/navigation', [ConfigurationController::class, 'getNavigationConfig']);
        Route::get('/content/{slug}', [ConfigurationController::class, 'getContentPage']);
    });

    // Static Pages Routes (Public)
    Route::prefix('pages')->group(function () {
        Route::get('/', [StaticPageController::class, 'getPages']);
        Route::get('/{slug}', [StaticPageController::class, 'getPage']);
    });

    // FAQ Routes (Public)
    Route::prefix('faqs')->group(function () {
        Route::get('/', [FaqController::class, 'index']);
        Route::get('/categories', [FaqController::class, 'getCategories']);
        Route::get('/search', [FaqController::class, 'search']);
        Route::get('/{id}', [FaqController::class, 'show']);
    });

    // Hero Configuration Routes
    Route::prefix('hero')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\HeroConfigController::class, 'index']);
        Route::get('/active', [App\Http\Controllers\Api\HeroConfigController::class, 'getActive']);
        Route::get('/{variant}', [App\Http\Controllers\Api\HeroConfigController::class, 'show']);
        Route::post('/set-active', [App\Http\Controllers\Api\HeroConfigController::class, 'setActive']);
        Route::put('/{variant}', [App\Http\Controllers\Api\HeroConfigController::class, 'update']);
    });

    // Cart Routes (Public - supports both guest and authenticated users)
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/add', [CartController::class, 'add']);
        Route::put('/update/{cartItem}', [CartController::class, 'update']);
        Route::delete('/remove/{cartItem}', [CartController::class, 'destroy']);
        Route::delete('/clear', [CartController::class, 'clear']);
        Route::post('/apply-coupon', [CartController::class, 'applyCoupon']);
        Route::delete('/remove-coupon', [CartController::class, 'removeCoupon']);
        Route::get('/validate', [CartController::class, 'validateCart']);
    });

    // Coupon Routes

    // Public Review Routes

    // Contact Routes (Public)

    // Newsletter Routes (Public)
    Route::prefix('newsletter')->group(function () {
        Route::post('/subscribe', [NewsletterController::class, 'subscribe']);
        Route::post('/unsubscribe', [NewsletterController::class, 'unsubscribe']);
        Route::get('/status', [NewsletterController::class, 'getStatus']);
    });
    Route::prefix('contact')->group(function () {
        Route::post('/submit', [ContactController::class, 'submit']);
        Route::get('/categories', [ContactController::class, 'getCategories']);
    });
    Route::prefix('reviews')->group(function () {
        Route::get('/product/{productId}', [ReviewController::class, 'index']);
    });
    Route::prefix('coupons')->group(function () {
        Route::get('/available', [CartController::class, 'getAvailableCoupons']);
    });

    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {

        // Order Routes
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store']);
            Route::get('/{order_id}', [OrderController::class, 'show']);
            Route::put('/{order_id}/cancel', [OrderController::class, 'cancel']);
            Route::get('/{order_id}/invoice', [OrderController::class, 'downloadInvoice']);
            Route::get('/{order_id}/receipt', [OrderController::class, 'downloadReceipt']);
        });

        // Shipping Routes (Protected)
        Route::prefix('shipping')->group(function () {
            Route::get('/delivery-options', [ShippingController::class, 'getAvailableDeliveryOptions']);
            Route::get('/insurance-plans', [ShippingController::class, 'getAvailableInsurancePlans']);
        });

        // Address Routes (Protected)
        Route::prefix('addresses')->group(function () {
            Route::get('/', [AddressController::class, 'index']);
            Route::post('/', [AddressController::class, 'store']);
            Route::get('/{id}', [AddressController::class, 'show']);
            Route::put('/{id}', [AddressController::class, 'update']);
            Route::delete('/{id}', [AddressController::class, 'destroy']);
            Route::put('/{id}/set-default', [AddressController::class, 'setDefault']);
            Route::get('/defaults/all', [AddressController::class, 'getDefaults']);
            Route::post('/validate', [AddressController::class, 'validateAddress']);
        });

        // Wishlist Routes

        // Review Routes
        Route::prefix('reviews')->group(function () {
            Route::post('/', [ReviewController::class, 'store']);
            Route::get('/my-reviews', [ReviewController::class, 'userReviews']);
            Route::put('/{id}', [ReviewController::class, 'update']);
            Route::delete('/{id}', [ReviewController::class, 'destroy']);
            Route::get('/eligible-products', [ReviewController::class, 'eligibleProducts']);
            Route::get('/my-stats', [ReviewController::class, 'userStats']);
            Route::post('/{id}/report', [ReviewController::class, 'report']);
        });
        Route::prefix('wishlist')->group(function () {
            Route::get('/', [WishlistController::class, 'index']);
            Route::post('/', [WishlistController::class, 'store']);
            Route::put('/{id}', [WishlistController::class, 'update']);
            Route::delete('/{id}', [WishlistController::class, 'destroy']);
            Route::post('/check', [WishlistController::class, 'check']);
            Route::post('/move-to-cart', [WishlistController::class, 'moveToCart']);
            Route::delete('/clear/all', [WishlistController::class, 'clear']);
            Route::get('/stats', [WishlistController::class, 'stats']);
        });
    });

    // Payment Gateway Routes
    require __DIR__.'/payment.php';

    // Admin routes (require admin role)
    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
        

        // Category Management
        Route::prefix('categories')->group(function () {
            Route::post('/', [CategoryController::class, 'store']);
            Route::put('/{id}', [CategoryController::class, 'update']);
            Route::delete('/{id}', [CategoryController::class, 'destroy']);
        });

        // Order Management
        Route::prefix('orders')->group(function () {
            Route::get('/all', [OrderController::class, 'adminIndex']);
            Route::put('/{id}/status', [OrderController::class, 'updateStatus']);
            Route::put('/{id}/payment-status', [OrderController::class, 'updatePaymentStatus']);
        });

        // Bundle Discount Management
        Route::prefix('bundle-discounts')->group(function () {
            Route::get('/', [BundleDiscountController::class, 'index']);
            Route::post('/', [BundleDiscountController::class, 'store']);
            Route::get('/{id}', [BundleDiscountController::class, 'show']);
            Route::put('/{id}', [BundleDiscountController::class, 'update']);
            Route::delete('/{id}', [BundleDiscountController::class, 'destroy']);
            Route::post('/{id}/toggle-active', [BundleDiscountController::class, 'toggleActive']);
            Route::post('/preview', [BundleDiscountController::class, 'previewDiscount']);
        });

        // Dashboard & Analytics
        Route::get('/dashboard/overview', [AdminDashboardController::class, 'overview']);
        Route::get('/dashboard/sales-analytics', [AdminDashboardController::class, 'salesAnalytics']);
        Route::get('/dashboard/customer-analytics', [AdminDashboardController::class, 'customerAnalytics']);
        Route::get('/dashboard/inventory-overview', [AdminDashboardController::class, 'inventoryOverview']);
        Route::get('/dashboard/order-insights', [AdminDashboardController::class, 'orderInsights']);
        Route::get('/dashboard/marketing-performance', [AdminDashboardController::class, 'marketingPerformance']);

        // User Management
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']);
            Route::get('/{user}', [AdminUserController::class, 'show']);
            Route::put('/{user}', [AdminUserController::class, 'update']);
            Route::post('/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
            Route::post('/{user}/toggle-status', [AdminUserController::class, 'toggleStatus']);
            Route::get('/{user}/analytics', [AdminUserController::class, 'getAnalytics']);
            Route::get('/{user}/orders', [AdminUserController::class, 'getOrders']);
            Route::get('/{user}/addresses', [AdminUserController::class, 'getAddresses']);
        });

        // Customers (alias for users)
        Route::prefix('customers')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']);
            Route::get('/{user}', [AdminUserController::class, 'show']);
            Route::put('/{user}', [AdminUserController::class, 'update']);
            Route::delete('/{user}', [AdminUserController::class, 'destroy']);
        });

        // Settings Management
        Route::prefix('settings')->group(function () {
            Route::get('/general', [SettingsController::class, 'getGeneral']);
            Route::put('/general', [SettingsController::class, 'updateGeneral']);
            Route::get('/roles', [SettingsController::class, 'getRoles']);
            Route::post('/roles', [SettingsController::class, 'createRole']);
            Route::put('/roles/{role}', [SettingsController::class, 'updateRole']);
            Route::delete('/roles/{role}', [SettingsController::class, 'deleteRole']);
            Route::get('/email-templates', [SettingsController::class, 'getEmailTemplates']);
            Route::get('/payment', [SettingsController::class, 'getPayment']);
            Route::get('/shipping', [SettingsController::class, 'getShipping']);
        });

        // Advanced Order Management
        Route::prefix('orders')->group(function () {
            Route::get('/', [AdminOrderController::class, 'index']);
            Route::get('/{order}', [AdminOrderController::class, 'show']);
            Route::put('/{order}/status', [AdminOrderController::class, 'updateStatus']);
            Route::put('/{order}/payment-status', [AdminOrderController::class, 'updatePaymentStatus']);
            Route::post('/{order}/cancel', [AdminOrderController::class, 'cancel']);
            Route::post('/{order}/refund', [AdminOrderController::class, 'refund']);
            Route::get('/{order}/timeline', [AdminOrderController::class, 'getTimeline']);
        });

        // Advanced Product Management
        Route::prefix('products')->group(function () {
            Route::get('/', [AdminProductController::class, 'index']);
            Route::get('/{product}', [AdminProductController::class, 'show']);
            Route::post('/', [AdminProductController::class, 'store']);
            Route::put('/{product}', [AdminProductController::class, 'update']);
            Route::delete('/{product}', [AdminProductController::class, 'destroy']);
            Route::post('/{product}/images', [AdminProductController::class, 'uploadImages']);
            Route::put('/{product}/toggle-status', [AdminProductController::class, 'toggleStatus']);
            Route::get('/{product}/analytics', [AdminProductController::class, 'analytics']);
        });

        // System Management
        Route::prefix('system')->group(function () {
            Route::get('/health', function () {
                return response()->json([
                    'system_status' => 'healthy',
                    'database_status' => 'connected',
                    'cache_status' => 'active',
                    'queue_status' => 'running',
                    'storage_status' => 'accessible',
                    'memory_usage' => memory_get_usage(true),
                    'server_time' => now(),
                ]);
            });
            
            Route::post('/cache/clear', function () {
                \Artisan::call('cache:clear');
                return response()->json(['message' => 'Cache cleared successfully']);
            });
            
            Route::post('/optimize', function () {
                \Artisan::call('optimize');
                return response()->json(['message' => 'Application optimized successfully']);
            });
        });

        // Shipping Configuration Management
        Route::prefix('shipping')->group(function () {
            // Weight Slabs Management
            Route::prefix('weight-slabs')->group(function () {
                Route::get('/', [ShippingConfigController::class, 'getWeightSlabs']);
                Route::post('/', [ShippingConfigController::class, 'storeWeightSlab']);
                Route::put('/{id}', [ShippingConfigController::class, 'updateWeightSlab']);
                Route::delete('/{id}', [ShippingConfigController::class, 'deleteWeightSlab']);
                Route::post('/bulk-import', [ShippingConfigController::class, 'bulkImportWeightSlabs']);
            });

            // Shipping Rates Management
            Route::prefix('rates')->group(function () {
                Route::get('/', [ShippingConfigController::class, 'getShippingRates']);
                Route::post('/', [ShippingConfigController::class, 'storeShippingRate']);
                Route::put('/{id}', [ShippingConfigController::class, 'updateShippingRate']);
                Route::delete('/{id}', [ShippingConfigController::class, 'deleteShippingRate']);
                Route::post('/bulk-import', [ShippingConfigController::class, 'bulkImportShippingRates']);
            });

            // Pincode Zone Management
            Route::prefix('pincodes')->group(function () {
                Route::get('/', [ShippingConfigController::class, 'getPincodeZones']);
                Route::post('/', [ShippingConfigController::class, 'storePincodeZone']);
                Route::put('/{id}', [ShippingConfigController::class, 'updatePincodeZone']);
                Route::delete('/{id}', [ShippingConfigController::class, 'deletePincodeZone']);
                Route::post('/bulk-import', [ShippingConfigController::class, 'bulkImportPincodes']);
                Route::post('/check-zone', [ShippingConfigController::class, 'checkPincodeZone']);
            });

            // Zone Management
            Route::prefix('zones')->group(function () {
                Route::get('/', [ShippingConfigController::class, 'getZones']);
                Route::post('/', [ShippingConfigController::class, 'storeZone']);
                Route::put('/{id}', [ShippingConfigController::class, 'updateZone']);
                Route::delete('/{id}', [ShippingConfigController::class, 'deleteZone']);
            });

            // Testing & Analytics
            Route::post('/test-calculation', [ShippingConfigController::class, 'testShippingCalculation']);
            Route::get('/analytics', [ShippingConfigController::class, 'getShippingAnalytics']);
            Route::get('/performance-metrics', [ShippingConfigController::class, 'getPerformanceMetrics']);
        });

        // Delivery Options Management
        Route::prefix('delivery-options')->group(function () {
            Route::get('/', [DeliveryOptionController::class, 'index']);
            Route::get('/{deliveryOption}', [DeliveryOptionController::class, 'show']);
            Route::post('/', [DeliveryOptionController::class, 'store']);
            Route::put('/{deliveryOption}', [DeliveryOptionController::class, 'update']);
            Route::delete('/{deliveryOption}', [DeliveryOptionController::class, 'destroy']);
            Route::put('/{deliveryOption}/toggle-status', [DeliveryOptionController::class, 'toggleStatus']);
            Route::post('/test-availability', [DeliveryOptionController::class, 'testAvailability']);
            Route::post('/get-available', [DeliveryOptionController::class, 'getAvailableForConditions']);
            Route::put('/sort-order', [DeliveryOptionController::class, 'updateSortOrder']);
            Route::get('/analytics/overview', [DeliveryOptionController::class, 'analytics']);
        });

        // Shipping Insurance Management
        Route::prefix('shipping-insurance')->group(function () {
            Route::get('/', [ShippingInsuranceController::class, 'index']);
            Route::get('/{insurance}', [ShippingInsuranceController::class, 'show']);
            Route::post('/', [ShippingInsuranceController::class, 'store']);
            Route::put('/{insurance}', [ShippingInsuranceController::class, 'update']);
            Route::put('/{insurance}/toggle-status', [ShippingInsuranceController::class, 'toggleStatus']);
            Route::post('/test-calculation', [ShippingInsuranceController::class, 'testCalculation']);
        });

        // Content Management
        Route::prefix('content')->group(function () {
            Route::put('/site-config', [ContentController::class, 'updateSiteConfig']);
            Route::put('/homepage-config', [ContentController::class, 'updateHomepageConfig']);
            Route::put('/navigation-config', [ContentController::class, 'updateNavigationConfig']);
            Route::put('/pages/{slug}', [ContentController::class, 'updateContentPage']);
            Route::post('/media/upload', [ContentController::class, 'uploadMedia']);
            Route::get('/media/library', [ContentController::class, 'getMediaLibrary']);
            Route::delete('/media/{id}', [ContentController::class, 'deleteMedia']);
            Route::get('/theme-presets', [ContentController::class, 'getThemePresets']);
        });
    });

    // Health check route
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'message' => 'BookBharat API is running',
            'timestamp' => now(),
            'version' => '1.0.0'
        ]);
    });
});

// Fallback route for API
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});