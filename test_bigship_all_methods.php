<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\Shipping\Carriers\CarrierFactory;
use App\Models\ShippingCarrier;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=============================================================\n";
echo "BIGSHIP CARRIER - COMPREHENSIVE METHOD TESTING\n";
echo "=============================================================\n\n";

try {
    // Check BigShip carrier configuration
    echo "=============================================================\n";
    echo "CHECKING BIGSHIP CONFIGURATION\n";
    echo "=============================================================\n";

    $carrierModel = ShippingCarrier::where('code', 'BIGSHIP')->first();

    if (!$carrierModel) {
        echo "✗ BigShip carrier not found in database!\n";
        echo "Please run: php artisan db:seed --class=ShippingCarrierSeeder\n";
        exit(1);
    }

    echo "✓ BigShip carrier found in database\n";
    echo "  ID: {$carrierModel->id}\n";
    echo "  Code: {$carrierModel->code}\n";
    echo "  Name: {$carrierModel->name}\n";
    echo "  Active: " . ($carrierModel->is_active ? 'Yes' : 'No') . "\n";
    echo "  Primary: " . ($carrierModel->is_primary ? 'Yes' : 'No') . "\n";
    echo "  API Endpoint: {$carrierModel->api_endpoint}\n";
    echo "  API Mode: {$carrierModel->api_mode}\n";

    // Check config structure
    $config = $carrierModel->config;
    if (is_string($config)) {
        $config = json_decode($config, true);
    }

    echo "\nCredentials Status:\n";
    $hasUsername = !empty($config['credentials']['username'] ?? null) || !empty($carrierModel->api_key);
    $hasPassword = !empty($config['credentials']['password'] ?? null) || !empty($carrierModel->api_secret);
    $hasAccessKey = !empty($config['credentials']['access_key'] ?? null);

    echo "  Username: " . ($hasUsername ? '✓ Set' : '✗ Not set') . "\n";
    echo "  Password: " . ($hasPassword ? '✓ Set' : '✗ Not set') . "\n";
    echo "  Access Key: " . ($hasAccessKey ? '✓ Set' : '✗ Not set') . "\n";

    if (!$hasUsername || !$hasPassword || !$hasAccessKey) {
        echo "\n⚠️ WARNING: BigShip credentials are not fully configured!\n";
        echo "\nTo configure BigShip credentials, you can either:\n\n";
        echo "1. Set environment variables in .env:\n";
        echo "   BIGSHIP_USERNAME=your_username\n";
        echo "   BIGSHIP_PASSWORD=your_password\n";
        echo "   BIGSHIP_ACCESS_KEY=your_access_key\n\n";
        echo "2. Or use the admin panel to configure credentials\n\n";
        echo "For testing purposes, continuing with available configuration...\n\n";
    }

    echo "\n";

    // Create BigShip adapter
    $factory = new CarrierFactory();
    $adapter = $factory->make($carrierModel);

    echo "✓ BigShip adapter created successfully\n\n";

    // Test data
    $testPincodes = [
        'pickup' => '110001',    // Delhi
        'delivery' => '400001'   // Mumbai
    ];

    $testShipmentData = [
        'pickup_pincode' => $testPincodes['pickup'],
        'delivery_pincode' => $testPincodes['delivery'],
        'payment_mode' => 'prepaid',
        'weight' => 1,
        'length' => 15,
        'width' => 10,
        'height' => 8,
        'invoice_amount' => 500,
        'shipment_category' => 'b2c'
    ];

    $testCreateShipmentData = [
        'order_id' => 'TEST_' . time(),
        'payment_mode' => 'prepaid',
        'service_type' => 'b2c',
        'pickup_address' => [
            'name' => 'Test Sender',
            'phone' => '9876543210',
            'address_1' => 'Test Address Line 1',
            'address_2' => 'Test Address Line 2',
            'pincode' => $testPincodes['pickup'],
            'city' => 'Delhi',
            'state' => 'Delhi'
        ],
        'delivery_address' => [
            'name' => 'Test Receiver',
            'phone' => '9876543211',
            'address_1' => 'Delivery Address Line 1',
            'address_2' => 'Delivery Address Line 2',
            'pincode' => $testPincodes['delivery'],
            'city' => 'Mumbai',
            'state' => 'Maharashtra'
        ],
        'package_details' => [
            'weight' => 1,
            'length' => 15,
            'width' => 10,
            'height' => 8,
            'quantity' => 1,
            'value' => 500
        ],
        'cod_amount' => 0
    ];

    // Test 1: Validate Credentials
    echo "=============================================================\n";
    echo "TEST 1: VALIDATE CREDENTIALS\n";
    echo "=============================================================\n";
    $credentialResult = $adapter->validateCredentials();
    echo "Status: " . ($credentialResult['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
    echo "Message: " . $credentialResult['message'] . "\n";
    if (isset($credentialResult['details'])) {
        echo "Details: " . json_encode($credentialResult['details'], JSON_PRETTY_PRINT) . "\n";
    }
    echo "\n";

    if (!$credentialResult['success']) {
        echo "⚠️ Authentication failed. This could be due to:\n";
        echo "   1. Invalid credentials\n";
        echo "   2. Credentials not configured\n";
        echo "   3. API endpoint not reachable\n";
        echo "   4. Account disabled or expired\n\n";
        echo "Continuing with remaining tests (they may fail)...\n\n";
    }

    // Test 2: Get Registered Warehouses
    echo "=============================================================\n";
    echo "TEST 2: GET REGISTERED WAREHOUSES\n";
    echo "=============================================================\n";

    try {
        $warehousesResult = $adapter->getRegisteredWarehouses();
        echo "Status: " . ($warehousesResult['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";

        if ($warehousesResult['success']) {
            echo "Total Warehouses: " . count($warehousesResult['warehouses']) . "\n";
            foreach ($warehousesResult['warehouses'] as $index => $warehouse) {
                echo "\nWarehouse " . ($index + 1) . ":\n";
                echo "  ID: " . ($warehouse['id'] ?? 'N/A') . "\n";
                echo "  Name: " . ($warehouse['name'] ?? 'N/A') . "\n";
                echo "  Address: " . ($warehouse['address'] ?? 'N/A') . "\n";
                echo "  Pincode: " . ($warehouse['pincode'] ?? 'N/A') . "\n";
                echo "  Phone: " . ($warehouse['phone'] ?? 'N/A') . "\n";
                echo "  Registered: " . ($warehouse['is_registered'] ? 'Yes' : 'No') . "\n";
            }

            // Store first warehouse ID for later tests
            $warehouseId = null;
            if (!empty($warehousesResult['warehouses'])) {
                $warehouseId = $warehousesResult['warehouses'][0]['id'] ?? null;
                echo "\n✓ Using Warehouse ID: {$warehouseId} for subsequent tests\n";
            }
        } else {
            echo "Message: " . ($warehousesResult['message'] ?? 'Unknown error') . "\n";
        }
    } catch (\Exception $e) {
        echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Test 3: Check Serviceability
    echo "=============================================================\n";
    echo "TEST 3: CHECK SERVICEABILITY\n";
    echo "=============================================================\n";
    echo "Testing serviceability:\n";
    echo "  Pickup Pincode: {$testPincodes['pickup']}\n";
    echo "  Delivery Pincode: {$testPincodes['delivery']}\n\n";

    try {
        $serviceabilityPrepaid = $adapter->checkServiceability(
            $testPincodes['pickup'],
            $testPincodes['delivery'],
            'prepaid'
        );
        echo "Prepaid: " . ($serviceabilityPrepaid ? "✓ SERVICEABLE" : "✗ NOT SERVICEABLE") . "\n";

        $serviceabilityCod = $adapter->checkServiceability(
            $testPincodes['pickup'],
            $testPincodes['delivery'],
            'cod'
        );
        echo "COD: " . ($serviceabilityCod ? "✓ SERVICEABLE" : "✗ NOT SERVICEABLE") . "\n";
    } catch (\Exception $e) {
        echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Test 4: Get Rates
    echo "=============================================================\n";
    echo "TEST 4: GET SHIPPING RATES\n";
    echo "=============================================================\n";
    echo "Fetching rates with shipment details:\n";
    echo "  Weight: {$testShipmentData['weight']} kg\n";
    echo "  Dimensions: {$testShipmentData['length']} x {$testShipmentData['width']} x {$testShipmentData['height']} cm\n";
    echo "  Invoice Amount: ₹{$testShipmentData['invoice_amount']}\n";
    echo "  Payment Mode: {$testShipmentData['payment_mode']}\n\n";

    try {
        $ratesResult = $adapter->getRates($testShipmentData);
        echo "Status: " . ($ratesResult['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";

        if ($ratesResult['success'] && !empty($ratesResult['rates'])) {
            echo "Total Rates Found: " . count($ratesResult['rates']) . "\n\n";

            foreach ($ratesResult['rates'] as $index => $rate) {
                echo "Rate " . ($index + 1) . ":\n";
                echo "  Service: " . ($rate['service_name'] ?? 'N/A') . "\n";
                echo "  Service Code: " . ($rate['service_code'] ?? 'N/A') . "\n";
                echo "  Courier ID: " . ($rate['courier_id'] ?? 'N/A') . "\n";
                echo "  Base Charge: ₹" . number_format($rate['base_charge'] ?? 0, 2) . "\n";
                echo "  COD Charge: ₹" . number_format($rate['cod_charge'] ?? 0, 2) . "\n";
                echo "  Total Charge: ₹" . number_format($rate['total_charge'] ?? 0, 2) . "\n";
                echo "  Delivery Days: " . ($rate['delivery_days'] ?? 'N/A') . " days\n";
                echo "  Estimated Delivery: " . ($rate['estimated_delivery_date'] ?? 'N/A') . "\n";
                echo "  Billable Weight: " . ($rate['billable_weight'] ?? 'N/A') . " kg\n";
                echo "  Zone: " . ($rate['zone'] ?? 'N/A') . "\n";
                echo "\n";
            }
        } else {
            echo "Message: " . ($ratesResult['message'] ?? 'No rates found') . "\n";
        }
    } catch (\Exception $e) {
        echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Test 5: Get Rates Async
    echo "=============================================================\n";
    echo "TEST 5: GET RATES ASYNC\n";
    echo "=============================================================\n";
    echo "Testing async rate fetching...\n";

    try {
        $promise = $adapter->getRateAsync($testShipmentData);
        $asyncRatesResult = $promise->wait();

        echo "Status: " . ($asyncRatesResult['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        if ($asyncRatesResult['success']) {
            echo "Async Rates Count: " . count($asyncRatesResult['rates']) . "\n";
        } else {
            echo "Message: " . ($asyncRatesResult['message'] ?? 'Unknown error') . "\n";
        }
    } catch (\Exception $e) {
        echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Test 6: Create Shipment (Optional - creates real shipment)
    echo "=============================================================\n";
    echo "TEST 6: CREATE SHIPMENT\n";
    echo "=============================================================\n";
    echo "⚠️ NOTE: This will create a REAL shipment in the system!\n";
    echo "Skipping by default to avoid test shipments.\n";
    echo "To enable: Set \$enableShipmentCreation = true in the script\n\n";

    $enableShipmentCreation = false; // Set to true to test shipment creation
    $testTrackingNumber = null;

    if ($enableShipmentCreation) {
        try {
            if (isset($warehouseId)) {
                $testCreateShipmentData['warehouse_id'] = $warehouseId;
            }

            $createResult = $adapter->createShipment($testCreateShipmentData);
            echo "Status: " . ($createResult['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";

            if ($createResult['success']) {
                echo "Tracking Number: " . ($createResult['tracking_number'] ?? 'N/A') . "\n";
                echo "Carrier Reference: " . ($createResult['carrier_reference'] ?? 'N/A') . "\n";
                echo "Pickup Date: " . ($createResult['pickup_date'] ?? 'N/A') . "\n";
                echo "Expected Delivery: " . ($createResult['expected_delivery'] ?? 'N/A') . "\n";

                // Store tracking number for later tests
                $testTrackingNumber = $createResult['tracking_number'] ?? null;
            } else {
                echo "Message: " . ($createResult['message'] ?? 'Unknown error') . "\n";
                if (isset($createResult['error'])) {
                    echo "Error: " . json_encode($createResult['error'], JSON_PRETTY_PRINT) . "\n";
                }
            }
        } catch (\Exception $e) {
            echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
        }
    }

    // Use a dummy tracking number for testing if no real shipment was created
    if (!$testTrackingNumber) {
        $testTrackingNumber = 'TEST123456789';
        echo "Using dummy tracking number for testing: {$testTrackingNumber}\n";
    }
    echo "\n";

    // Test 7: Track Shipment
    echo "=============================================================\n";
    echo "TEST 7: TRACK SHIPMENT\n";
    echo "=============================================================\n";
    echo "Tracking Number: {$testTrackingNumber}\n\n";

    try {
        $trackingResult = $adapter->trackShipment($testTrackingNumber);
        echo "Status: " . ($trackingResult['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";

        if ($trackingResult['success']) {
            echo "Tracking Number: " . ($trackingResult['tracking_number'] ?? 'N/A') . "\n";
            echo "Status: " . ($trackingResult['status'] ?? 'N/A') . "\n";
            echo "Status Description: " . ($trackingResult['status_description'] ?? 'N/A') . "\n";
            echo "Current Location: " . ($trackingResult['current_location'] ?? 'N/A') . "\n";

            if (!empty($trackingResult['events'])) {
                echo "\nTracking Events:\n";
                foreach ($trackingResult['events'] as $index => $event) {
                    echo "  " . ($index + 1) . ". " . ($event['date'] ?? 'N/A') . " - " . ($event['status'] ?? 'N/A') . "\n";
                    echo "     " . ($event['description'] ?? 'N/A') . "\n";
                    echo "     Location: " . ($event['location'] ?? 'N/A') . "\n";
                }
            }
        } else {
            echo "Message: " . ($trackingResult['message'] ?? 'Unknown error') . "\n";
        }
    } catch (\Exception $e) {
        echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Test 8: Print Label
    echo "=============================================================\n";
    echo "TEST 8: PRINT SHIPPING LABEL\n";
    echo "=============================================================\n";
    echo "Tracking Number: {$testTrackingNumber}\n\n";

    try {
        $labelContent = $adapter->printLabel($testTrackingNumber);

        if (!empty($labelContent)) {
            echo "✓ SUCCESS\n";
            echo "Label Content Length: " . strlen($labelContent) . " bytes\n";
            echo "Content Type: " . (strpos($labelContent, 'PDF') !== false ? 'PDF' : 'Unknown') . "\n";
        } else {
            echo "✗ FAILED or No label available\n";
        }
    } catch (\Exception $e) {
        echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Test 9: Schedule Pickup
    echo "=============================================================\n";
    echo "TEST 9: SCHEDULE PICKUP\n";
    echo "=============================================================\n";

    try {
        $pickupData = [
            'pickup_date' => date('Y-m-d', strtotime('+1 day')),
            'pickup_time' => '10:00',
            'tracking_numbers' => [$testTrackingNumber]
        ];

        $pickupResult = $adapter->schedulePickup($pickupData);
        echo "Status: " . ($pickupResult['success'] ? "✓ SUCCESS" : "✗ NOT SUPPORTED") . "\n";
        echo "Message: " . ($pickupResult['message'] ?? 'Unknown') . "\n";
    } catch (\Exception $e) {
        echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // Test 10: Cancel Shipment
    echo "=============================================================\n";
    echo "TEST 10: CANCEL SHIPMENT\n";
    echo "=============================================================\n";
    echo "⚠️ NOTE: This will cancel a shipment if it exists!\n";
    echo "Tracking Number: {$testTrackingNumber}\n\n";
    echo "Skipping cancellation to avoid canceling test shipments.\n";
    echo "To enable: Set \$enableCancellation = true in the script\n\n";

    $enableCancellation = false; // Set to true to test cancellation

    if ($enableCancellation && $testTrackingNumber !== 'TEST123456789') {
        try {
            $cancelResult = $adapter->cancelShipment($testTrackingNumber);

            if ($cancelResult) {
                echo "✓ SUCCESS - Shipment cancelled\n";
            } else {
                echo "✗ FAILED - Shipment could not be cancelled\n";
            }
        } catch (\Exception $e) {
            echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
        }
    }

    echo "\n";

    // Summary
    echo "=============================================================\n";
    echo "TEST SUMMARY\n";
    echo "=============================================================\n";
    echo "Total Methods Tested: 10\n\n";
    echo "1. validateCredentials()         : " . ($credentialResult['success'] ? '✓ PASSED' : '✗ FAILED') . "\n";
    echo "2. getRegisteredWarehouses()     : " . (isset($warehousesResult) && $warehousesResult['success'] ? '✓ PASSED' : '✗ FAILED') . "\n";
    echo "3. checkServiceability()         : " . (isset($serviceabilityPrepaid) || isset($serviceabilityCod) ? '✓ TESTED' : '✗ FAILED') . "\n";
    echo "4. getRates()                    : " . (isset($ratesResult) && $ratesResult['success'] ? '✓ PASSED' : '✗ FAILED') . "\n";
    echo "5. getRateAsync()                : " . (isset($asyncRatesResult) && $asyncRatesResult['success'] ? '✓ PASSED' : '✗ FAILED') . "\n";
    echo "6. createShipment()              : " . ($enableShipmentCreation ? '✓ TESTED' : '⚠ SKIPPED') . "\n";
    echo "7. trackShipment()               : " . (isset($trackingResult) ? '✓ TESTED' : '✗ FAILED') . "\n";
    echo "8. printLabel()                  : " . (isset($labelContent) ? '✓ TESTED' : '✗ FAILED') . "\n";
    echo "9. schedulePickup()              : " . (isset($pickupResult) ? '✓ TESTED' : '✗ FAILED') . " (Not supported - expected)\n";
    echo "10. cancelShipment()             : " . ($enableCancellation ? '✓ TESTED' : '⚠ SKIPPED') . "\n";
    echo "=============================================================\n";

    if (!$credentialResult['success']) {
        echo "\n⚠️ NOTE: Most tests failed due to invalid credentials.\n";
        echo "Please configure BigShip credentials to run full tests.\n";
    }

} catch (\Exception $e) {
    echo "✗ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n✓ Testing completed!\n";
