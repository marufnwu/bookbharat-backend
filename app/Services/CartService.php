<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\PersistentCart;
use App\Models\Coupon;
use App\Models\AdminSetting;
use App\Jobs\SendAbandonedCartEmail;
use Illuminate\Support\Str;
use App\Services\ProductRecommendationService;
use App\Services\ShippingService;
use App\Services\ChargeCalculationService;
use App\Services\TaxCalculationService;

class CartService
{
    protected PricingEngine $pricingEngine;
    protected ProductRecommendationService $recommendationService;
    protected ShippingService $shippingService;
    protected ChargeCalculationService $chargeService;
    protected TaxCalculationService $taxService;

    public function __construct(
        PricingEngine $pricingEngine,
        ProductRecommendationService $recommendationService,
        ShippingService $shippingService,
        ChargeCalculationService $chargeService,
        TaxCalculationService $taxService
    ) {
        $this->pricingEngine = $pricingEngine;
        $this->recommendationService = $recommendationService;
        $this->shippingService = $shippingService;
        $this->chargeService = $chargeService;
        $this->taxService = $taxService;
    }

    public function addToCart($productId, $variantId = null, $quantity = 1, $attributes = [], $userId = null, $sessionId = null)
    {
        $product = Product::findOrFail($productId);
        $variant = $variantId ? ProductVariant::findOrFail($variantId) : null;
        
        // Check stock availability
        if (!$this->checkStockAvailability($product, $variant, $quantity)) {
            throw new \Exception('Insufficient stock available');
        }
        
        $cart = $this->getOrCreateCart($userId, $sessionId);
        
        // Check if item already exists in cart
        $existingItem = $cart->items()
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->where('attributes', json_encode($attributes))
            ->first();
        
        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $quantity;
            if (!$this->checkStockAvailability($product, $variant, $newQuantity)) {
                throw new \Exception('Cannot add more items - insufficient stock');
            }
            $existingItem->update(['quantity' => $newQuantity]);
            $cartItem = $existingItem;
        } else {
            $unitPrice = $variant ? $variant->price : $product->price;
            
            $cartItem = $cart->items()->create([
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'attributes' => $attributes,
            ]);
        }
        
        // Reserve stock
        if ($variant) {
            $variant->reserveStock($quantity);
        } else {
            // Handle stock reservation for simple products
            $product->decrement('stock_quantity', $quantity);
        }
        
        // $this->updateCartTotals($cart); // Disabled complex pricing for now
        // $this->updatePersistentCart($cart); // Disabled for now
        
