    public function getCartSummary($userId = null, $sessionId = null)
    {
        $cart = $this->getCart($userId, $sessionId);
        
        if (!$cart || !$cart->items) {
            return [
                'total_items' => 0,
                'subtotal' => 0,
                'tax_amount' => 0,
                'shipping_cost' => 0,
                'total' => 0,
                'currency' => 'INR',
                'is_empty' => true
            ];
        }
        
        // Calculate basic subtotal
        $subtotal = $cart->items->sum(function($item) {
            return $item->unit_price * $item->quantity;
        });
        
        $totalItems = $cart->items->sum('quantity');
        $couponDiscount = $cart->coupon_discount ?? 0;
        $couponFreeShipping = $cart->coupon_free_shipping ?? false;
        
        // Check for bundle discounts
        $bundleDiscount = 0;
        $bundleDetails = null;
        
        if ($cart->items->count() >= 2) {
            try {
                // Get products from cart items
                $products = $cart->items->map(function($item) {
                    $product = $item->product;
                    $product->cart_quantity = $item->quantity;
                    return $product;
                });
                
                // Calculate potential bundle discount using recommendation service
                $bundleData = $this->recommendationService->calculateBundlePrice(
                    $products->first(), 
                    $products->slice(1)
                );
                
                if ($bundleData && $bundleData['savings'] > 0) {
                    $bundleDiscount = $bundleData['savings'];
                    $bundleDetails = [
                        'original_price' => $bundleData['total_price'],
                        'bundle_price' => $bundleData['bundle_price'],
                        'savings' => $bundleData['savings'],
                        'discount_percentage' => $bundleData['discount_percentage'],
                        'product_count' => $bundleData['product_count'],
                        'discount_rule' => $bundleData['discount_rule'] ?? null
                    ];
                }
            } catch (\Exception $e) {
                // Log error but don't break cart functionality
                \Log::warning('Bundle discount calculation failed', ['error' => $e->getMessage()]);
            }
        }
        
        // Apply all discounts (coupon + bundle, but bundle shouldn't stack with coupon typically)
        $totalDiscount = max($couponDiscount, $bundleDiscount); // Use better of the two
        $discountedSubtotal = max(0, $subtotal - $totalDiscount);
        
        $taxAmount = $discountedSubtotal * 0.18; // 18% GST
        $shippingCost = ($discountedSubtotal > 500 || $couponFreeShipping) ? 0 : 50;
        $total = $discountedSubtotal + $taxAmount + $shippingCost;
        
        $summary = [
            'total_items' => $totalItems,
            'subtotal' => $subtotal,
            'coupon_code' => $cart->coupon_code,
            'coupon_discount' => $couponDiscount,
            'coupon_free_shipping' => $couponFreeShipping,
            'bundle_discount' => $bundleDiscount,
            'bundle_details' => $bundleDetails,
            'total_discount' => $totalDiscount,
            'discounted_subtotal' => $discountedSubtotal,
            'tax_amount' => $taxAmount,
            'shipping_cost' => $shippingCost,
            'total' => $total,
            'currency' => 'INR',
            'is_empty' => false
        ];
        
        // Add bundle savings message if applicable
        if ($bundleDiscount > 0) {
            $summary['discount_message'] = "Bundle discount applied! You saved ₹" . number_format($bundleDiscount, 2);
        }
        
        return $summary;
    }