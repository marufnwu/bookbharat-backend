# PayU Payment Data Flow - Debug Guide

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ STEP 1: User Frontend (Checkout Page)                          │
│ ─────────────────────────────────────────────────────────────── │
│ User submits order with payment_method='payu'                  │
│ → POST /api/v1/orders                                           │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ STEP 2: Backend OrderController                                │
│ ─────────────────────────────────────────────────────────────── │
│ $paymentResult = $this->paymentService->processPayment()       │
│                                                                 │
│ Returns:                                                        │
│ {                                                               │
│   'status' => 'pending',                                        │
│   'transaction_id' => 123,                                      │
│   'payment_method' => 'payu',                                   │
│   'message' => 'Payment initiated',                             │
│   'payment_data' => {  ← FULL GATEWAY RESPONSE                 │
│     'success' => true,                                          │
│     'gateway' => 'PayU',                                        │
│     'message' => 'Payment initiated successfully',              │
│     'data' => {  ← ACTUAL PAYU FIELDS                          │
│       'key' => 'gtKFFx',                                        │
│       'txnid' => 'ORD33T1760813226',                            │
│       'amount' => '517.22',                                     │
│       'productinfo' => 'Order #ORD-...',                        │
│       'firstname' => 'John',                                    │
│       'email' => 'john@example.com',                            │
│       'phone' => '1234567890',                                  │
│       'surl' => 'http://.../callback',                          │
│       'furl' => 'http://.../callback',                          │
│       'udf1' => '33',                                           │
│       'udf2' => 'ORD-...',                                      │
│       'udf3' => '4',                                            │
│       'udf4' => '',                                             │
│       'udf5' => '',                                             │
│       'hash' => '8ae70b5933e10f8347963dc...',                   │
│       'payment_id' => 123,  ← Meta field                       │
│       'payment_url' => 'https://test.payu.in/_payment',        │
│       'method' => 'POST'    ← Meta field                       │
│     },                                                          │
│     'timestamp' => '2025-...'  ← Meta field                     │
│   }                                                             │
│ }                                                               │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ STEP 3: OrderController Response Transformation                │
│ ─────────────────────────────────────────────────────────────── │
│ Line 242:                                                       │
│ $gatewayData = $paymentResult['payment_data']['data']          │
│                ?? $paymentResult['payment_data']               │
│                ?? [];                                           │
│                                                                 │
│ This extracts: data => { key, txnid, amount, ..., hash }       │
│                                                                 │
│ Line 253-254:                                                   │
│ 'payment_url' => $gatewayData['payment_url'],                  │
│ 'payment_data' => $gatewayData,                                │
│                                                                 │
│ Response to frontend:                                           │
│ {                                                               │
│   'success' => true,                                            │
│   'order' => {...},                                             │
│   'payment_details' => {                                        │
│     'payment_method' => 'payu',                                 │
│     'transaction_id' => 123,                                    │
│     'payment_url' => 'https://test.payu.in/_payment',          │
│     'payment_data' => {  ← FLAT PAYU FIELDS                    │
│       'key' => 'gtKFFx',                                        │
│       'txnid' => 'ORD33T1760813226',                            │
│       'amount' => '517.22',                                     │
│       // ... all PayU fields                                    │
│       'hash' => '...',                                          │
│       'payment_id' => 123,    ← Should skip                    │
│       'payment_url' => '...', ← Should skip                    │
│       'method' => 'POST'      ← Should skip                    │
│     },                                                          │
│     'message' => 'Payment initiated'                            │
│   },                                                            │
│   'requires_redirect' => true                                   │
│ }                                                               │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ STEP 4: Frontend handlePaymentRedirect (UPDATED)               │
│ ─────────────────────────────────────────────────────────────── │
│ Line 1162-1163:                                                 │
│ const paymentData = paymentDetails.payment_data                 │
│ const paymentUrl = paymentDetails.payment_url                   │
│                                                                 │
│ Line 1178: Skip meta fields:                                    │
│ const skipFields = [                                            │
│   'payment_url',  ← Meta field                                 │
│   'payment_id',   ← Meta field                                 │
│   'method',       ← Meta field                                 │
│   'success',      ← Should not be in paymentData               │
│   'message',      ← Should not be in paymentData               │
│   'data',         ← Should not be in paymentData               │
│   'gateway',      ← Should not be in paymentData (ADDED)       │
│   'timestamp'     ← Should not be in paymentData (ADDED)       │
│ ];                                                              │
│                                                                 │
│ Line 1181-1190: Create form fields                             │
│ Object.entries(paymentData).forEach(([key, value]) => {        │
│   if (!skipFields.includes(key)) {                             │
│     form.appendChild(input)  // key=value                      │
│   }                                                             │
│ });                                                             │
│                                                                 │
│ Result: Form with PayU fields:                                 │
│ - key                                                           │
│ - txnid                                                         │
│ - amount                                                        │
│ - productinfo                                                   │
│ - firstname                                                     │
│ - email                                                         │
│ - phone                                                         │
│ - surl                                                          │
│ - furl                                                          │
│ - udf1, udf2, udf3, udf4, udf5                                 │
│ - hash                                                          │
│ + any optional fields (lastname, address1, etc.)               │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ STEP 5: POST to PayU                                           │
│ ─────────────────────────────────────────────────────────────── │
│ Form submitted to: https://test.payu.in/_payment               │
│ PayU validates hash using same formula:                        │
│ sha512(key|txnid|amount|...|udf5||||||SALT)                    │
└─────────────────────────────────────────────────────────────────┘
```

## Potential Issues & Debugging

### Issue 1: `payment_data` Structure Mismatch

**Symptom:** Frontend logs show `payment_data` has wrong structure

**Check:**
```javascript
// In browser console during checkout:
console.log('Payment Details:', paymentDetails);
console.log('Payment Data:', paymentDetails.payment_data);
console.log('Payment Data Keys:', Object.keys(paymentDetails.payment_data));
```

**Expected Keys:**
- ✅ key, txnid, amount, productinfo, firstname, email, etc.
- ✅ hash
- ⚠️ payment_id, payment_url, method (will be skipped)

**Problem if you see:**
- ❌ success, gateway, message, data, timestamp at root level
- This means backend didn't extract `data` properly

**Fix:** Check `OrderController.php` line 242

### Issue 2: Hash Mismatch

**Symptom:** PayU shows "incorrectly calculated hash parameter"

**Backend Check:**
```bash
# View backend logs
tail -100 storage/logs/laravel.log | grep -A 10 "PayU Hash Data"
```

**Expected Log:**
```
PayU Hash Data:
  hash_data: {
    key: "gtKFFx",
    txnid: "ORD...",
    amount: "517.22",
    productinfo: "Order #...",
    firstname: "John",
    email: "john@example.com",
    udf1: "33",
    udf2: "ORD-...",
    udf3: "4",
    udf4: "",
    udf5: ""
  }
  generated_hash: "07e07b95214451e4ba314167096a630ab92d..."