        return $cartItem;
    }
    
    public function updateCartItem($cartItem, $quantity)
    {
        if (is_int($cartItem)) {
            $cartItem = CartItem::findOrFail($cartItem);
        }
        $cart = $cartItem->cart;
        
        $oldQuantity = $cartItem->quantity;
        $quantityDiff = $quantity - $oldQuantity;
        
        if ($quantityDiff > 0) {
            // Check stock availability for additional quantity
            if (!$this->checkStockAvailability($cartItem->product, $cartItem->variant, $quantityDiff)) {
                throw new \Exception('Insufficient stock available');
            }
        }
        
        $cartItem->update(['quantity' => $quantity]);
        
        // Update stock reservation
        if ($cartItem->variant) {
            if ($quantityDiff > 0) {
                $cartItem->variant->reserveStock($quantityDiff);
            } else {
                $cartItem->variant->releaseStock(abs($quantityDiff));
            }
        }
        
        // $this->updateCartTotals($cart); // Disabled complex pricing for now
        // $this->updatePersistentCart($cart); // Disabled for now
        
        return $cartItem;
    }
    
    public function removeFromCart($cartItem)
    {
        if (is_int($cartItem)) {
            $cartItem = CartItem::findOrFail($cartItem);
        }
        $cart = $cartItem->cart;
        
        // Release reserved stock
        if ($cartItem->variant) {
            $cartItem->variant->releaseStock($cartItem->quantity);
        }
        
        $cartItem->delete();
        
        // $this->updateCartTotals($cart); // Disabled complex pricing for now
        // $this->updatePersistentCart($cart); // Disabled for now
        
        return true;
    }
    
    public function clearCart($userId = null, $sessionId = null)
    {
        $cart = $this->getCart($userId, $sessionId);
        
        if ($cart) {
            // Release all reserved stock
            foreach ($cart->items as $item) {
                if ($item->variant) {
                    $item->variant->releaseStock($item->quantity);
                }
            }
            
            $cart->items()->delete();
            // $this->updateCartTotals($cart); // Disabled complex pricing for now
            // $this->updatePersistentCart($cart); // Disabled for now
        }
        
        return true;
    }
    
    public function getCart($userId = null, $sessionId = null)
    {
        \Log::info('CartService::getCart called', ['userId' => $userId, 'sessionId' => $sessionId]);
        
        if ($userId) {
            // For authenticated users, first look for carts by user_id
            // This covers both user-only carts and user+session carts
            $cart = Cart::with(['items.product.images', 'items.variant'])
                ->where('user_id', $userId)
                ->first();
            
            \Log::info('Cart search by user_id', ['found' => $cart ? true : false, 'cart_id' => $cart?->id]);
            
            if ($cart) {
                return $cart;
            }
            
            // Fallback: if no user cart found and session provided,
            // look for session cart that could be converted to user cart
            if ($sessionId) {
                $cart = Cart::with(['items.product.images', 'items.variant'])
                    ->where('session_id', $sessionId)
                    ->whereNull('user_id')
                    ->first();
                    
                \Log::info('Cart search by session_id (fallback)', ['found' => $cart ? true : false, 'cart_id' => $cart?->id]);
                return $cart;
            }
        } else if ($sessionId) {
            // For guest users, look for session-based carts
            $cart = Cart::with(['items.product.images', 'items.variant'])
                ->where('session_id', $sessionId)
                ->whereNull('user_id')
                ->first();
                
            \Log::info('Cart search by session_id (guest)', ['found' => $cart ? true : false, 'cart_id' => $cart?->id]);
            return $cart;
        }
        
        \Log::info('No cart found');
        return null;
    }
    
    public function getCartSummary($userId = null, $sessionId = null, $deliveryPincode = null, $pickupPincode = null)
    {
        $cart = $this->getCart($userId, $sessionId);

        if (!$cart || !$cart->items || $cart->items->isEmpty()) {
            return [
                'total_items' => 0,
                'subtotal' => 0,
                'tax_amount' => 0,
                'shipping_cost' => 0,
                'total' => 0,
                'currency' => 'INR',
                'is_empty' => true,
                'requires_pincode' => true,
                'shipping_details' => null
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

        // Calculate REAL shipping using ShippingService
        $shippingCost = 0;
        $shippingDetails = null;
        $requiresPincode = !$deliveryPincode;

        if ($deliveryPincode && !$couponFreeShipping) {
            try {
                // Get default pickup pincode if not provided
                if (!$pickupPincode) {
                    $pickupPincode = $this->getDefaultPickupPincode();
                }

                // Prepare items for shipping calculation
                $shippingItems = $cart->items->map(function($item) {
                    $product = $item->product;
                    return [
                        'product' => (object) [
                            'weight' => $product->weight ?? 0.5, // Default 0.5kg if not set
                            'dimensions' => [
                                'length' => $product->length ?? 20,
                                'width' => $product->width ?? 14,
                                'height' => $product->height ?? 5
                            ]
                        ],
                        'quantity' => $item->quantity
                    ];
                })->toArray();

                // Calculate real shipping using ShippingService
                $shippingCalculation = $this->shippingService->calculateShippingCharges(
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
                    'is_free_shipping' => $cheapestOption['is_free_shipping'],
                    'billable_weight' => $shippingCalculation['billable_weight'],
                    'delivery_estimate' => $shippingCalculation['delivery_estimate']
                ];

            } catch (\Exception $e) {
                // Log error but don't break cart functionality
                \Log::error('Shipping calculation failed in cart', [
                    'error' => $e->getMessage(),
                    'pickup' => $pickupPincode,
                    'delivery' => $deliveryPincode
                ]);
                // Fallback to flat rate
                $shippingCost = 50;
                $requiresPincode = true;
            }
        } else if ($couponFreeShipping) {
            $shippingCost = 0;
            $shippingDetails = [
                'is_free_shipping' => true,
                'reason' => 'Coupon provides free shipping'
            ];
        }

        // Prepare order context for charge and tax calculation
        $paymentMethod = request()->input('payment_method', 'online'); // Default to online payment
        $orderContext = [
            'payment_method' => $paymentMethod,
            'order_value' => $subtotal,
            'discounted_value' => $discountedSubtotal,
            'shipping_cost' => $shippingCost,
            'pincode' => $deliveryPincode,
            'state' => null, // TODO: Get state from address if available
            'categories' => $cart->items->pluck('product.category_id')->unique()->toArray(),
        ];

        // Calculate all applicable charges using ChargeCalculationService
        $chargesResult = $this->chargeService->calculateCharges($orderContext);
        $totalCharges = $chargesResult['total_charges'];
        $chargesBreakdown = $chargesResult['charges'];

        // Calculate taxes using TaxCalculationService
        $taxesResult = $this->taxService->calculateTaxes($orderContext, $chargesResult);
        $taxAmount = $taxesResult['total_tax'];
        $taxesBreakdown = $taxesResult['taxes'];

        // Calculate final total
        $total = $discountedSubtotal + $taxAmount + $shippingCost + $totalCharges;

        $summary = [
            'total_items' => $totalItems,
            'subtotal' => round($subtotal, 2),
            'coupon_code' => $cart->coupon_code,
            'coupon_discount' => round($couponDiscount, 2),
            'coupon_free_shipping' => $couponFreeShipping,
            'bundle_discount' => round($bundleDiscount, 2),
            'bundle_details' => $bundleDetails,
            'total_discount' => round($totalDiscount, 2),
            'discounted_subtotal' => round($discountedSubtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'taxes_breakdown' => $taxesBreakdown,
            'shipping_cost' => round($shippingCost, 2),
            'shipping_details' => $shippingDetails,
            'charges' => $chargesBreakdown,
            'total_charges' => round($totalCharges, 2),
            'payment_method' => $paymentMethod,
            'total' => round($total, 2),
            'currency' => 'INR',
            'is_empty' => false,
            'requires_pincode' => $requiresPincode,
            'pincode_message' => $requiresPincode ? 'Enter delivery pincode to calculate shipping' : null
        ];

        // Add bundle savings message if applicable
        if ($bundleDiscount > 0) {
            $summary['discount_message'] = "Bundle discount applied! You saved ₹" . number_format($bundleDiscount, 2);
        }

        return $summary;
    }    
    public function mergeGuestCart($guestSessionId, User $user)
    {
        $guestCart = $this->getCart(null, $guestSessionId);
        $userCart = $this->getCart($user->id);
        
        if (!$guestCart) {
            return $userCart;
        }
        
        if (!$userCart) {
            // Transfer guest cart to user
            $guestCart->update([
                'user_id' => $user->id,
                'session_id' => null,
            ]);
            return $guestCart;
        }
        
        // Merge guest cart items into user cart
        foreach ($guestCart->items as $guestItem) {
            $existingItem = $userCart->items()
                ->where('product_id', $guestItem->product_id)
                ->where('variant_id', $guestItem->variant_id)
                ->where('attributes', $guestItem->attributes)
                ->first();
                
            if ($existingItem) {
                $existingItem->increment('quantity', $guestItem->quantity);
            } else {
                $guestItem->update(['cart_id' => $userCart->id]);
            }
        }
        
        $guestCart->delete();
        $this->updateCartTotals($userCart);
        
        return $userCart;
    }
    
    public function calculateCartTotals($cart, $user = null)
    {
        $subtotal = 0;
        $totalItems = 0;
        $totalWeight = 0;
        
        foreach ($cart->items as $item) {
            $pricing = $this->pricingEngine->calculatePrice(
                $item->product,
                $item->variant,
                $user,
                ['quantity' => $item->quantity]
            );
            
            $lineTotal = $pricing['final_price'] * $item->quantity;
            $subtotal += $lineTotal;
            $totalItems += $item->quantity;
            
            $weight = $item->variant ? $item->variant->weight : $item->product->weight;
            $totalWeight += ($weight ?? 0) * $item->quantity;
        }
        
        // Calculate shipping (this would integrate with shipping service)
        $shippingCost = $this->calculateShipping($cart, $totalWeight);
        
        // Calculate taxes (this would integrate with tax service)
        $taxAmount = $this->calculateTax($subtotal, $cart);
        
        $total = $subtotal + $shippingCost + $taxAmount;
        
        return [
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'total_items' => $totalItems,
            'total_weight' => $totalWeight,
            'currency' => 'INR',
        ];
    }
    
    protected function getOrCreateCart($userId = null, $sessionId = null)
    {
        \Log::info('CartService::getOrCreateCart called', ['userId' => $userId, 'sessionId' => $sessionId]);
        
        $cart = $this->getCart($userId, $sessionId);
        
        if (!$cart) {
            \Log::info('Creating new cart', ['user_id' => $userId, 'session_id' => $sessionId]);
            $cart = Cart::create([
                'user_id' => $userId,
                'session_id' => $sessionId ?: Str::uuid(),
                'status' => 'active',
                'currency' => 'INR',
            ]);
            \Log::info('Cart created', ['cart_id' => $cart->id, 'user_id' => $cart->user_id, 'session_id' => $cart->session_id]);
        }
        
        return $cart;
    }
    
    protected function checkStockAvailability(Product $product, ?ProductVariant $variant, int $quantity)
    {
        if ($variant) {
            return $variant->available_stock >= $quantity;
        }
        
        return $product->stock_quantity >= $quantity;
    }
    
    protected function updateCartTotals($cart)
    {
        $totals = $this->calculateCartTotals($cart, $cart->user);
        
        $cart->update([
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'shipping_cost' => $totals['shipping_cost'],
            'total' => $totals['total'],
            'total_items' => $totals['total_items'],
            'updated_at' => now(),
        ]);
    }
    
    protected function updatePersistentCart($cart)
    {
        if ($cart->user_id) {
            PersistentCart::updateOrCreate([
                'user_id' => $cart->user_id,
            ], [
                'session_id' => $cart->session_id ?: Str::uuid(),
                'cart_data' => $cart->items->toJson(),
                'total_amount' => $cart->total,
                'items_count' => $cart->total_items,
                'currency' => $cart->currency,
                'last_activity' => now(),
                'expires_at' => now()->addDays(30),
            ]);
        }
    }
    
    protected function calculateShipping($cart, $totalWeight)
    {
        // This method is deprecated - use getCartSummary with pincode instead
        \Log::warning('Deprecated calculateShipping method called - use getCartSummary with pincode');
        return 50; // Fallback flat rate
    }

    protected function getDefaultPickupPincode(): string
    {
        // Try to get default warehouse pincode
        try {
            $defaultWarehouse = \App\Models\InventoryLocation::where('is_default', true)
                ->where('is_active', true)
                ->first();

            if ($defaultWarehouse && $defaultWarehouse->postal_code) {
                return $defaultWarehouse->postal_code;
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get default warehouse pincode', ['error' => $e->getMessage()]);
        }

        // Fallback to Delhi
        return '110001';
    }
    
    protected function calculateTax($subtotal, $cart)
    {
        // Basic tax calculation - integrate with tax service
        $taxRate = 0.18; // 18% GST
        return $subtotal * $taxRate;
    }
    
    public function scheduleAbandonmentRecovery($cartId)
    {
        // Schedule abandoned cart emails
        SendAbandonedCartEmail::dispatch($cartId)
            ->delay(now()->addHour()); // Send after 1 hour
            
        SendAbandonedCartEmail::dispatch($cartId)
            ->delay(now()->addDay()); // Send after 24 hours
            
        SendAbandonedCartEmail::dispatch($cartId)
            ->delay(now()->addDays(3)); // Send after 3 days
    }
    
    public function processCheckout($cartId, $paymentData, $shippingData)
    {
        $cart = Cart::with('items')->findOrFail($cartId);
        
        // Validate cart items availability
        foreach ($cart->items as $item) {
            if (!$this->checkStockAvailability($item->product, $item->variant, $item->quantity)) {
                throw new \Exception("Item {$item->product->name} is no longer available in requested quantity");
            }
        }
        
        // Create order (integrate with order service)
        // Process payment (integrate with payment service)
        // Update inventory (move from reserved to sold)
        // Clear cart
        
        return true;
    }
    
    public function applyCoupon($couponCode, $userId = null, $sessionId = null)
    {
        $cart = $this->getCart($userId, $sessionId);
        
        if (!$cart || $cart->items->isEmpty()) {
            throw new \Exception('Cart is empty');
        }
        
        $coupon = Coupon::where('code', $couponCode)->valid()->first();
        
        if (!$coupon) {
            throw new \Exception('Invalid or expired coupon code');
        }
        
        // Check if user can use this coupon
        if ($userId) {
            $user = User::find($userId);
            if ($user && !$coupon->canBeUsedBy($user)) {
                throw new \Exception('This coupon cannot be used by you');
            }
        }
        
        $subtotal = $cart->items->sum(function($item) {
            return $item->unit_price * $item->quantity;
        });
        
        // Check minimum order amount
        if ($coupon->minimum_order_amount && $subtotal < $coupon->minimum_order_amount) {
            throw new \Exception('Order amount must be at least ₹' . number_format($coupon->minimum_order_amount, 2) . ' to use this coupon');
        }
        
        // Calculate discount
        $cartItems = $cart->items->map(function($item) {
            return [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->unit_price,
                'product' => $item->product
            ];
        })->toArray();
        
        $discountResult = $coupon->calculateDiscount($subtotal, $cartItems);
        
        if ($discountResult['discount_amount'] <= 0 && !$discountResult['free_shipping']) {
            throw new \Exception('This coupon is not applicable to your cart items');
        }
        
        // Store coupon in cart
        $cart->update([
            'coupon_code' => $couponCode,
            'coupon_discount' => $discountResult['discount_amount'],
            'coupon_free_shipping' => $discountResult['free_shipping']
        ]);
        
        $cartSummary = $this->getCartSummary($userId, $sessionId);
        
        return [
            'discount' => $discountResult,
            'cart_summary' => $cartSummary
        ];
    }
    
    public function removeCoupon($userId = null, $sessionId = null)
    {
        $cart = $this->getCart($userId, $sessionId);
        
        if (!$cart) {
            throw new \Exception('Cart not found');
        }
        
        $cart->update([
            'coupon_code' => null,
            'coupon_discount' => 0,
            'coupon_free_shipping' => false
        ]);
        
        return $this->getCartSummary($userId, $sessionId);
    }
}