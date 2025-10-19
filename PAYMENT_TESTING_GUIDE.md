# Payment Gateway Testing Guide

## Recent Fixes Applied

### 1. PayU Gateway Availability Fix
**Issue:** PayU was showing as "not available" even when enabled
**Root Cause:** `BasePaymentGateway` was only loading configuration, not credentials
**Fix Applied:** Merged both credentials and configuration in `loadConfiguration()` method

### 2. PayU Hash Calculation Fix
**Issue:** Hash mismatch error - "Transaction failed due to incorrectly calculated hash parameter"
**Root Cause:** Extra fields in data array were contaminating hash calculation
**Fix Applied:** Separated hash-relevant fields from payment data fields

## Testing Steps

### Pre-Testing Checklist

1. **Verify PayU is enabled in database:**
   ```bash
   cd bookbharat-backend
   php artisan tinker --execute="echo \App\Models\PaymentMethod::where('payment_method', 'payu')->value('is_enabled') ? 'ENABLED' : 'DISABLED';"
   ```

2. **Verify PayU credentials are set:**
   - Go to Admin UI → Settings → Payment Methods
   - Check that PayU has Merchant Key and Merchant Salt configured

3. **Clear all caches:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   ```

### Test Case 1: PayU Gateway Availability

**Steps:**
1. Open User Frontend → Add products to cart
2. Go to Checkout page
3. Navigate to Payment step

**Expected Results:**
- ✅ PayU should appear in the available payment methods list
- ✅ PayU should have proper display name "Pay Online (PayU)"
- ✅ PayU logo/icon should be visible

**If PayU is NOT showing:**
```bash
# Check gateway availability
cd bookbharat-backend
php artisan tinker --execute="
\$gateway = \App\Services\Payment\PaymentGatewayFactory::create('payu');
echo 'Gateway Name: ' . \$gateway->getName() . PHP_EOL;
echo 'Is Available: ' . (\$gateway->isAvailable() ? 'YES' : 'NO') . PHP_EOL;
"
```

### Test Case 2: PayU Payment Initiation

**Steps:**
1. Select PayU as payment method
2. Click "Place Order"
3. Observe the redirect to PayU payment page

**Expected Results:**
- ✅ Order should be created with status "pending"
- ✅ Should redirect to PayU payment page (test.payu.in or secure.payu.in)
- ✅ Payment form should be pre-filled with order details
- ✅ No hash mismatch error should appear

**Check Backend Logs:**
```bash
# View recent PayU logs
tail -f storage/logs/laravel.log | grep -i payu
```

Look for these log entries:
- `PayU Hash Data` - Should show hash calculation details
- `PayU Payment Data` - Should show the data being sent to PayU

### Test Case 3: Hash Calculation Verification

**Manual Hash Check Script:**

Create a test file to verify hash calculation:

```bash
cd bookbharat-backend
cat > test_hash_verification.php << 'EOF'
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PaymentMethod;

// Get PayU credentials
$payu = PaymentMethod::where('payment_method', 'payu')->first();
$credentials = $payu->credentials ?? [];

$key = $credentials['merchant_key'] ?? '';
$salt = $credentials['merchant_salt'] ?? '';

// Test data
$testData = [
    'key' => $key,
    'txnid' => 'TEST' . time(),
    'amount' => '100.00',
    'productinfo' => 'Test Order',
    'firstname' => 'John',
    'email' => 'test@example.com',
    'udf1' => '1',
    'udf2' => 'ORD-TEST',
    'udf3' => '1',
    'udf4' => '',
    'udf5' => '',
];

// Calculate hash
$hashString = $testData['key'] . '|' .
              $testData['txnid'] . '|' .
              $testData['amount'] . '|' .
              $testData['productinfo'] . '|' .
              $testData['firstname'] . '|' .
              $testData['email'] . '|' .
              $testData['udf1'] . '|' .
              $testData['udf2'] . '|' .
              $testData['udf3'] . '|' .
              $testData['udf4'] . '|' .
              $testData['udf5'] . '||||||' .
              $salt;

$hash = hash('sha512', $hashString);

echo "Hash String Format:\n";
echo "key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||SALT\n\n";
echo "Actual Hash String:\n";
echo $hashString . "\n\n";
echo "Generated Hash:\n";
echo $hash . "\n";
EOF

