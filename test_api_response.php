<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== API Response Structure Analysis ===" . PHP_EOL . PHP_EOL;

$amount = 1000;
$paymentMethods = \App\Models\PaymentConfiguration::getEnabledMethods($amount, []);

// This is what the controller does
$methods = $paymentMethods->map(function ($method) {
    return [
        'id' => $method->id,
        'payment_method' => $method->payment_method,
        'display_name' => $method->display_name,
        'is_cod' => str_starts_with($method->payment_method, 'cod'),
    ];
});

echo "Methods collection type: " . get_class($methods) . PHP_EOL;
echo "Methods is array: " . (is_array($methods) ? 'YES' : 'NO') . PHP_EOL;
echo "Methods toArray() is array: " . (is_array($methods->toArray()) ? 'YES' : 'NO') . PHP_EOL;
echo "Methods values() is array: " . (is_array($methods->values()->toArray()) ? 'YES' : 'NO') . PHP_EOL;

echo PHP_EOL . "JSON without values():" . PHP_EOL;
echo json_encode(['payment_methods' => $methods], JSON_PRETTY_PRINT) . PHP_EOL;

echo PHP_EOL . "JSON with values():" . PHP_EOL;
echo json_encode(['payment_methods' => $methods->values()], JSON_PRETTY_PRINT) . PHP_EOL;
