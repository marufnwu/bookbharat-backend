<?php

$response = file_get_contents('http://localhost:8000/api/v1/payment/gateways?amount=1000&currency=INR');
$data = json_decode($response, true);

echo "=== Gateway API Response Analysis ===" . PHP_EOL . PHP_EOL;

echo "Success: " . ($data['success'] ? 'YES' : 'NO') . PHP_EOL;
echo "Total gateways: " . count($data['gateways']) . PHP_EOL . PHP_EOL;

echo "=== Gateways List ===" . PHP_EOL;
foreach ($data['gateways'] as $gateway) {
    echo sprintf("- %s: %s (COD: %s)",
        $gateway['gateway'],
        $gateway['display_name'],
        $gateway['is_cod'] ? 'YES' : 'NO'
    ) . PHP_EOL;

    if ($gateway['is_cod'] && isset($gateway['advance_payment'])) {
        echo "  Advance Payment: " . json_encode($gateway['advance_payment']) . PHP_EOL;
    }
}

echo PHP_EOL . "=== Payment Flow Settings ===" . PHP_EOL;
echo json_encode($data['payment_flow'], JSON_PRETTY_PRINT) . PHP_EOL;

echo PHP_EOL . "=== COD Gateways ===" . PHP_EOL;
$codGateways = array_filter($data['gateways'], fn($g) => $g['is_cod']);
echo "Found " . count($codGateways) . " COD gateway(s)" . PHP_EOL;
foreach ($codGateways as $cod) {
    echo "  - " . $cod['display_name'] . PHP_EOL;
    echo "    Gateway field: " . $cod['gateway'] . PHP_EOL;
    echo "    Has advance_payment: " . (isset($cod['advance_payment']) ? 'YES' : 'NO') . PHP_EOL;
    echo "    Has service_charges: " . (isset($cod['service_charges']) ? 'YES' : 'NO') . PHP_EOL;
}

echo PHP_EOL . "=== Online Gateways ===" . PHP_EOL;
$onlineGateways = array_filter($data['gateways'], fn($g) => !$g['is_cod']);
echo "Found " . count($onlineGateways) . " online gateway(s)" . PHP_EOL;
foreach ($onlineGateways as $online) {
    echo "  - " . $online['display_name'] . PHP_EOL;
}
