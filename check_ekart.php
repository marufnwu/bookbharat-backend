<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== EKART CARRIER CHECK ===\n\n";

$ekart = \App\Models\ShippingCarrier::where('code', 'EKART')->first();

if ($ekart) {
    echo "✅ Ekart found in database\n";
    echo "ID: {$ekart->id}\n";
    echo "Name: {$ekart->name}\n";
    echo "Code: {$ekart->code}\n";
    echo "Active: " . ($ekart->is_active ? 'Yes' : 'No') . "\n";
    echo "API Mode: {$ekart->api_mode}\n";
    echo "Supported modes: " . json_encode($ekart->supported_payment_modes) . "\n";
    echo "Credentials: " . json_encode($ekart->config['credential_fields'] ?? []) . "\n";
} else {
    echo "❌ Ekart not found in database\n";
}

