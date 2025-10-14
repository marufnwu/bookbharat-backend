<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Carrier-Specific Field Mapping Refactor\n";
echo str_repeat("=", 50) . "\n\n";

$service = app(\App\Services\Shipping\MultiCarrierShippingService::class);
$controller = app(\App\Http\Controllers\Api\WarehouseController::class);

foreach(['DELHIVERY', 'EKART'] as $code) {
    echo "Testing $code:\n";

    $carrier = \App\Models\ShippingCarrier::where('code', $code)->first();
    if (!$carrier) {
        echo "  ❌ Carrier not found\n\n";
        continue;
    }

    // Test API endpoint
    $response = $controller->getCarrierWarehouses($carrier->id);
    $data = json_decode($response->getContent(), true);

    echo "  API Status: " . ($data['success'] ? '✅' : '❌') . "\n";
    echo "  Locations: " . count($data['data'] ?? []) . "\n";

    if (count($data['data'] ?? []) > 0) {
        $first = $data['data'][0];
        echo "  Sample: {$first['name']} (ID: {$first['id']})\n";
        echo "  Registered: " . ($first['is_registered'] ? 'Yes' : 'No') . "\n";

        // Verify field mapping
        $required = ['id', 'name', 'carrier_warehouse_name', 'address', 'city', 'pincode', 'phone', 'is_enabled', 'is_registered'];
        $missing = array_diff($required, array_keys($first));
        if (empty($missing)) {
            echo "  Field mapping: ✅ Complete\n";
        } else {
            echo "  Field mapping: ❌ Missing: " . implode(', ', $missing) . "\n";
        }
    }

    echo "\n";
}

echo "✅ Refactor Testing Complete!\n";
