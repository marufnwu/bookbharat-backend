<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Auto-Selection Logic for Ekart\n";
echo str_repeat("=", 50) . "\n\n";

$controller = app(\App\Http\Controllers\Api\WarehouseController::class);

// Test Ekart carrier warehouses
$carrier = \App\Models\ShippingCarrier::where('code', 'EKART')->first();
if ($carrier) {
    $response = $controller->getCarrierWarehouses($carrier->id);
    $data = json_decode($response->getContent(), true);

    echo "Ekart Warehouses API Response:\n";
    echo "  Status: " . ($data['success'] ? '✅ Success' : '❌ Failed') . "\n";
    echo "  Count: " . count($data['data'] ?? []) . "\n";

    if (count($data['data'] ?? []) > 0) {
        $warehouses = $data['data'];

        // Simulate frontend auto-selection logic
        echo "\nAuto-Selection Logic Test:\n";

        // First priority: registered warehouses
        $registeredWarehouses = array_filter($warehouses, function($w) {
            return $w['is_registered'] ?? false;
        });

        if (count($registeredWarehouses) > 0) {
            $selected = reset($registeredWarehouses); // First registered warehouse
            echo "  ✅ Priority 1: Registered warehouse selected\n";
            echo "    Selected: {$selected['name']} (ID: {$selected['id']})\n";
            echo "    Is Registered: " . ($selected['is_registered'] ? 'Yes' : 'No') . "\n";
        } else {
            echo "  ❌ No registered warehouses found\n";
        }

        // Show all warehouses for reference
        echo "\nAll Available Warehouses:\n";
        foreach ($warehouses as $i => $wh) {
            echo "  " . ($i + 1) . ". {$wh['name']} (ID: {$wh['id']})\n";
            echo "     Registered: " . ($wh['is_registered'] ? 'Yes' : 'No') . "\n";
            echo "     Default: " . ($wh['is_default'] ?? 'N/A') . "\n";
        }
    }
} else {
    echo "❌ Ekart carrier not found\n";
}

echo "\n✅ Test Complete!\n";
