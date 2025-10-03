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
        // Initiate payment
        Route::post('/initiate', [PaymentController::class, 'initiatePayment']);


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

    // Hero Configuration Routes (Read-only for public)
    Route::prefix('hero')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\HeroConfigController::class, 'index']);
        Route::get('/active', [App\Http\Controllers\Api\HeroConfigController::class, 'getActive']);
        Route::get('/{variant}', [App\Http\Controllers\Api\HeroConfigController::class, 'show']);
    });

    // Cart Routes (Public - supports both guest and authenticated users)
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/add', [CartController::class, 'add']);
        Route::post('/add-bundle', [CartController::class, 'addBundle']);
        Route::put('/update/{cartItem}', [CartController::class, 'update']);
        Route::delete('/remove/{cartItem}', [CartController::class, 'destroy']);
        Route::delete('/clear', [CartController::class, 'clear']);
        Route::post('/apply-coupon', [CartController::class, 'applyCoupon']);
        Route::delete('/remove-coupon', [CartController::class, 'removeCoupon']);
        Route::get('/validate', [CartController::class, 'validateCart']);
        Route::post('/calculate-shipping', [CartController::class, 'calculateShipping']);
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

    // Public Tracking Route (customers can track their shipments)
    Route::get('/shipping/{tracking}/track', [\App\Http\Controllers\Api\MultiCarrierShippingController::class, 'trackShipment']);

    // Webhook endpoints for carriers (needs to be public for carrier callbacks)
    Route::post('/shipping/webhook/{carrier}', [\App\Http\Controllers\Api\MultiCarrierShippingController::class, 'processWebhook']);

    // Payment Gateway Routes
    require __DIR__.'/payment.php';

    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    |
    | IMPORTANT: All admin routes have been moved to routes/admin.php
    | They are automatically mounted at /api/v1/admin/* via bootstrap/app.php
    | DO NOT add admin routes here to avoid duplication and conflicts.
    |
    */

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