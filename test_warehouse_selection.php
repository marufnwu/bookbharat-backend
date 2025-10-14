<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\Shipping\Carriers\CarrierFactory;
use App\Models\ShippingCarrier;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=============================================================\n";
echo "WAREHOUSE SELECTION LOGIC TEST\n";
echo "=============================================================\n\n";

// Test 1: Get BigShip registered warehouses
echo "TEST 1: Getting BigShip Registered Warehouses\n";
echo "=============================================================\n";

$bigship = ShippingCarrier::where('code', 'BIGSHIP')->first();
if ($bigship) {
    $factory = new CarrierFactory();
    $adapter = $factory->make($bigship);

    $warehouses = $adapter->getRegisteredWarehouses();

    echo "Success: " . ($warehouses['success'] ? 'Yes' : 'No') . "\n";
    echo "Total Warehouses: " . count($warehouses['warehouses'] ?? []) . "\n\n";

    if (!empty($warehouses['warehouses'])) {
        foreach ($warehouses['warehouses'] as $i => $wh) {
            echo ($i + 1) . ". " . ($wh['name'] ?? 'N/A') . "\n";
            echo "   ID: " . ($wh['id'] ?? $wh['warehouse_id'] ?? 'N/A') . "\n";
            echo "   Pincode: " . ($wh['pincode'] ?? 'N/A') . "\n\n";
        }
    }
} else {
    echo "BigShip carrier not found!\n";
}

// Test 2: Check if warehouse_id is passed through in shipment creation
echo "\n=============================================================\n";
echo "TEST 2: Warehouse ID Passthrough in Shipment Data\n";
echo "=============================================================\n";

// Simulate shipment data preparation
$testOrder = (object)[
    'order_number' => 'TEST123',
    'total_weight' => 1,
    'total_amount' => 500,
    'payment_method' => 'prepaid',
    'shipping_address' => [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '9876543210',
        'address_1' => 'Test Address',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'pincode' => '400001'
    ],
    'user' => (object)['email' => 'test@example.com'],
    'orderItems' => collect([])
];

echo "Simulating shipment data with warehouse_id = 192676\n\n";

// Check the actual implementation
$reflectionClass = new ReflectionClass('App\Services\Shipping\MultiCarrierShippingService');
$prepareShipmentDataMethod = $reflectionClass->getMethod('prepareShipmentData');
$prepareShipmentDataMethod->setAccessible(true);

$shippingService = app('App\Services\Shipping\MultiCarrierShippingService');

// Create a mock service object
$mockService = new stdClass();
$mockService->carrier_id = $bigship->id;
$mockService->service_code = 'STANDARD';
$mockService->carrier = $bigship;

$shipmentData = $prepareShipmentDataMethod->invoke(
    $shippingService,
    $testOrder,
    $mockService,
    ['warehouse_id' => '192676']
);

echo "Shipment Data Keys:\n";
foreach (array_keys($shipmentData) as $key) {
    echo "  - {$key}\n";
}

echo "\nwarehouse_id present in shipment data: " . (isset($shipmentData['warehouse_id']) ? '✓ YES' : '✗ NO') . "\n";
if (isset($shipmentData['warehouse_id'])) {
    echo "warehouse_id value: " . $shipmentData['warehouse_id'] . "\n";
}

// Test 3: Check carrier-specific warehouse endpoint
echo "\n\n=============================================================\n";
echo "TEST 3: Carrier Warehouse Endpoint Test\n";
echo "=============================================================\n";

try {
    $warehouseController = new \App\Http\Controllers\Api\WarehouseController();
    $response = $warehouseController->getCarrierWarehouses($bigship->id);
    $data = json_decode($response->getContent(), true);

    echo "API Response Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
    echo "Warehouses Count: " . count($data['data'] ?? []) . "\n\n";

    if (!empty($data['data'])) {
        echo "First warehouse:\n";
        $first = $data['data'][0];
        echo "  Name: " . ($first['name'] ?? 'N/A') . "\n";
        echo "  ID: " . ($first['id'] ?? 'N/A') . "\n";
        echo "  Pincode: " . ($first['pincode'] ?? 'N/A') . "\n";
        echo "  Registered: " . ($first['is_registered'] ? 'Yes' : 'No') . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Test 4: Check BigShip createShipment with warehouse_id
echo "\n\n=============================================================\n";
echo "TEST 4: BigShip Warehouse Selection in createShipment\n";
echo "=============================================================\n";

echo "Checking if BigShip adapter uses warehouse_id from shipment data...\n\n";

// Read BigshipAdapter source
$bigshipAdapterPath = app_path('Services/Shipping/Carriers/BigshipAdapter.php');
$bigshipCode = file_get_contents($bigshipAdapterPath);

// Check for warehouse_id usage
if (strpos($bigshipCode, "\$data['warehouse_id']") !== false) {
    echo "✓ BigShip adapter DOES check for \$data['warehouse_id']\n";
} else {
    echo "✗ BigShip adapter does NOT check for \$data['warehouse_id']\n";
}

if (strpos($bigshipCode, 'warehouse_detail') !== false) {
    echo "✓ BigShip adapter DOES include warehouse_detail in payload\n";
} else {
    echo "✗ BigShip adapter does NOT include warehouse_detail\n";
}

echo "\n=============================================================\n";
echo "SUMMARY\n";
echo "=============================================================\n\n";

echo "Issues Found:\n";
echo "1. warehouse_id passthrough: " . (isset($shipmentData['warehouse_id']) ? '✓ FIXED' : '✗ ISSUE EXISTS') . "\n";
echo "2. BigShip warehouse selection: Needs verification with actual shipment\n";
echo "3. Warehouse validation: Not implemented (carrier-agnostic)\n";
echo "4. Error handling: Improved logging added\n\n";

echo "Recommendations:\n";
echo "1. Test actual shipment creation with specific warehouse_id\n";
echo "2. Add warehouse validation in controller\n";
echo "3. Implement carrier-specific warehouse format handling\n";
echo "4. Add admin notification when warehouse selection fails\n";

echo "\n=============================================================\n";
echo "TEST COMPLETE\n";
echo "=============================================================\n";

