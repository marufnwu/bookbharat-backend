<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\Shipping\MultiCarrierShippingService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=============================================================\n";
echo "SHIPROCKET RATE COMPARISON TEST\n";
echo "=============================================================\n\n";

$service = app(MultiCarrierShippingService::class);

$result = $service->getRatesForComparison([
    'pickup_pincode' => '110001',
    'delivery_pincode' => '400001',
    'weight' => 1,
    'dimensions' => ['length' => 15, 'width' => 10, 'height' => 8],
    'order_value' => 500,
    'payment_mode' => 'prepaid',
    'items' => [['name' => 'Test', 'weight' => 1, 'quantity' => 1, 'value' => 500]],
    'force_refresh' => true
]);

echo "Total Carriers Checked: " . $result['metadata']['total_carriers_checked'] . "\n";
echo "Total Options Available: " . $result['metadata']['total_options_available'] . "\n\n";

$byCarrier = [];
foreach ($result['rates'] as $rate) {
    $code = $rate['carrier_code'];
    if (!isset($byCarrier[$code])) {
        $byCarrier[$code] = [];
    }
    $byCarrier[$code][] = $rate;
}

echo "Options by Carrier:\n";
foreach ($byCarrier as $code => $rates) {
    echo "  {$code}: " . count($rates) . " options\n";
}

echo "\n";

if (isset($byCarrier['SHIPROCKET'])) {
    echo "✅ SHIPROCKET IS SHOWING!\n";
    echo "Shiprocket Options: " . count($byCarrier['SHIPROCKET']) . "\n\n";

    echo "Top 3 Shiprocket Services:\n";
    foreach (array_slice($byCarrier['SHIPROCKET'], 0, 3) as $i => $rate) {
        echo "  " . ($i + 1) . ". {$rate['service_name']} - ₹{$rate['total_charge']} ({$rate['delivery_days']} days)\n";
    }
} else {
    echo "⚠️  SHIPROCKET NOT SHOWING\n";
    echo "Available carriers: " . implode(', ', array_keys($byCarrier)) . "\n";
}

echo "\n=============================================================\n";
echo "TEST COMPLETE\n";
echo "=============================================================\n";