php test_hash_verification.php
```

**Expected Results:**
- Hash string should have exactly 6 pipes between udf5 and SALT (`||||||`)
- Hash should be a 128-character SHA-512 string

### Test Case 4: Complete Payment Flow

**Steps:**
1. Place test order with PayU
2. On PayU test page, use test card credentials:
   - Card Number: 5123456789012346
   - CVV: 123
   - Expiry: Any future date
   - Name: Any name
3. Complete payment
4. Verify redirect back to success page

**Expected Results:**
- ✅ Payment status updated to "success" in database
- ✅ Order status updated to "processing" or "confirmed"
- ✅ Success page displays with order details
- ✅ Customer receives order confirmation email

**Check Database:**
```sql
-- Check payment record
SELECT * FROM payments 
WHERE payment_method = 'payu' 
ORDER BY created_at DESC 
LIMIT 1;

-- Check order status
SELECT id, order_number, payment_status, order_status, total_amount
FROM orders 
WHERE payment_method = 'payu' 
ORDER BY created_at DESC 
LIMIT 1;
```

### Test Case 5: Other Payment Gateways

**Important:** The fixes also affect all other gateways (Razorpay, PhonePe, Cashfree)

**Steps:**
1. Test Razorpay payment flow
2. Test PhonePe payment flow
3. Test Cashfree payment flow
4. Test COD order placement

**Expected Results:**
- ✅ All gateways should continue working normally
- ✅ No regression issues
- ✅ COD orders should place successfully

## Troubleshooting

### Issue: PayU still shows "not available"

**Check 1: Database status**
```bash
php artisan tinker --execute="
\$payu = \App\Models\PaymentMethod::where('payment_method', 'payu')->first();
echo 'Enabled: ' . (\$payu->is_enabled ? 'YES' : 'NO') . PHP_EOL;
echo 'Has Credentials: ' . (!empty(\$payu->credentials) ? 'YES' : 'NO') . PHP_EOL;
"
```

**Check 2: Gateway creation**
```bash
php artisan tinker --execute="
try {
    \$gateway = \App\Services\Payment\PaymentGatewayFactory::create('payu');
    echo 'Gateway created successfully' . PHP_EOL;
    echo 'Is Available: ' . (\$gateway->isAvailable() ? 'YES' : 'NO') . PHP_EOL;
} catch (\Exception \$e) {
    echo 'Error: ' . \$e->getMessage() . PHP_EOL;
}
"
```

**Fix:** Clear cache and check credentials:
```bash
php artisan cache:clear
php artisan config:clear
```

### Issue: Hash mismatch error still occurs

**Check:** View the hash calculation logs:
```bash
tail -100 storage/logs/laravel.log | grep -A 5 "PayU Hash Data"
```

**Verify:**
- Hash string format: `key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||SALT`
- Exactly 6 pipes between udf5 and SALT
- All fields are strings (no null values)

### Issue: Payment successful but order not updating

**Check webhook configuration:**
1. Go to PayU merchant dashboard
2. Verify webhook URL is set: `https://yourdomain.com/api/v1/payment/webhook/payu`
3. Check webhook logs in Admin UI → Payments → Webhook Logs

## Success Criteria

✅ **All tests pass if:**
1. PayU appears in available payment methods
2. Order creation succeeds without errors
3. PayU payment page loads with correct details
4. No hash mismatch errors
5. Test payment completes successfully
6. Order status updates correctly
7. Other gateways still work normally

## Additional Verification

**View all available payment methods:**
```bash
php artisan tinker --execute="
\$methods = \App\Services\Payment\PaymentGatewayFactory::getAvailableGateways();
foreach (\$methods as \$method) {
    echo \$method['gateway'] . ': ' . \$method['display_name'] . PHP_EOL;
}
"
```

**Check payment method priorities:**
```bash
php artisan tinker --execute="
\$methods = \App\Models\PaymentMethod::where('is_enabled', true)
    ->orderBy('priority', 'desc')
    ->get(['payment_method', 'display_name', 'priority', 'is_enabled']);
foreach (\$methods as \$method) {
    echo \$method->payment_method . ' - Priority: ' . \$method->priority . PHP_EOL;
}
"
```

## Cleanup

After testing, remove test scripts:
```bash
rm test_hash_verification.php
```

## Support

If issues persist:
1. Check `storage/logs/laravel.log` for detailed error messages
2. Enable debug mode temporarily: Set `APP_DEBUG=true` in `.env`
3. Check browser console for frontend errors
4. Verify PayU merchant credentials are correct

## Next Steps After Successful Testing

Once all payment tests pass:
- ✅ Mark Phase 4 (Checkout Refactoring) as next priority
- ✅ Test admin payment analytics dashboard
- ✅ Test transaction log viewer
- ✅ Verify refund management features


