# Cart & Checkout Calculation Issues

## Critical Issues Found

### 1. **HARDCODED SHIPPING CALCULATION** ⚠️ CRITICAL
**Location:** `app/Services/CartService.php:262`

```php
$shippingCost = ($discountedSubtotal > 500 || $couponFreeShipping) ? 0 : 50;
```

**Problems:**
- ❌ Uses hardcoded ₹500 threshold instead of zone-based thresholds from database
- ❌ Doesn't use `ShippingService` for actual calculation
- ❌ Ignores `free_shipping_enabled` flag from `shipping_zones` table
- ❌ No zone calculation based on delivery pincode
- ❌ No weight-based shipping calculation
- ❌ Flat ₹50 rate instead of actual zone rates
- ❌ NOT using the proper ShippingService that has all the correct logic

**Impact:**
- Users see incorrect shipping costs in cart
- Free shipping applied incorrectly (doesn't respect admin settings)
- No zone-based pricing (Zone A-E differences ignored)

---

### 2. **MISSING DELIVERY PINCODE IN CART SUMMARY** ⚠️ CRITICAL
**Location:** `app/Services/CartService.php:196-288`

**Problem:**
- Cart summary doesn't accept delivery pincode parameter
- Cannot calculate proper zone-based shipping without pincode
- Hardcoded logic used as fallback

**Required Fix:**
```php
public function getCartSummary($userId = null, $sessionId = null, $deliveryPincode = null, $pickupPincode = null)
```

---

### 3. **SHIPPING COST CAN BE MANIPULATED** ⚠️ SECURITY ISSUE
**Location:** `app/Http/Controllers/Api/OrderController.php:138`

```php
$shippingAmount = $request->shipping_cost ?? $cartSummary['shipping_cost'];
```

**Problem:**
- ❌ Accepts `shipping_cost` from client request
- ❌ User can send any value and pay less shipping
- ❌ Should ALWAYS calculate server-side, never trust client

**Impact:**
- Users can manipulate checkout to pay ₹0 shipping
- Revenue loss from incorrect shipping charges

---

### 4. **DUPLICATE SHIPPING CALCULATION LOGIC** ⚠️ INCONSISTENCY

**Locations:**
1. `CartService.php:262` - Hardcoded: `> 500 ? 0 : 50`
2. `CartService.php:432-436` - Method `calculateShipping()`: `>= 500 ? 0 : 50`
3. `ShippingService.php:70-82` - Proper implementation with zones

**Problems:**
- ❌ Three different implementations
- ❌ No single source of truth
- ❌ Inconsistent thresholds (> 500 vs >= 500)
- ❌ Only ShippingService is correct, others are wrong

---

### 5. **TAX CALCULATION ORDER ISSUE** ⚠️ CALCULATION ERROR
**Location:** `app/Services/CartService.php:261-263`

```php
$taxAmount = $discountedSubtotal * 0.18; // 18% GST
$shippingCost = ($discountedSubtotal > 500 || $couponFreeShipping) ? 0 : 50;
$total = $discountedSubtotal + $taxAmount + $shippingCost;
```

**Problem:**
- ❌ Tax calculated BEFORE shipping is determined
- ❌ Shipping should be added to taxable amount in some tax regimes
- ❌ Hardcoded 18% GST rate (should be configurable)

**Correct Order:**
1. Calculate subtotal
2. Apply product discounts
3. Apply coupon discount
4. Calculate shipping cost
5. Calculate tax on (subtotal - discounts + shipping) OR (subtotal - discounts) depending on tax rules
6. Calculate final total

---

### 6. **DISCOUNT STACKING LOGIC** ⚠️ BUSINESS LOGIC
**Location:** `app/Services/CartService.php:258`

```php
$totalDiscount = max($couponDiscount, $bundleDiscount); // Use better of the two
```

**Current Behavior:**
- Takes MAXIMUM of coupon or bundle discount (doesn't stack)

**Question:** Is this intentional?
- If yes, it's fine but should be documented
- If no, should be: `$totalDiscount = $couponDiscount + $bundleDiscount;`

---

### 7. **NO WEIGHT CALCULATION FOR SHIPPING** ⚠️ MISSING FEATURE
**Location:** `app/Services/CartService.php:196-288`

**Problem:**
- Cart summary doesn't calculate total weight
- ShippingService needs weight for accurate calculation
- Current: Uses simplified logic without weight

---

### 8. **FREE SHIPPING THRESHOLD NOT RESPECTING DATABASE** ⚠️ CRITICAL
**Location:** `app/Services/CartService.php:262,432`

**Problem:**
- Hardcoded ₹500 threshold
- Should use:
  - `shipping_zones.free_shipping_threshold` (per zone)
  - `shipping_zones.free_shipping_enabled` (on/off toggle)

**Current Database Values:**
```sql
Zone A: threshold=499, enabled=0
Zone B: threshold=699, enabled=0
Zone C: threshold=999, enabled=0
Zone D: threshold=1499, enabled=0
Zone E: threshold=2499, enabled=0
```

All zones currently have free shipping DISABLED, but cart shows free shipping at ₹500!

---

## Recommended Fixes

### Priority 1: Fix CartService.php

```php
public function getCartSummary($userId = null, $sessionId = null, $deliveryPincode = null, $pickupPincode = '110001')
{
    $cart = $this->getCart($userId, $sessionId);

    if (!$cart || !$cart->items) {
        return [/* empty cart response */];
    }

    // Calculate basic subtotal
    $subtotal = $cart->items->sum(function($item) {
        return $item->unit_price * $item->quantity;
    });

    $totalItems = $cart->items->sum('quantity');
    $couponDiscount = $cart->coupon_discount ?? 0;
    $couponFreeShipping = $cart->coupon_free_shipping ?? false;

    // Bundle discount calculation (existing logic is fine)
    $bundleDiscount = /* existing logic */;

    // Apply discounts
    $totalDiscount = max($couponDiscount, $bundleDiscount);
    $discountedSubtotal = max(0, $subtotal - $totalDiscount);

    // Calculate REAL shipping using ShippingService
    $shippingCost = 0;
    $shippingDetails = null;

    if ($deliveryPincode && !$couponFreeShipping) {
        try {
            $shippingService = app(\App\Services\ShippingService::class);

            // Prepare items for shipping calculation
            $shippingItems = $cart->items->map(function($item) {
                return [
                    'product' => $item->product,
                    'quantity' => $item->quantity
                ];
            })->toArray();

            // Calculate real shipping
            $shippingCalculation = $shippingService->calculateShippingCharges(
                $pickupPincode,
                $deliveryPincode,
                $shippingItems,
                $discountedSubtotal // Order value for free shipping check
            );

            // Get cheapest shipping option
            $cheapestOption = collect($shippingCalculation['shipping_options'])
                ->sortBy('final_cost')
                ->first();

            $shippingCost = $cheapestOption['final_cost'];
            $shippingDetails = [
                'zone' => $shippingCalculation['zone'],
                'zone_name' => $shippingCalculation['zone_name'],
                'free_shipping_threshold' => $shippingCalculation['free_shipping_threshold'],
                'free_shipping_enabled' => $shippingCalculation['free_shipping_enabled'],
                'is_free_shipping' => $cheapestOption['is_free_shipping']
            ];

        } catch (\Exception $e) {
            \Log::error('Shipping calculation failed in cart', ['error' => $e->getMessage()]);
            // Fallback to flat rate
            $shippingCost = 50;
        }
    }

    // Calculate tax on discounted subtotal (not including shipping)
    $taxAmount = $discountedSubtotal * 0.18;

    // Final total
    $total = $discountedSubtotal + $taxAmount + $shippingCost;

    return [
        'total_items' => $totalItems,
        'subtotal' => $subtotal,
        'coupon_code' => $cart->coupon_code,
        'coupon_discount' => $couponDiscount,
        'coupon_free_shipping' => $couponFreeShipping,
        'bundle_discount' => $bundleDiscount,
        'total_discount' => $totalDiscount,
        'discounted_subtotal' => $discountedSubtotal,
        'tax_amount' => $taxAmount,
        'shipping_cost' => $shippingCost,
        'shipping_details' => $shippingDetails,
        'total' => $total,
        'currency' => 'INR',
        'is_empty' => false,
        'requires_pincode' => !$deliveryPincode,
        'pincode_message' => !$deliveryPincode ? 'Enter delivery pincode to calculate shipping' : null
    ];
}
```

### Priority 2: Fix OrderController.php

```php
// NEVER accept shipping_cost from request
// ALWAYS calculate server-side

// Get delivery pincode from address
$deliveryPincode = $isAuthenticated
    ? $shippingAddress->postal_code
    : $request->shipping_address['postal_code'];

$pickupPincode = $this->getDefaultWarehousePincode(); // Or from config

// Recalculate cart summary with pincode
$cartSummary = $this->cartService->getCartSummary($userId, $sessionId, $deliveryPincode, $pickupPincode);

// Use calculated shipping, NEVER trust client
$shippingAmount = $cartSummary['shipping_cost'];

// Remove $request->shipping_cost validation - it should not exist
```

### Priority 3: Update API Routes

CartController needs new endpoint:
```php
public function calculateShipping(Request $request)
{
    $request->validate([
        'delivery_pincode' => 'required|string|size:6'
    ]);

    $user = $request->user();
    $userId = $user ? $user->id : null;
    $sessionId = $request->header('X-Session-ID');

    $cartSummary = $this->cartService->getCartSummary(
        $userId,
        $sessionId,
        $request->delivery_pincode
    );

    return response()->json([
        'success' => true,
        'summary' => $cartSummary
    ]);
}
```

---

## Summary of Changes Needed

1. ✅ **CartService::getCartSummary()** - Add pincode parameters, use ShippingService
2. ✅ **CartService::calculateShipping()** - Remove or update to use ShippingService
3. ✅ **CartController** - Add calculateShipping endpoint
4. ✅ **OrderController** - Remove shipping_cost from request, always calculate server-side
5. ✅ **Frontend** - Add pincode input in cart, call calculateShipping API
6. ❓ **Tax calculation** - Verify GST calculation order is correct for Indian tax laws
7. ❓ **Discount stacking** - Confirm business logic: max(coupon, bundle) or stack them?

---

## Testing Checklist

- [ ] Cart shows "Enter pincode" message when no pincode
- [ ] Cart calculates correct zone-based shipping when pincode entered
- [ ] Free shipping respects `free_shipping_enabled` flag
- [ ] Free shipping uses zone-based thresholds (not ₹500)
- [ ] Zone A (Mumbai to Mumbai): Shows ₹499 threshold, currently disabled
- [ ] Zone B (Mumbai to Pune): Shows ₹699 threshold, currently disabled
- [ ] Zone C (Delhi to Mumbai): Shows ₹999 threshold, currently disabled
- [ ] Checkout cannot be manipulated with custom shipping_cost
- [ ] Order creation uses server-calculated shipping
- [ ] Tax calculated correctly after discounts
- [ ] Bundle discount calculation works
- [ ] Coupon discount calculation works
- [ ] Max(coupon, bundle) logic confirmed as intentional
