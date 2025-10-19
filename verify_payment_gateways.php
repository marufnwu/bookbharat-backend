<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PaymentMethod;
use App\Services\Payment\PaymentGatewayFactory;

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║         PAYMENT GATEWAY VERIFICATION REPORT                   ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

// Get all payment methods from database
$paymentMethods = PaymentMethod::all();

echo "📊 DATABASE STATUS:\n";
echo "─────────────────────────────────────────────────────────────────\n";

foreach ($paymentMethods as $method) {
    $status = $method->is_enabled ? '✅ ENABLED' : '❌ DISABLED';
    $hasCredentials = !empty($method->credentials) ? '🔑 YES' : '⚠️  NO';

    echo sprintf(
        "%-15s | Status: %-12s | Credentials: %-8s | Priority: %d\n",
        strtoupper($method->payment_method),
        $status,
        $hasCredentials,
        $method->priority
    );

    // Check required credentials
    if ($method->is_enabled && !empty($method->credentials)) {
        $credentials = $method->credentials;
        $missingCreds = [];

        switch ($method->payment_method) {
            case 'razorpay':
                if (empty($credentials['key_id'])) $missingCreds[] = 'key_id';
                if (empty($credentials['key_secret'])) $missingCreds[] = 'key_secret';
                break;
            case 'payu':
                if (empty($credentials['merchant_key'])) $missingCreds[] = 'merchant_key';
                if (empty($credentials['merchant_salt'])) $missingCreds[] = 'merchant_salt';
                break;
            case 'phonepe':
                if (empty($credentials['merchant_id'])) $missingCreds[] = 'merchant_id';
                if (empty($credentials['salt_key'])) $missingCreds[] = 'salt_key';
                if (empty($credentials['salt_index'])) $missingCreds[] = 'salt_index';
                break;
            case 'cashfree':
                if (empty($credentials['app_id'])) $missingCreds[] = 'app_id';
                if (empty($credentials['secret_key'])) $missingCreds[] = 'secret_key';
                break;
        }

        if (!empty($missingCreds)) {
            echo "   ⚠️  Missing credentials: " . implode(', ', $missingCreds) . "\n";
        }
    }
}

echo "\n";
echo "🔍 GATEWAY AVAILABILITY TEST:\n";
echo "─────────────────────────────────────────────────────────────────\n";

$availableCount = 0;
$unavailableCount = 0;

foreach ($paymentMethods as $method) {
    if (!$method->is_enabled) {
        continue;
    }

    try {
        $gateway = PaymentGatewayFactory::create($method->payment_method);
        $isAvailable = $gateway->isAvailable();

        if ($isAvailable) {
            echo "✅ " . strtoupper($method->payment_method) . " - AVAILABLE\n";
            echo "   Gateway Name: " . $gateway->getName() . "\n";
            echo "   Currencies: " . implode(', ', $gateway->getSupportedCurrencies()) . "\n";
            $availableCount++;
        } else {
            echo "❌ " . strtoupper($method->payment_method) . " - NOT AVAILABLE\n";
            echo "   Reason: Gateway exists but isAvailable() returned false\n";
            echo "   Action: Check credentials or configuration\n";
            $unavailableCount++;
        }

    } catch (\Exception $e) {
        echo "❌ " . strtoupper($method->payment_method) . " - ERROR\n";
        echo "   Error: " . $e->getMessage() . "\n";
        $unavailableCount++;
    }

    echo "\n";
}

echo "─────────────────────────────────────────────────────────────────\n";
echo "📈 SUMMARY:\n";
echo "   Total Methods: " . $paymentMethods->count() . "\n";
echo "   Enabled: " . $paymentMethods->where('is_enabled', true)->count() . "\n";
echo "   Available: " . $availableCount . "\n";
echo "   Unavailable: " . $unavailableCount . "\n";

echo "\n";
echo "🧪 PAYU SPECIFIC CHECKS:\n";
echo "─────────────────────────────────────────────────────────────────\n";

$payu = PaymentMethod::where('payment_method', 'payu')->first();

if ($payu) {
    echo "Database Status: " . ($payu->is_enabled ? '✅ ENABLED' : '❌ DISABLED') . "\n";

    $credentials = $payu->credentials ?? [];
    $hasMerchantKey = isset($credentials['merchant_key']) && !empty($credentials['merchant_key']);
    $hasMerchantSalt = isset($credentials['merchant_salt']) && !empty($credentials['merchant_salt']);

    echo "Merchant Key: " . ($hasMerchantKey ? '✅ SET' : '❌ MISSING') . "\n";
    echo "Merchant Salt: " . ($hasMerchantSalt ? '✅ SET' : '❌ MISSING') . "\n";

    if ($hasMerchantKey && $hasMerchantSalt) {
        try {
            $gateway = PaymentGatewayFactory::create('payu');
            $isAvailable = $gateway->isAvailable();

            echo "Gateway Available: " . ($isAvailable ? '✅ YES' : '❌ NO') . "\n";

            if ($isAvailable) {
                echo "\n";
                echo "✨ PayU is ready for testing! You can now:\n";
                echo "   1. Go to User Frontend\n";
                echo "   2. Add products to cart\n";
                echo "   3. Proceed to checkout\n";
                echo "   4. Select PayU as payment method\n";
                echo "   5. Complete a test transaction\n";
            } else {
                echo "\n";
                echo "⚠️  PayU gateway exists but is not available.\n";
                echo "   This usually means hasRequiredConfiguration() returned false.\n";
                echo "   Check if credentials are properly loaded.\n";
            }

        } catch (\Exception $e) {
            echo "Gateway Error: ❌ " . $e->getMessage() . "\n";
        }
    } else {
        echo "\n";
        echo "⚠️  PayU credentials are incomplete!\n";
        echo "   Action: Go to Admin UI → Settings → Payment Methods → Edit PayU\n";
        echo "   Add both Merchant Key and Merchant Salt\n";
    }
} else {
    echo "❌ PayU not found in database!\n";
    echo "   Action: Run payment method seeder\n";
}

echo "\n";
echo "💡 RECOMMENDATIONS:\n";
echo "─────────────────────────────────────────────────────────────────\n";

if ($unavailableCount > 0) {
    echo "⚠️  Some payment methods are unavailable:\n";
    echo "   - Check credentials in Admin UI → Settings → Payment Methods\n";
    echo "   - Verify credentials are correct (no typos)\n";
    echo "   - Clear cache: php artisan cache:clear\n";
} else {
    echo "✅ All enabled payment methods are available!\n";
    echo "   You can proceed with testing payments.\n";
}

echo "\n";
echo "📝 TESTING CHECKLIST:\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "□ Test PayU payment with test credentials\n";
echo "□ Test other online payment gateways\n";
echo "□ Test COD order placement\n";
echo "□ Verify order status updates after payment\n";
echo "□ Check payment analytics in Admin UI\n";
echo "□ Review transaction logs in Admin UI\n";

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║                    VERIFICATION COMPLETE                      ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";


