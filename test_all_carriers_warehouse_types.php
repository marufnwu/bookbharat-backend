<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\Shipping\Carriers\CarrierFactory;
use App\Models\ShippingCarrier;
use App\Http\Controllers\Api\WarehouseController;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=============================================================\n";
echo "ALL CARRIERS - WAREHOUSE REQUIREMENT TYPE TEST\n";
echo "=============================================================\n\n";

$factory = new CarrierFactory();
$carriers = ShippingCarrier::where('is_active', true)->get();

echo "Testing " . $carriers->count() . " active carriers\n\n";

$results = [
    'registered_id' => [],
    'registered_alias' => [],
    'full_address' => []
];

foreach ($carriers as $carrier) {
    try {
        $adapter = $factory->make($carrier);
        $requirementType = $adapter->getWarehouseRequirementType();

        echo "{$carrier->code}";
        echo str_repeat('.', 20 - strlen($carrier->code));
        echo " {$requirementType}\n";

        $results[$requirementType][] = $carrier->code;

    } catch (\Exception $e) {
        echo "{$carrier->code}";
        echo str_repeat('.', 20 - strlen($carrier->code));
        echo " ERROR: {$e->getMessage()}\n";
    }
}

echo "\n=============================================================\n";
echo "SUMMARY BY TYPE\n";
echo "=============================================================\n\n";

echo "registered_id (Needs pre-registered numeric IDs):\n";
foreach ($results['registered_id'] as $code) {
    echo "  ✓ {$code}\n";
}
echo "\n";

echo "registered_alias (Needs pre-registered aliases/names):\n";
foreach ($results['registered_alias'] as $code) {
    echo "  ✓ {$code}\n";
}
echo "\n";

echo "full_address (Accepts full address from database):\n";
foreach ($results['full_address'] as $code) {
    echo "  ✓ {$code}\n";
}
echo "\n";

// Test 2: Warehouse Controller API Response
echo "=============================================================\n";
echo "TEST API ENDPOINTS\n";
echo "=============================================================\n\n";

$warehouseController = new WarehouseController();

// Test BigShip (registered_id type)
echo "1. Testing BigShip (registered_id type):\n";
echo "   GET /api/admin/shipping/carriers/{bigship_id}/warehouses\n\n";

$bigship = ShippingCarrier::where('code', 'BIGSHIP')->first();
if ($bigship) {
    $response = $warehouseController->getCarrierWarehouses($bigship->id);
    $data = json_decode($response->getContent(), true);

    echo "   Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
    echo "   Requirement Type: " . ($data['requirement_type'] ?? 'N/A') . "\n";
    echo "   Source: " . ($data['source'] ?? 'N/A') . "\n";
    echo "   Warehouses Count: " . count($data['data'] ?? []) . "\n";
    if (!empty($data['data'])) {
        $first = $data['data'][0];
        echo "   First Warehouse: " . ($first['name'] ?? 'N/A') . " (ID: " . ($first['id'] ?? 'N/A') . ")\n";
    }
    echo "\n";
}

// Test Xpressbees (full_address type)
echo "2. Testing Xpressbees (full_address type):\n";
echo "   GET /api/admin/shipping/carriers/{xpressbees_id}/warehouses\n\n";

$xpressbees = ShippingCarrier::where('code', 'XPRESSBEES')->first();
if ($xpressbees) {
    $response = $warehouseController->getCarrierWarehouses($xpressbees->id);
    $data = json_decode($response->getContent(), true);

    echo "   Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
    echo "   Requirement Type: " . ($data['requirement_type'] ?? 'N/A') . "\n";
    echo "   Source: " . ($data['source'] ?? 'N/A') . "\n";
    echo "   Warehouses Count: " . count($data['data'] ?? []) . "\n";
    if (!empty($data['data'])) {
        $first = $data['data'][0];
        echo "   First Warehouse: " . ($first['name'] ?? 'N/A') . " (ID: " . ($first['id'] ?? 'N/A') . ")\n";
    }
    echo "\n";
}

// Test Ekart (registered_alias type)
echo "3. Testing Ekart (registered_alias type):\n";
echo "   GET /api/admin/shipping/carriers/{ekart_id}/warehouses\n\n";

$ekart = ShippingCarrier::where('code', 'EKART')->first();
if ($ekart) {
    $response = $warehouseController->getCarrierWarehouses($ekart->id);
    $data = json_decode($response->getContent(), true);

    echo "   Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
    echo "   Requirement Type: " . ($data['requirement_type'] ?? 'N/A') . "\n";
    echo "   Source: " . ($data['source'] ?? 'N/A') . "\n";
    echo "   Warehouses Count: " . count($data['data'] ?? []) . "\n";
    if (!empty($data['data'])) {
        $first = $data['data'][0];
        echo "   First Warehouse: " . ($first['name'] ?? 'N/A') . " (ID: " . ($first['id'] ?? 'N/A') . ")\n";
    }
    echo "\n";
}

echo "=============================================================\n";
echo "ADMIN PANEL BEHAVIOR\n";
echo "=============================================================\n\n";

echo "For carriers requiring registered IDs/aliases:\n";
echo "  - Admin panel will show warehouses from carrier's API\n";
echo "  - User selects from pre-registered list\n";
echo "  - Selected ID/alias is sent directly to carrier\n";
echo "  - Examples: BigShip (ID), Ekart (alias), Delhivery (alias)\n\n";

echo "For carriers accepting full addresses:\n";
echo "  - Admin panel will show site warehouses from database\n";
echo "  - User selects local warehouse\n";
echo "  - Full address is extracted and sent to carrier\n";
echo "  - Examples: Xpressbees, DTDC, BlueDart, Shadowfax\n\n";

echo "=============================================================\n";
echo "WAREHOUSE SELECTION IN SHIPMENT CREATION\n";
echo "=============================================================\n\n";

echo "The warehouse_id field behavior:\n";
echo "  - BigShip: warehouse_id=192676 → pickup_location_id: 192676\n";
echo "  - Ekart: warehouse_id='alias' → uses registered alias\n";
echo "  - Xpressbees: warehouse_id=1 → fetches Warehouse#1 full address\n";
echo "  - DTDC: warehouse_id=1 → fetches Warehouse#1 full address\n\n";

echo "=============================================================\n";
echo "TEST COMPLETE\n";
echo "=============================================================\n";

