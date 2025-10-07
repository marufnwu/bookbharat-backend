<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
$cart = App\Models\Cart::where('user_id', $user->id)->first();

$service = new App\Services\CartService(
    new App\Services\CouponService(),
    new App\Services\ShippingService(),
    new App\Services\BundleDiscountService(),
    new App\Services\OrderChargeService(),
    new App\Services\TaxCalculationService()
);

$result = $service->calculateCart($cart);

echo "Tax Breakdown:\n";
echo json_encode($result['summary']['taxes_breakdown'], JSON_PRETTY_PRINT);
echo "\n\nTotal Tax: " . $result['summary']['tax_amount'];
echo "\n\nTotal: " . $result['summary']['total'];
