<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🎯 Final Ekart Fix Verification\n";
echo str_repeat("=", 40) . "\n\n";

$controller = app(\App\Http\Controllers\Api\WarehouseController::class);

foreach(['DELHIVERY', 'EKART'] as $code) {
    $carrier = \App\Models\ShippingCarrier::where('code', $code)->first();
    if ($carrier) {
        $response = $controller->getCarrierWarehouses($carrier->id);
        $data = json_decode($response->getContent(), true);

        echo $code . ":\n";
        echo "  Status: " . ($data['success'] ? '✅ Success' : '❌ Failed') . "\n";
        echo "  Locations: " . count($data['data'] ?? []) . "\n";

        if (count($data['data'] ?? []) > 0) {
            $first = $data['data'][0];
            echo "  Sample: {$first['name']} ({$first['carrier_warehouse_name']})\n";
            echo "  Registered: " . ($first['is_registered'] ? 'Yes' : 'No') . "\n";
        }
        echo "\n";
    } else {
        echo "$code: Carrier not found\n\n";
    }
}

echo "✅ Verification Complete!\n";
echo "Ekart should now show registered pickup locations in the admin UI.\n";
