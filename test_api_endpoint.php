<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Carrier Warehouses API Endpoint\n";
echo str_repeat("=", 50) . "\n\n";

try {
    // Test the API endpoint directly
    $carrierId = 1; // Delhivery

    echo "Testing GET /api/v1/admin/shipping/multi-carrier/carriers/{$carrierId}/warehouses\n";

    // Create a mock request to simulate the API call
    $request = new \Illuminate\Http\Request();
    $request->setMethod('GET');

    // Get the controller and call the method
    $controller = app(\App\Http\Controllers\Api\WarehouseController::class);
    $response = $controller->getCarrierWarehouses($carrierId);

    echo "Response status: " . $response->getStatusCode() . "\n";

    if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getContent(), true);
        echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        echo "Carrier code: " . ($data['carrier_code'] ?? 'not set') . "\n";
        echo "Data count: " . count($data['data']) . "\n";

        if (count($data['data']) > 0) {
            echo "\nFirst warehouse:\n";
            echo json_encode($data['data'][0], JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        echo "Error response: " . $response->getContent() . "\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
