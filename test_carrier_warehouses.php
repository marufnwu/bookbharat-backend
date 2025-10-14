<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Carrier Registered Warehouses API\n";
echo str_repeat("=", 50) . "\n\n";

try {
    // Test Delhivery
    $carrier = \App\Models\ShippingCarrier::where('code', 'DELHIVERY')->first();
    if ($carrier) {
        echo "✅ Found Delhivery carrier (ID: {$carrier->id})\n";

        $service = app(\App\Services\Shipping\MultiCarrierShippingService::class);
        $result = $service->getCarrierRegisteredPickupLocations($carrier);

        echo "📋 Registered locations found: " . count($result) . "\n";
        if (count($result) > 0) {
            echo "First location details:\n";
            echo json_encode($result[0], JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "❌ No registered locations found\n";
        }
    } else {
        echo "❌ Delhivery carrier not found\n";
    }

    echo "\n";

    // Test Ekart
    $carrier = \App\Models\ShippingCarrier::where('code', 'EKART')->first();
    if ($carrier) {
        echo "✅ Found Ekart carrier (ID: {$carrier->id})\n";

        $service = app(\App\Services\Shipping\MultiCarrierShippingService::class);
        $result = $service->getCarrierRegisteredPickupLocations($carrier);

        echo "📋 Registered locations found: " . count($result) . "\n";
        if (count($result) > 0) {
            echo "First location details:\n";
            echo json_encode($result[0], JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "❌ No registered locations found\n";
        }
    } else {
        echo "❌ Ekart carrier not found\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
