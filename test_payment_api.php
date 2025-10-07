<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Payment Gateway API Endpoint ===" . PHP_EOL . PHP_EOL;

// Simulate the API call
$amount = 1000;
$currency = 'INR';

$paymentMethods = \App\Models\PaymentConfiguration::getEnabledMethods($amount, []);

$methods = $paymentMethods->map(function ($method) {
    return [
        'id' => $method->id,
        'payment_method' => $method->payment_method,
        'display_name' => $method->display_name,
        'description' => $method->description,
        'priority' => $method->priority,
        'is_cod' => str_starts_with($method->payment_method, 'cod'),
        'is_online' => !str_starts_with($method->payment_method, 'cod'),
    ];
});

// Get payment flow settings
$paymentFlowType = \App\Models\AdminSetting::get('payment_flow_type', 'two_tier');
$defaultPaymentType = \App\Models\AdminSetting::get('payment_default_type', 'none');

// Check COD and online payment enabled
$codEnabled = \App\Models\PaymentConfiguration::whereIn('payment_method', ['cod', 'cod_with_advance', 'cod_percentage_advance'])
    ->where('is_enabled', true)
    ->exists();

$onlinePaymentEnabled = \App\Models\PaymentConfiguration::whereNotIn('payment_method', ['cod', 'cod_with_advance', 'cod_percentage_advance'])
    ->where('is_enabled', true)
    ->exists();

$response = [
    'success' => true,
    'payment_methods' => $methods->toArray(),
    'payment_flow' => [
        'type' => $paymentFlowType,
        'default_payment_type' => $defaultPaymentType,
        'cod_enabled' => $codEnabled,
        'online_payment_enabled' => $onlinePaymentEnabled,
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL;

echo PHP_EOL . "=== Summary ===" . PHP_EOL;
echo "Total methods returned: " . count($methods) . PHP_EOL;
echo "COD enabled: " . ($codEnabled ? 'YES' : 'NO') . PHP_EOL;
echo "Online enabled: " . ($onlinePaymentEnabled ? 'YES' : 'NO') . PHP_EOL;
