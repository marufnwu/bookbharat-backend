<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\PromotionalCampaignController;
use App\Http\Controllers\Admin\ShippingConfigController;
use App\Http\Controllers\Admin\DeliveryOptionController;
use App\Http\Controllers\Admin\ShippingInsuranceController;
use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\ContentModerationController;
use App\Http\Controllers\Admin\BundleDiscountController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\AuthController;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| All admin panel routes are defined here. These routes are prefixed
| with 'admin' and require authentication and admin role.
|
*/

// Admin Authentication (public routes)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/check', [AuthController::class, 'check'])->middleware('auth:sanctum');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');
});

// Protected Admin Routes
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {

    // Dashboard & Analytics
    Route::prefix('dashboard')->group(function () {
        Route::get('/overview', [DashboardController::class, 'overview']);
        Route::get('/sales-analytics', [DashboardController::class, 'salesAnalytics']);
        Route::get('/customer-analytics', [DashboardController::class, 'customerAnalytics']);
        Route::get('/inventory-overview', [DashboardController::class, 'inventoryOverview']);
        Route::get('/order-insights', [DashboardController::class, 'orderInsights']);
        Route::get('/marketing-performance', [DashboardController::class, 'marketingPerformance']);
        Route::get('/real-time-stats', [DashboardController::class, 'realTimeStats']);
    });

    // Product Management
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{product}', [ProductController::class, 'show']);
        Route::post('/', [ProductController::class, 'store']);
        Route::put('/{product}', [ProductController::class, 'update']);
        Route::delete('/{product}', [ProductController::class, 'destroy']);
        Route::post('/bulk-action', [ProductController::class, 'bulkAction']);
        Route::post('/{product}/duplicate', [ProductController::class, 'duplicate']);
        Route::post('/{product}/images', [ProductController::class, 'uploadImages']);
        Route::delete('/{product}/images/{image}', [ProductController::class, 'deleteImage']);
        Route::put('/{product}/toggle-status', [ProductController::class, 'toggleStatus']);
        Route::put('/{product}/toggle-featured', [ProductController::class, 'toggleFeatured']);
        Route::get('/{product}/analytics', [ProductController::class, 'analytics']);
        Route::post('/import', [ProductController::class, 'import']);
        Route::get('/export', [ProductController::class, 'export']);
    });

    // Category Management
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/tree', [CategoryController::class, 'tree']);
        Route::get('/{category}', [CategoryController::class, 'show']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
        Route::put('/{category}/move', [CategoryController::class, 'move']);
        Route::post('/{category}/image', [CategoryController::class, 'uploadImage']);
    });

    // Order Management
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::put('/{order}/status', [OrderController::class, 'updateStatus']);
        Route::put('/{order}/payment-status', [OrderController::class, 'updatePaymentStatus']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
        Route::post('/{order}/refund', [OrderController::class, 'refund']);
        Route::get('/{order}/timeline', [OrderController::class, 'getTimeline']);
        Route::post('/{order}/note', [OrderController::class, 'addNote']);
        Route::post('/{order}/tracking', [OrderController::class, 'updateTracking']);
        Route::get('/{order}/invoice', [OrderController::class, 'getInvoice']);
        Route::post('/{order}/send-email', [OrderController::class, 'sendEmail']);
        Route::post('/bulk-update-status', [OrderController::class, 'bulkUpdateStatus']);
        Route::get('/export', [OrderController::class, 'export']);
    });

    // User Management
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::post('/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::get('/{user}/orders', [UserController::class, 'getOrders']);
        Route::get('/{user}/addresses', [UserController::class, 'getAddresses']);
        Route::get('/{user}/analytics', [UserController::class, 'getAnalytics']);
        Route::post('/{user}/send-email', [UserController::class, 'sendEmail']);
        Route::post('/bulk-action', [UserController::class, 'bulkAction']);
        Route::get('/export', [UserController::class, 'export']);
    });

    // Coupon Management
    Route::prefix('coupons')->group(function () {
        Route::get('/', [CouponController::class, 'index']);
        Route::get('/{coupon}', [CouponController::class, 'show']);
        Route::post('/', [CouponController::class, 'store']);
        Route::put('/{coupon}', [CouponController::class, 'update']);
        Route::delete('/{coupon}', [CouponController::class, 'destroy']);
        Route::post('/bulk-action', [CouponController::class, 'bulkAction']);
        Route::post('/generate-code', [CouponController::class, 'generateCode']);
        Route::post('/validate', [CouponController::class, 'validateCoupon']);
        Route::get('/{coupon}/usage-report', [CouponController::class, 'getUsageReport']);
        Route::post('/bulk-generate', [CouponController::class, 'bulkGenerate']);
    });

    // Bundle Discount Management
    Route::prefix('bundle-discounts')->group(function () {
        Route::get('/', [BundleDiscountController::class, 'index']);
        Route::get('/{id}', [BundleDiscountController::class, 'show']);
        Route::post('/', [BundleDiscountController::class, 'store']);
        Route::put('/{id}', [BundleDiscountController::class, 'update']);
        Route::delete('/{id}', [BundleDiscountController::class, 'destroy']);
        Route::post('/{id}/toggle-active', [BundleDiscountController::class, 'toggleActive']);
        Route::post('/preview', [BundleDiscountController::class, 'previewDiscount']);
        Route::get('/analytics', [BundleDiscountController::class, 'getAnalytics']);
    });

    // Promotional Campaign Management
    Route::prefix('campaigns')->group(function () {
        Route::get('/', [PromotionalCampaignController::class, 'index']);
        Route::get('/{campaign}', [PromotionalCampaignController::class, 'show']);
        Route::post('/', [PromotionalCampaignController::class, 'store']);
        Route::put('/{campaign}', [PromotionalCampaignController::class, 'update']);
        Route::delete('/{campaign}', [PromotionalCampaignController::class, 'destroy']);
        Route::post('/{campaign}/activate', [PromotionalCampaignController::class, 'activate']);
        Route::post('/{campaign}/pause', [PromotionalCampaignController::class, 'pause']);
        Route::post('/{campaign}/end', [PromotionalCampaignController::class, 'end']);
        Route::post('/{campaign}/duplicate', [PromotionalCampaignController::class, 'duplicate']);
        Route::post('/{campaign}/generate-coupons', [PromotionalCampaignController::class, 'generateCoupons']);
        Route::get('/{campaign}/eligible-users', [PromotionalCampaignController::class, 'getEligibleUsers']);
        Route::get('/{campaign}/performance-report', [PromotionalCampaignController::class, 'getPerformanceReport']);
    });

    // Shipping Configuration
    Route::prefix('shipping')->group(function () {
        Route::get('/overview', [ShippingConfigController::class, 'overview']);

        // Zones
        Route::prefix('zones')->group(function () {
            Route::get('/', [ShippingConfigController::class, 'getZones']);
            Route::post('/', [ShippingConfigController::class, 'storeZone']);
            Route::put('/{id}', [ShippingConfigController::class, 'updateZone']);
            Route::delete('/{id}', [ShippingConfigController::class, 'deleteZone']);
        });

        // Weight Slabs
        Route::prefix('weight-slabs')->group(function () {
            Route::get('/', [ShippingConfigController::class, 'getWeightSlabs']);
            Route::post('/', [ShippingConfigController::class, 'storeWeightSlab']);
            Route::put('/{id}', [ShippingConfigController::class, 'updateWeightSlab']);
            Route::delete('/{id}', [ShippingConfigController::class, 'deleteWeightSlab']);
            Route::post('/bulk-import', [ShippingConfigController::class, 'bulkImportWeightSlabs']);
        });

        // Pincode Management
        Route::prefix('pincodes')->group(function () {
            Route::get('/', [ShippingConfigController::class, 'getPincodeZones']);
            Route::post('/', [ShippingConfigController::class, 'storePincodeZone']);
            Route::put('/{id}', [ShippingConfigController::class, 'updatePincodeZone']);
            Route::delete('/{id}', [ShippingConfigController::class, 'deletePincodeZone']);
            Route::post('/bulk-import', [ShippingConfigController::class, 'bulkImportPincodes']);
            Route::post('/check-zone', [ShippingConfigController::class, 'checkPincodeZone']);
        });

        // Warehouses
        Route::prefix('warehouses')->group(function () {
            Route::get('/', [ShippingConfigController::class, 'getWarehouses']);
            Route::post('/', [ShippingConfigController::class, 'storeWarehouse']);
            Route::put('/{id}', [ShippingConfigController::class, 'updateWarehouse']);
            Route::delete('/{id}', [ShippingConfigController::class, 'deleteWarehouse']);
            Route::post('/{id}/set-default', [ShippingConfigController::class, 'setDefaultWarehouse']);
        });

        // Testing & Analytics
        Route::post('/test-calculation', [ShippingConfigController::class, 'testCalculation']);
        Route::get('/analytics', [ShippingConfigController::class, 'getAnalytics']);
    });

    // Delivery Options
    Route::prefix('delivery-options')->group(function () {
        Route::get('/', [DeliveryOptionController::class, 'index']);
        Route::get('/{deliveryOption}', [DeliveryOptionController::class, 'show']);
        Route::post('/', [DeliveryOptionController::class, 'store']);
        Route::put('/{deliveryOption}', [DeliveryOptionController::class, 'update']);
        Route::delete('/{deliveryOption}', [DeliveryOptionController::class, 'destroy']);
        Route::put('/{deliveryOption}/toggle-status', [DeliveryOptionController::class, 'toggleStatus']);
        Route::post('/test-availability', [DeliveryOptionController::class, 'testAvailability']);
        Route::put('/sort-order', [DeliveryOptionController::class, 'updateSortOrder']);
        Route::get('/analytics', [DeliveryOptionController::class, 'analytics']);
    });

    // Shipping Insurance
    Route::prefix('shipping-insurance')->group(function () {
        Route::get('/', [ShippingInsuranceController::class, 'index']);
        Route::get('/{insurance}', [ShippingInsuranceController::class, 'show']);
        Route::post('/', [ShippingInsuranceController::class, 'store']);
        Route::put('/{insurance}', [ShippingInsuranceController::class, 'update']);
        Route::delete('/{insurance}', [ShippingInsuranceController::class, 'destroy']);
        Route::put('/{insurance}/toggle-status', [ShippingInsuranceController::class, 'toggleStatus']);
        Route::post('/test-calculation', [ShippingInsuranceController::class, 'testCalculation']);
    });

    // Inventory Management
    Route::prefix('inventory')->group(function () {
        Route::get('/overview', [InventoryController::class, 'overview']);
        Route::get('/low-stock', [InventoryController::class, 'getLowStockProducts']);
        Route::get('/out-of-stock', [InventoryController::class, 'getOutOfStockProducts']);
        Route::get('/movements', [InventoryController::class, 'getMovements']);
        Route::post('/adjust', [InventoryController::class, 'adjustStock']);
        Route::post('/bulk-update', [InventoryController::class, 'bulkUpdate']);
        Route::get('/value-report', [InventoryController::class, 'getValueReport']);
        Route::get('/export', [InventoryController::class, 'export']);
    });

    // Review Management
    Route::prefix('reviews')->group(function () {
        Route::get('/', [ReviewController::class, 'index']);
        Route::get('/{review}', [ReviewController::class, 'show']);
        Route::put('/{review}/approve', [ReviewController::class, 'approve']);
        Route::put('/{review}/reject', [ReviewController::class, 'reject']);
        Route::delete('/{review}', [ReviewController::class, 'destroy']);
        Route::post('/bulk-action', [ReviewController::class, 'bulkAction']);
        Route::get('/pending', [ReviewController::class, 'getPending']);
        Route::get('/reported', [ReviewController::class, 'getReported']);
    });

    // Content Management
    Route::prefix('content')->group(function () {
        Route::get('/site-config', [ContentController::class, 'getSiteConfig']);
        Route::put('/site-config', [ContentController::class, 'updateSiteConfig']);
        Route::get('/homepage-config', [ContentController::class, 'getHomepageConfig']);
        Route::put('/homepage-config', [ContentController::class, 'updateHomepageConfig']);
        Route::get('/navigation-config', [ContentController::class, 'getNavigationConfig']);
        Route::put('/navigation-config', [ContentController::class, 'updateNavigationConfig']);
        Route::get('/pages', [ContentController::class, 'getPages']);
        Route::get('/pages/{slug}', [ContentController::class, 'getPage']);
        Route::put('/pages/{slug}', [ContentController::class, 'updateContentPage']);
        Route::post('/media/upload', [ContentController::class, 'uploadMedia']);
        Route::get('/media/library', [ContentController::class, 'getMediaLibrary']);
        Route::delete('/media/{id}', [ContentController::class, 'deleteMedia']);
        Route::get('/theme-presets', [ContentController::class, 'getThemePresets']);
    });

    // Content Moderation
    Route::prefix('moderation')->group(function () {
        Route::get('/', [ContentModerationController::class, 'index']);
        Route::get('/{content}', [ContentModerationController::class, 'show']);
        Route::post('/approve', [ContentModerationController::class, 'approve']);
        Route::post('/reject', [ContentModerationController::class, 'reject']);
        Route::post('/bulk-action', [ContentModerationController::class, 'bulkAction']);
        Route::get('/featured', [ContentModerationController::class, 'getFeatured']);
        Route::put('/featured-status', [ContentModerationController::class, 'updateFeaturedStatus']);
        Route::get('/analytics', [ContentModerationController::class, 'getContentAnalytics']);
    });

    // Payment Management
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::get('/{payment}', [PaymentController::class, 'show']);
        Route::post('/{payment}/refund', [PaymentController::class, 'refund']);
        Route::get('/gateways', [PaymentController::class, 'getGateways']);
        Route::put('/gateways/{gateway}', [PaymentController::class, 'updateGateway']);
        Route::get('/transactions', [PaymentController::class, 'getTransactions']);
        Route::get('/refunds', [PaymentController::class, 'getRefunds']);
        Route::get('/analytics', [PaymentController::class, 'getAnalytics']);
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/sales', [ReportController::class, 'salesReport']);
        Route::get('/products', [ReportController::class, 'productsReport']);
        Route::get('/customers', [ReportController::class, 'customersReport']);
        Route::get('/inventory', [ReportController::class, 'inventoryReport']);
        Route::get('/taxes', [ReportController::class, 'taxesReport']);
        Route::get('/coupons', [ReportController::class, 'couponsReport']);
        Route::get('/shipping', [ReportController::class, 'shippingReport']);
        Route::post('/generate', [ReportController::class, 'generateCustomReport']);
        Route::get('/scheduled', [ReportController::class, 'getScheduledReports']);
        Route::post('/schedule', [ReportController::class, 'scheduleReport']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/send', [NotificationController::class, 'send']);
        Route::get('/templates', [NotificationController::class, 'getTemplates']);
        Route::post('/templates', [NotificationController::class, 'createTemplate']);
        Route::put('/templates/{template}', [NotificationController::class, 'updateTemplate']);
        Route::delete('/templates/{template}', [NotificationController::class, 'deleteTemplate']);
        Route::get('/logs', [NotificationController::class, 'getLogs']);
    });

    // Settings
    Route::prefix('settings')->group(function () {
        Route::get('/general', [SettingsController::class, 'getGeneral']);
        Route::put('/general', [SettingsController::class, 'updateGeneral']);
        Route::get('/email', [SettingsController::class, 'getEmail']);
        Route::put('/email', [SettingsController::class, 'updateEmail']);
        Route::get('/sms', [SettingsController::class, 'getSms']);
        Route::put('/sms', [SettingsController::class, 'updateSms']);
        Route::get('/taxes', [SettingsController::class, 'getTaxes']);
        Route::put('/taxes', [SettingsController::class, 'updateTaxes']);
        Route::get('/currencies', [SettingsController::class, 'getCurrencies']);
        Route::put('/currencies', [SettingsController::class, 'updateCurrencies']);
        Route::get('/roles', [SettingsController::class, 'getRoles']);
        Route::post('/roles', [SettingsController::class, 'createRole']);
        Route::put('/roles/{role}', [SettingsController::class, 'updateRole']);
        Route::get('/activity-logs', [SettingsController::class, 'getActivityLogs']);
    });

    // System Management
    Route::prefix('system')->group(function () {
        Route::get('/health', [SettingsController::class, 'systemHealth']);
        Route::post('/cache/clear', [SettingsController::class, 'clearCache']);
        Route::post('/optimize', [SettingsController::class, 'optimize']);
        Route::get('/backup', [SettingsController::class, 'getBackups']);
        Route::post('/backup/create', [SettingsController::class, 'createBackup']);
        Route::post('/backup/restore', [SettingsController::class, 'restoreBackup']);
        Route::get('/logs', [SettingsController::class, 'getSystemLogs']);
        Route::get('/queue-status', [SettingsController::class, 'getQueueStatus']);
    });
});