<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\Shipping\MultiCarrierShippingService;
use App\Services\Shipping\Carriers\CarrierFactory;
use App\Models\Order;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=============================================================\n";
echo "TESTING ADMIN PANEL BIGSHIP RATES\n";
echo "=============================================================\n\n";

// Create service
$carrierFactory = new CarrierFactory();
$shippingService = new MultiCarrierShippingService($carrierFactory);

// Simulate admin panel request for order 27 (or a test order)
$testParams = [
    'pickup_pincode' => '700009',     // Kolkata (where warehouse is)
    'delivery_pincode' => '400001',   // Mumbai
    'weight' => 1,
    'dimensions' => [
        'length' => 15,
        'width' => 10,
        'height' => 8
    ],
    'order_value' => 500,
    'payment_mode' => 'prepaid',
    'cod_amount' => 0,
    'items' => [
        [
            'name' => 'Test Book',
            'weight' => 1,
            'quantity' => 1,
            'value' => 500
        ]
    ],
    'force_refresh' => true // Force refresh to bypass cache
];

echo "Request Parameters:\n";
echo "  Pickup Pincode: {$testParams['pickup_pincode']}\n";
echo "  Delivery Pincode: {$testParams['delivery_pincode']}\n";
echo "  Weight: {$testParams['weight']} kg\n";
echo "  Dimensions: {$testParams['dimensions']['length']} x {$testParams['dimensions']['width']} x {$testParams['dimensions']['height']} cm\n";
echo "  Payment Mode: {$testParams['payment_mode']}\n";
echo "  Order Value: ₹{$testParams['order_value']}\n\n";

try {
    echo "Calling getRatesForComparison()...\n\n";
    $result = $shippingService->getRatesForComparison($testParams);

    echo "=============================================================\n";
    echo "RESULT\n";
    echo "=============================================================\n\n";

    echo "Total Carriers Checked: " . $result['metadata']['total_carriers_checked'] . "\n";
    echo "Total Options Available: " . $result['metadata']['total_options_available'] . "\n\n";

    if (!empty($result['rates'])) {
        echo "Rates Found:\n\n";

        foreach ($result['rates'] as $index => $rate) {
            echo ($index + 1) . ". " . ($rate['carrier_name'] ?? 'Unknown Carrier') . "\n";
            echo "   Service: " . ($rate['service_name'] ?? 'Unknown Service') . "\n";
            echo "   Total Cost: ₹" . number_format($rate['total_charge'] ?? $rate['total_cost'] ?? 0, 2) . "\n";
            echo "   Delivery Days: " . ($rate['delivery_days'] ?? 'N/A') . " days\n";
            echo "   Carrier Code: " . ($rate['carrier_code'] ?? 'N/A') . "\n";
            echo "\n";
        }

        // Check if BigShip is in the results
        $bigshipRates = array_filter($result['rates'], function($rate) {
            return strtolower($rate['carrier_code'] ?? '') === 'bigship';
        });

        if (empty($bigshipRates)) {
            echo "⚠️  WARNING: BigShip rates NOT found in results!\n\n";

            // Show all carriers that were found
            echo "Carriers that provided rates:\n";
            $carriers = array_unique(array_map(function($r) { return $r['carrier_code'] ?? 'unknown'; }, $result['rates']));
            foreach ($carriers as $carrier) {
                echo "  - {$carrier}\n";
            }
        } else {
            echo "✓ BigShip rates FOUND in results! (" . count($bigshipRates) . " options)\n";
        }
    } else {
        echo "✗ NO RATES FOUND\n";
    }

    // Show recommended option
    if (!empty($result['recommended'])) {
        echo "\nRecommended Option:\n";
        echo "  Carrier: " . ($result['recommended']['carrier_name'] ?? 'N/A') . "\n";
        echo "  Service: " . ($result['recommended']['service_name'] ?? 'N/A') . "\n";
        echo "  Cost: ₹" . number_format($result['recommended']['total_charge'] ?? $result['recommended']['total_cost'] ?? 0, 2) . "\n";
    }

    // Debug: Show full first rate structure
    echo "\n\nDEBUG - First Rate Structure:\n";
    echo json_encode($result['rates'][0] ?? [], JSON_PRETTY_PRINT) . "\n";

} catch (\Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=============================================================\n";
echo "TEST COMPLETE\n";
echo "=============================================================\n";