```

**Frontend Check:**
```javascript
// In browser console, check submitted hash
console.log('Submitted Hash:', submittedFields.hash);
```

**Verify:**
1. Hash calculation uses ONLY the fields in `hash_data`
2. No extra fields like `phone`, `lastname`, `address1` in hash
3. Format: `key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||SALT`
4. Exactly 6 pipes (||||||) between udf5 and SALT

### Issue 3: Extra Fields Submitted to PayU

**Symptom:** PayU rejects request with validation error

**Frontend Check:**
```javascript
// In browser console
console.log('Submitted Fields:', submittedFields);
```

**Should NOT include:**
- ❌ success
- ❌ gateway
- ❌ message
- ❌ data
- ❌ timestamp
- ❌ payment_id (our internal ID)
- ❌ payment_url (duplicate)
- ❌ method

**Should include:**
- ✅ key, txnid, amount, productinfo
- ✅ firstname, email, phone
- ✅ surl, furl
- ✅ udf1, udf2, udf3, udf4, udf5
- ✅ hash
- ✅ Optional: lastname, address1, address2, city, state, country, zipcode

### Issue 4: Wrong Payment URL

**Symptom:** Form submits to wrong URL or 404

**Check:**
```javascript
console.log('Payment URL:', paymentUrl);
console.log('Form Action:', form.action);
```

**Expected:**
- Production: `https://secure.payu.in/_payment`
- Sandbox: `https://test.payu.in/_payment`

**Problem if:**
- ❌ URL is undefined or null
- ❌ URL points to local/wrong domain

## Testing Checklist

### Before Testing
- [ ] Clear browser cache and console
- [ ] Open browser DevTools (F12)
- [ ] Go to Console tab
- [ ] Set console to preserve log
- [ ] Open Network tab

### During Testing
1. Add product to cart
2. Go to checkout
3. Fill shipping information
4. Select PayU payment
5. Click "Place Order"
6. **WATCH CONSOLE** for logs:
   - `🔄 handlePaymentRedirect called with:`
   - `💳 Extracted payment data:`
   - `💳 PaymentData keys:`
   - `✅ Submitting PayU form with action:`
   - `📋 PayU form fields count:`
   - `📋 PayU submitted fields:`
   - `🔑 PayU hash being submitted:`

### After Form Submit
- Form should auto-submit to PayU
- Page should show PayU payment page
- **If you see error page instead:**
  - Screenshot the error
  - Copy console logs
  - Check backend logs

### Backend Logs to Check
```bash
# View recent PayU logs
tail -100 storage/logs/laravel.log | grep -i payu

# Watch logs in real-time
tail -f storage/logs/laravel.log | grep -i payu
```

Look for:
- `PayU Hash Data` - Shows hash calculation
- `PayU Payment Data` - Shows data sent to gateway
- `Payment initiated` - Confirms successful initiation

## Quick Fixes

### If payment_data has wrong structure
**Problem:** `payment_data` contains `success`, `gateway`, `message` at root

**Fix OrderController.php line 242:**
```php
// Current (correct):
$gatewayData = $paymentResult['payment_data']['data'] 
               ?? $paymentResult['payment_data'] 
               ?? [];

// If still wrong, check PaymentService.php line 34
// Should return the full gateway response
'payment_data' => $result
```

### If hash mismatch persists
**Problem:** Hash doesn't match PayU's calculation

**Fix PayuGateway.php:**
1. Verify `merchant_salt` (not `salt`)
2. Ensure only hash-relevant fields used
3. Confirm format: `key|txnid|...|udf5||||||SALT` (6 pipes)

### If extra fields in form
**Problem:** Meta fields being submitted

**Update frontend skipFields:**
```javascript
const skipFields = [
  'payment_url', 'payment_id', 'method',
  'success', 'message', 'data',
  'gateway', 'timestamp'  // ← Added
];
```

## Success Indicators

✅ **Console shows:**
- Correct payment URL
- 15-20 form fields (not 3-5)
- Hash is 128 characters long
- No meta fields in submitted data

✅ **PayU page shows:**
- Correct order amount
- Customer name and email pre-filled
- No hash error message

✅ **Backend logs show:**
- Hash calculation with correct format
- Payment initiated successfully
- Transaction ID generated

## Still Not Working?

1. Clear all caches:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```

2. Check PayU credentials in Admin UI

3. Compare backend hash with PayU's expected hash (from error message)

4. Share console logs and backend logs for analysis


