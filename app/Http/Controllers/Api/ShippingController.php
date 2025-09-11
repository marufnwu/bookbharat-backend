<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShippingService;
use App\Models\Cart;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    protected $shippingService;

    public function __construct(ShippingService $shippingService)
    {
        $this->shippingService = $shippingService;
    }

    /**
     * Calculate shipping for cart items
     */
    public function calculateCartShipping(Request $request)
    {
        $request->validate([
            'delivery_pincode' => 'required|string|size:6',
            'pickup_pincode' => 'string|size:6',
            'include_insurance' => 'boolean',
            'delivery_option_id' => 'nullable|exists:delivery_options,id',
            'is_remote' => 'boolean',
            'has_fragile_items' => 'boolean',
            'has_electronics' => 'boolean',
        ]);

        try {
            $userId = auth()->id();
            $sessionId = $request->header('X-Session-ID');
            
            // Get cart items
            $cart = Cart::where(function($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })->with('items.product')->first();

            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart is empty'
                ], 400);
            }

            $pickupPincode = $request->pickup_pincode ?? '110001'; // Default Delhi
            $deliveryPincode = $request->delivery_pincode;
            
            $options = [
                'include_insurance' => $request->boolean('include_insurance'),
                'delivery_option_id' => $request->delivery_option_id,
                'is_remote' => $request->boolean('is_remote'),
                'has_fragile_items' => $request->boolean('has_fragile_items'),
                'has_electronics' => $request->boolean('has_electronics'),
                'order_date' => now()->toDateString(),
                'order_time' => now()->format('H:i:s'),
            ];

            $shippingData = $this->shippingService->calculateCartShipping(
                $cart->items,
                $pickupPincode,
                $deliveryPincode,
                $options
            );

            return response()->json([
                'success' => true,
                'shipping' => $shippingData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate shipping',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate shipping for specific items
     */
    public function calculateShipping(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'delivery_pincode' => 'required|string|size:6',
            'pickup_pincode' => 'string|size:6',
            'order_value' => 'numeric|min:0',
            'include_insurance' => 'boolean',
            'delivery_option_id' => 'nullable|exists:delivery_options,id',
            'is_remote' => 'boolean',
            'has_fragile_items' => 'boolean',
            'has_electronics' => 'boolean',
        ]);

        try {
            $items = collect($request->items)->map(function ($item) {
                return [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity']
                ];
            })->toArray();

            $pickupPincode = $request->pickup_pincode ?? '110001';
            $deliveryPincode = $request->delivery_pincode;
            $orderValue = $request->order_value ?? 0;
            
            $options = [
                'include_insurance' => $request->boolean('include_insurance'),
                'delivery_option_id' => $request->delivery_option_id,
                'is_remote' => $request->boolean('is_remote'),
                'has_fragile_items' => $request->boolean('has_fragile_items'),
                'has_electronics' => $request->boolean('has_electronics'),
                'order_date' => now()->toDateString(),
                'order_time' => now()->format('H:i:s'),
            ];

            $shippingData = $this->shippingService->calculateShippingCharges(
                $pickupPincode,
                $deliveryPincode,
                $items,
                $orderValue,
                $options
            );

            return response()->json([
                'success' => true,
                'shipping' => $shippingData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate shipping',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shipping zones information
     */
    public function getShippingZones()
    {
        try {
            $zones = $this->shippingService->getShippingZones();

            return response()->json([
                'success' => true,
                'zones' => $zones
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shipping zones'
            ], 500);
        }
    }

    /**
     * Check if pincode is serviceable and get zone info
     */
    public function checkPincode(Request $request)
    {
        $request->validate([
            'pincode' => 'required|string|size:6',
            'pickup_pincode' => 'string|size:6'
        ]);

        try {
            $deliveryPincode = $request->pincode;
            
            // First check if pincode exists in our database
            $pincodeDetails = \App\Models\Pincode::getPincodeDetails($deliveryPincode);
            
            if (!$pincodeDetails) {
                return response()->json([
                    'success' => false,
                    'serviceable' => false,
                    'message' => 'Sorry, we do not deliver to this pincode',
                    'pincode' => $deliveryPincode
                ], 400);
            }

            // Check if delivery is available for this pincode
            $deliveryAvailable = \App\Models\Pincode::isDeliveryAvailable($deliveryPincode);
            
            if (!$deliveryAvailable) {
                return response()->json([
                    'success' => false,
                    'serviceable' => false,
                    'message' => 'Sorry, we do not deliver to this pincode',
                    'pincode' => $deliveryPincode
                ], 400);
            }

            return response()->json([
                'success' => true,
                'serviceable' => true,
                'pincode' => $deliveryPincode,
                'city' => $pincodeDetails['city'],
                'district' => $pincodeDetails['district'],
                'state' => $pincodeDetails['state'],
                'region' => $pincodeDetails['region'],
                'office_name' => $pincodeDetails['office_name'],
                'delivery_status' => $pincodeDetails['delivery_status'],
                'estimated_delivery' => '2-7 business days',
                'free_shipping_threshold' => 999,
                'cod_available' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'serviceable' => false,
                'message' => 'Pincode not serviceable or invalid',
                'pincode' => $request->pincode
            ], 400);
        }
    }

    /**
     * Get shipping rates table for display
     */
    public function getShippingRates()
    {
        try {
            $zones = $this->shippingService->getShippingZones();
            
            // Sample rates for different weight brackets
            $sampleRates = [];
            $weights = [0.5, 1.0, 2.0, 5.0, 10.0];
            
            foreach ($zones as $zoneCode => $zoneInfo) {
                $zoneRates = [];
                foreach ($weights as $weight) {
                    // Calculate rate for this weight
                    $dummyItems = [['product_id' => 1, 'quantity' => 1]];
                    $shippingData = $this->shippingService->calculateShippingCharges(
                        '110001', 
                        $zoneCode === 'A' ? '110001' : '400001', // Different zones
                        $dummyItems,
                        0
                    );
                    
                    $zoneRates[$weight . 'kg'] = '₹' . $shippingData['base_cost'];
                }
                $sampleRates[$zoneCode] = [
                    'info' => $zoneInfo,
                    'rates' => $zoneRates
                ];
            }

            return response()->json([
                'success' => true,
                'rates' => $sampleRates,
                'note' => 'Rates shown are base charges before free shipping thresholds'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shipping rates'
            ], 500);
        }
    }

    /**
     * Get available delivery options for conditions
     */
    public function getAvailableDeliveryOptions(Request $request)
    {
        $request->validate([
            'zone' => 'required|string|in:A,B,C,D,E',
            'order_value' => 'required|numeric|min:1',
            'order_date' => 'nullable|date',
            'order_time' => 'nullable|date_format:H:i:s',
        ]);

        try {
            $options = [
                'order_date' => $request->order_date ?? now()->toDateString(),
                'order_time' => $request->order_time ?? now()->format('H:i:s'),
            ];

            $availableOptions = \App\Models\DeliveryOption::getAvailableOptions(
                $request->zone,
                $request->order_value,
                50, // Base shipping cost
                $options
            );

            return response()->json([
                'success' => true,
                'delivery_options' => $availableOptions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get delivery options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available insurance plans for order value
     */
    public function getAvailableInsurancePlans(Request $request)
    {
        $request->validate([
            'order_value' => 'required|numeric|min:1',
            'zone' => 'nullable|string|in:A,B,C,D,E',
            'has_fragile_items' => 'boolean',
            'has_electronics' => 'boolean',
        ]);

        try {
            $options = [
                'zone' => $request->zone ?? 'D',
                'has_fragile_items' => $request->boolean('has_fragile_items'),
                'has_electronics' => $request->boolean('has_electronics'),
            ];

            $availablePlans = \App\Models\ShippingInsurance::getAvailableInsurance(
                $request->order_value,
                $options
            );

            return response()->json([
                'success' => true,
                'insurance_plans' => $availablePlans
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get insurance plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}