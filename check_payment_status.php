<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Current Payment Configuration Status ===" . PHP_EOL . PHP_EOL;

$configs = \App\Models\PaymentConfiguration::select('id', 'payment_method', 'display_name', 'is_enabled')
    ->orderBy('priority', 'desc')
    ->get();

foreach($configs as $config) {
    $enabled = $config->is_enabled ? 'YES' : 'NO ';
    echo sprintf('ID: %d | %-35s | Enabled: %s', $config->id, $config->display_name, $enabled) . PHP_EOL;
}

echo PHP_EOL . "=== Admin Settings ===" . PHP_EOL;
echo 'payment_flow_type: ' . \App\Models\AdminSetting::get('payment_flow_type', 'two_tier') . PHP_EOL;
echo 'payment_default_type: ' . \App\Models\AdminSetting::get('payment_default_type', 'none') . PHP_EOL;

echo PHP_EOL . "=== COD Status Check ===" . PHP_EOL;
$codEnabled = \App\Models\PaymentConfiguration::whereIn('payment_method', ['cod', 'cod_with_advance', 'cod_percentage_advance'])
    ->where('is_enabled', true)
    ->exists();
echo 'Any COD method enabled: ' . ($codEnabled ? 'YES' : 'NO') . PHP_EOL;

$onlineEnabled = \App\Models\PaymentConfiguration::whereNotIn('payment_method', ['cod', 'cod_with_advance', 'cod_percentage_advance'])
    ->where('is_enabled', true)
    ->exists();
echo 'Any Online method enabled: ' . ($onlineEnabled ? 'YES' : 'NO') . PHP_EOL;
