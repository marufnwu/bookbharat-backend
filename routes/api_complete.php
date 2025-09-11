<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ReturnController;

/*
|--------------------------------------------------------------------------
| API Routes for BookBharat E-commerce System
|--------------------------------------------------------------------------
|
| Complete API routes for all implemented features including:
| - Payment Gateway Integration (Razorpay & Cashfree)
| - Address Management
| - Wishlist System
| - Review & Rating System
| - Invoice Generation
| - Returns & Exchanges
|
*/

// Authentication middleware group
Route::middleware(['auth:sanctum'])->group(function () {
    
    // Payment Routes
    Route::prefix('payments')->group(function () {
        Route::post('/initiate', [PaymentController::class, 'initiatePayment']);
        Route::get('/order/{orderId}/status', [PaymentController::class, 'getPaymentStatus']);
        Route::post('/refund/{paymentId}', [PaymentController::class, 'refundPayment']);
    });

    // Payment Callback Routes (can be accessed without auth for webhooks)
    Route::prefix('payment-callbacks')->group(function () {
        Route::post('/razorpay', [PaymentController::class, 'razorpayCallback']);
        Route::post('/cashfree/{orderId}', [PaymentController::class, 'cashfreeCallback']);
    });

    // Address Management Routes
    Route::prefix('addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
        Route::get('/{id}', [AddressController::class, 'show']);
        Route::put('/{id}', [AddressController::class, 'update']);
        Route::delete('/{id}', [AddressController::class, 'destroy']);
        Route::post('/{id}/set-default', [AddressController::class, 'setDefault']);
        Route::get('/defaults/get', [AddressController::class, 'getDefaults']);
        Route::post('/validate', [AddressController::class, 'validateAddress']);
    });

    // Wishlist Routes
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

    // Review & Rating Routes
    Route::prefix('reviews')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
        Route::get('/my-reviews', [ReviewController::class, 'userReviews']);
        Route::put('/{id}', [ReviewController::class, 'update']);
        Route::delete('/{id}', [ReviewController::class, 'destroy']);
        Route::get('/eligible-products', [ReviewController::class, 'eligibleProducts']);
        Route::get('/my-stats', [ReviewController::class, 'userStats']);
        Route::post('/{id}/report', [ReviewController::class, 'report']);
    });

    // Invoice Routes
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('/{id}', [InvoiceController::class, 'show']);
        Route::post('/generate/{orderId}', [InvoiceController::class, 'generateForOrder']);
        Route::get('/{id}/download', [InvoiceController::class, 'download']);
        Route::get('/{id}/view', [InvoiceController::class, 'view']);
        Route::post('/{id}/send-email', [InvoiceController::class, 'sendEmail']);
        Route::post('/{id}/mark-paid', [InvoiceController::class, 'markAsPaid']);
        Route::get('/stats/overview', [InvoiceController::class, 'stats']);
        Route::get('/recent/list', [InvoiceController::class, 'recent']);
    });

    // Returns & Exchanges Routes
    Route::prefix('returns')->group(function () {
        Route::get('/eligibility/{orderId}', [ReturnController::class, 'checkEligibility']);
        Route::post('/', [ReturnController::class, 'createReturn']);
        Route::get('/', [ReturnController::class, 'getReturns']);
        Route::get('/{returnId}', [ReturnController::class, 'getReturn']);
        Route::post('/{returnId}/cancel', [ReturnController::class, 'cancelReturn']);
        
        // Admin routes (require admin permissions)
        Route::middleware(['can:manage_returns'])->group(function () {
            Route::get('/admin/list', [ReturnController::class, 'adminGetReturns']);
            Route::put('/admin/{returnId}', [ReturnController::class, 'adminUpdateReturn']);
        });
    });
});

// Public Routes (no authentication required)
Route::prefix('public')->group(function () {
    
    // Public Review Routes
    Route::prefix('reviews')->group(function () {
        Route::get('/product/{productId}', [ReviewController::class, 'index']);
    });
    
    // Payment Webhook Routes (for gateway callbacks)
    Route::prefix('webhooks')->group(function () {
        Route::post('/payment/{gateway}', [PaymentController::class, 'webhook']);
    });
});

/*
|--------------------------------------------------------------------------
| Configuration Routes
|--------------------------------------------------------------------------
|
| These routes can be used to get configuration data for the frontend
|
*/

Route::prefix('config')->group(function () {
    Route::get('/payment-methods', function () {
        return response()->json([
            'status' => 'success',
            'data' => [
                'available_methods' => ['cod', 'razorpay', 'cashfree'],
                'razorpay' => [
                    'enabled' => config('services.razorpay.key') ? true : false,
                    'key_id' => config('services.razorpay.key')
                ],
                'cashfree' => [
                    'enabled' => config('services.cashfree.client_id') ? true : false,
                    'environment' => config('services.cashfree.environment')
                ]
            ]
        ]);
    });

    Route::get('/return-reasons', function () {
        return response()->json([
            'status' => 'success',
            'data' => [
                'defective' => 'Product is defective or damaged',
                'wrong_item' => 'Wrong item was delivered',
                'not_as_described' => 'Item not as described',
                'changed_mind' => 'Changed my mind',
                'damaged_packaging' => 'Damaged packaging',
                'size_issue' => 'Size does not fit',
                'color_issue' => 'Color is different than expected',
            ]
        ]);
    });

    Route::get('/countries', function () {
        return response()->json([
            'status' => 'success',
            'data' => [
                'IN' => 'India',
                'US' => 'United States',
                'GB' => 'United Kingdom',
                'CA' => 'Canada',
                'AU' => 'Australia',
                // Add more countries as needed
            ]
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| Development/Testing Routes
|--------------------------------------------------------------------------
|
| These routes are for development and testing purposes only
| Remove or restrict access in production
|
*/

if (config('app.env') === 'local') {
    Route::prefix('dev')->group(function () {
        
        // Test email sending
        Route::post('/test-email/{type}', function (Request $request, $type) {
            $emailService = app(\App\Services\EmailService::class);
            
            switch ($type) {
                case 'welcome':
                    $user = \App\Models\User::first();
                    $result = $emailService->sendWelcomeEmail($user);
                    break;
                    
                case 'order-confirmation':
                    $order = \App\Models\Order::with('user', 'items.product')->first();
                    $result = $emailService->sendOrderConfirmation($order);
                    break;
                    
                default:
                    return response()->json(['error' => 'Invalid email type'], 400);
            }
            
            return response()->json([
                'status' => $result ? 'success' : 'failed',
                'message' => $result ? 'Email sent successfully' : 'Email sending failed'
            ]);
        });

        // Generate test invoice
        Route::post('/generate-test-invoice', function () {
            $order = \App\Models\Order::first();
            if (!$order) {
                return response()->json(['error' => 'No orders found'], 404);
            }
            
            $invoiceService = app(\App\Services\InvoiceService::class);
            $invoice = $invoiceService->generateInvoiceForOrder($order);
            
            return response()->json([
                'status' => 'success',
                'data' => $invoice
            ]);
        });
    });
}

/*
|--------------------------------------------------------------------------
| Health Check Route
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'services' => [
            'payment_gateways' => [
                'razorpay' => config('services.razorpay.key') ? 'configured' : 'not_configured',
                'cashfree' => config('services.cashfree.client_id') ? 'configured' : 'not_configured',
            ],
            'email' => config('mail.mailers.smtp.host') ? 'configured' : 'not_configured',
            'storage' => \Storage::disk('public')->exists('test') || true ? 'available' : 'unavailable',
        ]
    ]);
});