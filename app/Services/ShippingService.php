<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingWeightSlab;
use App\Models\ShippingZone as ShippingZoneModel;
use App\Models\Pincode;
use App\Models\ShippingInsurance;
use App\Services\ZoneCalculationService;
use Illuminate\Support\Facades\Log;

class ShippingService
{
    protected ZoneCalculationService $zoneService;

    public function __construct(ZoneCalculationService $zoneService)
    {
        $this->zoneService = $zoneService;
    }

    // Dimensional factor for volumetric weight calculation (in cubic cm per kg)
    protected $dimensionalFactor = 5000;


    /**
     * Calculate comprehensive shipping charges with multiple courier options
     */
    public function calculateShippingCharges($pickupPincode, $deliveryPincode, $items, $orderValue = 0, $options = [])
    {
        try {
            // Check if pincodes are serviceable
            if (!Pincode::isServiceable($pickupPincode) || !Pincode::isServiceable($deliveryPincode)) {
                throw new \Exception('One or both pincodes are not serviceable');
            }

            // Get zone using ZoneCalculationService
            $zone = $this->zoneService->determineZone($pickupPincode, $deliveryPincode);

            if (!$zone) {
                throw new \Exception('Unable to determine shipping zone');
            }

            // Calculate total weight and dimensions
            $weightData = $this->calculateTotalWeight($items);
            $billableWeight = max($weightData['gross_weight'], $weightData['dimensional_weight']);

            // Get shipping options from database
            $shippingOptions = $this->getShippingOptions($zone, $billableWeight, $options);

            if (empty($shippingOptions)) {
                // Fallback to legacy calculation if no database rates found
                $baseCost = $this->getLegacyShippingCost($zone, $billableWeight);
                $shippingOptions = [
                    [
                        'zone' => $zone,
                        'base_weight' => $billableWeight,
                        'charged_weight' => $billableWeight,
                        'courier' => 'Standard',
                        'base_cost' => $baseCost,
                        'additional_weight_charge' => 0,
                        'cod_charge' => 0,
                        'total_cost' => $baseCost
                    ]
                ];
            }

            // Apply free shipping rules
            $freeShippingConfig = $this->getFreeShippingConfig($zone);
            $finalOptions = [];

            foreach ($shippingOptions as $option) {
                // Apply free shipping only if enabled and threshold is met
                $isFreeShipping = $freeShippingConfig['enabled'] &&
                                  $orderValue >= $freeShippingConfig['threshold'];
                $finalCost = $isFreeShipping ? 0 : $option['total_cost'];
                $finalOptions[] = array_merge($option, [
                    'final_cost' => round($finalCost, 2),
                    'is_free_shipping' => $finalCost == 0
                ]);
            }

            // Get pincode details
            $pickupDetails = Pincode::getPincodeDetails($pickupPincode);
            $deliveryDetails = Pincode::getPincodeDetails($deliveryPincode);

            // Calculate insurance options
            $insuranceOptions = $this->calculateInsuranceOptions($orderValue, $zone, $options);

            return [
                'zone' => $zone,
                'zone_name' => $this->getZoneName($zone),
                'gross_weight' => $weightData['gross_weight'],
                'dimensional_weight' => $weightData['dimensional_weight'],
                'billable_weight' => $billableWeight,
                'shipping_options' => $finalOptions,
                'free_shipping_threshold' => $freeShippingConfig['threshold'],
                'free_shipping_enabled' => $freeShippingConfig['enabled'],
                'delivery_estimate' => $this->getDeliveryEstimate($zone),
                'cod_available' => true,
                'pickup_details' => $pickupDetails,
                'delivery_details' => $deliveryDetails,
                'insurance_options' => $insuranceOptions['options'],
                'insurance_mandatory' => $insuranceOptions['is_mandatory'],
                'recommended_insurance' => $insuranceOptions['recommended'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Shipping calculation failed', [
                'pickup' => $pickupPincode,
                'delivery' => $deliveryPincode,
                'error' => $e->getMessage()
            ]);

            // Fallback to standard shipping
            return $this->getFallbackShipping($pickupPincode, $deliveryPincode);
        }
    }

    /**
     * Get shipping options from database based on zone and weight
     */
    protected function getShippingOptions(string $zone, float $weight, array $options = []): array
    {
        $cod = $options['cod'] ?? false;
        $collectAmount = $options['collect_amount'] ?? 0;

        $slabs = ShippingZoneModel::where('zone', $zone)
            ->whereHas('weightSlab', function ($q) use ($weight) {
                $q->where('base_weight', '>=', $weight);
            })
            ->with(['weightSlab' => function ($q) use ($weight) {
                $q->where('base_weight', '>=', $weight);
            }])
            ->orderByDesc(
                ShippingWeightSlab::select('base_weight')
                    ->whereColumn('shipping_weight_slabs.id', 'shipping_zones.shipping_weight_slab_id')
                    ->limit(1)
            )
            ->get();

        if ($slabs->isEmpty()) {
            return [];
        }

        $shippingOptions = [];

        foreach ($slabs as $slab) {
            $additionalWeightCharge = $this->calculateAdditionalWeightCharge($weight, $slab);
            $codCharge = $this->calculateCodCharge($slab, $cod, $collectAmount);
            $totalCost = $slab->fwd_rate + $additionalWeightCharge + $codCharge;

            $shippingOptions[] = [
                'zone' => $zone,
                'base_weight' => $slab->weightSlab->base_weight,
                'charged_weight' => max($weight, $slab->weightSlab->base_weight),
                'courier' => $slab->weightSlab->courier_name ?? 'Standard',
                'base_cost' => $slab->fwd_rate,
                'additional_weight_charge' => $additionalWeightCharge,
                'cod_charge' => $codCharge,
                'total_cost' => $totalCost
            ];
        }

        return $shippingOptions;
    }

    /**
     * Calculate additional weight charges
     */
    private function calculateAdditionalWeightCharge(float $weight, ShippingZoneModel $rate): float
    {
        $baseWeight = $rate->weightSlab->base_weight;

        if ($weight <= $baseWeight) {
            return 0;
        }

        $extraWeight = $weight - $baseWeight;
        $additionalUnits = ceil($extraWeight / $baseWeight);

        return $additionalUnits * $rate->aw_rate;
    }

    /**
     * Calculate COD charges
     */
    private function calculateCodCharge(ShippingZoneModel $rate, bool $cod, float $collectAmount): float
    {
        if (!$cod) {
            return 0;
        }

        return max($rate->cod_charges, ($rate->cod_percentage / 100) * $collectAmount);
    }

    /**
     * Get zone name
     */
    public function getZoneName(string $zone): string
    {
        $names = [
            'A' => 'Same City',
            'B' => 'Same State/Region',
            'C' => 'Metro to Metro',
            'D' => 'Rest of India',
            'E' => 'Northeast & J&K',
        ];

        return $names[$zone] ?? 'Unknown Zone';
    }


    /**
     * Calculate total weight including dimensional weight
     */
    protected function calculateTotalWeight($items)
    {
        $totalGrossWeight = 0;
        $totalVolume = 0; // in cubic cm

        foreach ($items as $item) {
            $product = $item['product'] ?? Product::find($item['product_id']);
            $quantity = $item['quantity'];

            // Product weight (convert grams to kg if needed)
            $productWeight = $product->weight ?? 0.25; // Default 250g for books
            if ($productWeight < 1) { // Assume it's in grams if less than 1
                $productWeight = $productWeight; // Keep as kg
            }

            // Product dimensions (default dimensions for books: 20x14x2 cm)
            $dimensions = $product->dimensions ?? ['length' => 20, 'width' => 14, 'height' => 2];
            if (is_string($dimensions)) {
                $dimensions = json_decode($dimensions, true) ?? ['length' => 20, 'width' => 14, 'height' => 2];
            }

            // Add packaging weight (10% of product weight or minimum 50g)
            $packagingWeight = max(0.05, $productWeight * 0.1);

            $totalGrossWeight += ($productWeight + $packagingWeight) * $quantity;
            $totalVolume += ($dimensions['length'] * $dimensions['width'] * $dimensions['height']) * $quantity;
        }

        // Calculate dimensional weight
        $dimensionalWeight = $totalVolume / $this->dimensionalFactor;

        return [
            'gross_weight' => round($totalGrossWeight, 2),
            'dimensional_weight' => round($dimensionalWeight, 2),
            'total_volume' => $totalVolume
        ];
    }

    /**
     * Legacy shipping cost calculation (fallback)
     */
    protected function getLegacyShippingCost($zone, $weight)
    {
        $legacyRates = [
            'A' => [0.5 => 30, 1.0 => 40, 2.0 => 50, 5.0 => 80, 10.0 => 120, 'additional_kg' => 15],
            'B' => [0.5 => 50, 1.0 => 65, 2.0 => 80, 5.0 => 120, 10.0 => 180, 'additional_kg' => 20],
            'C' => [0.5 => 70, 1.0 => 85, 2.0 => 100, 5.0 => 150, 10.0 => 220, 'additional_kg' => 25],
            'D' => [0.5 => 80, 1.0 => 95, 2.0 => 120, 5.0 => 180, 10.0 => 260, 'additional_kg' => 30],
            'E' => [0.5 => 120, 1.0 => 140, 2.0 => 170, 5.0 => 250, 10.0 => 350, 'additional_kg' => 40]
        ];

        $rates = $legacyRates[$zone] ?? $legacyRates['D'];
        $weightBrackets = [0.5, 1.0, 2.0, 5.0, 10.0];

        foreach ($weightBrackets as $bracket) {
            if ($weight <= $bracket) {
                return $rates[$bracket];
            }
        }

        // For weights above 10kg
        $baseCost = $rates[10.0];
        $additionalWeight = $weight - 10.0;
        $additionalCost = ceil($additionalWeight) * $rates['additional_kg'];

        return $baseCost + $additionalCost;
    }

    /**
     * Get free shipping configuration for a zone
     */
    protected function getFreeShippingConfig($zone): array
    {
        // Try to get from database first (per-zone configuration)
        $zoneConfig = \App\Models\ShippingZone::where('zone', $zone)
            ->orderBy('id', 'desc')
            ->first();

        // Get default thresholds from AdminSetting (dynamic configuration)
        $defaultThresholds = [
            'A' => (int) \App\Models\AdminSetting::get('zone_a_threshold', 499),
            'B' => (int) \App\Models\AdminSetting::get('zone_b_threshold', 699),
            'C' => (int) \App\Models\AdminSetting::get('zone_c_threshold', 999),
            'D' => (int) \App\Models\AdminSetting::get('zone_d_threshold', 1499),
            'E' => (int) \App\Models\AdminSetting::get('zone_e_threshold', 2499)
        ];

        if ($zoneConfig) {
            return [
                'enabled' => (bool) $zoneConfig->free_shipping_enabled,
                'threshold' => $zoneConfig->free_shipping_threshold ?? $defaultThresholds[$zone] ?? 1499
            ];
        }

        // Fallback if no config found
        return [
            'enabled' => false,
            'threshold' => $defaultThresholds[$zone] ?? 1499
        ];
    }

    /**
     * Get free shipping threshold for a zone (backward compatibility)
     */
    protected function getFreeShippingThreshold($zone): float
    {
        $config = $this->getFreeShippingConfig($zone);
        return $config['threshold'];
    }

    /**
     * Get delivery time estimate based on zone and delivery details
     */
    protected function getDeliveryEstimate($zone, $deliveryDetails = null)
    {
        if ($deliveryDetails && isset($deliveryDetails['expected_delivery_days'])) {
            $days = $deliveryDetails['expected_delivery_days'];
            return $days == 1 ? '1 business day' : "{$days} business days";
        }

        $estimates = [
            'A' => '1-2 business days',
            'B' => '2-3 business days',
            'C' => '3-4 business days',
            'D' => '4-6 business days',
            'E' => '6-10 business days'
        ];

        return $estimates[$zone] ?? '4-6 business days';
    }

    /**
     * Fallback shipping calculation
     */
    protected function getFallbackShipping($pickupPincode = null, $deliveryPincode = null)
    {
        return [
            'zone' => 'D',
            'zone_name' => 'Rest of India',
            'gross_weight' => 0.5,
            'dimensional_weight' => 0.5,
            'billable_weight' => 0.5,
            'base_cost' => 80,
            'final_cost' => 80,
            'free_shipping_threshold' => (int) \App\Models\AdminSetting::get('free_shipping_threshold', 500),
            'is_free_shipping' => false,
            'delivery_estimate' => '4-6 business days',
            'cod_available' => true,
            'is_remote' => false,
            'pickup_details' => null,
            'delivery_details' => null,
        ];
    }

    /**
     * Calculate shipping for cart items
     */
    public function calculateCartShipping($cartItems, $pickupPincode, $deliveryPincode, $options = [])
    {
        $items = [];
        $totalValue = 0;

        foreach ($cartItems as $cartItem) {
            $items[] = [
                'product' => $cartItem->product,
                'quantity' => $cartItem->quantity
            ];
            $totalValue += $cartItem->unit_price * $cartItem->quantity;
        }

        return $this->calculateShippingCharges($pickupPincode, $deliveryPincode, $items, $totalValue, $options);
    }

    /**
     * Update order with shipping information
     */
    public function updateOrderShipping(Order $order, $shippingData)
    {
        $order->update([
            'shipping_cost' => $shippingData['final_cost'],
            'shipping_zone' => $shippingData['zone'],
            'shipping_weight' => $shippingData['billable_weight'],
            'delivery_estimate' => $shippingData['delivery_estimate'],
            'total_amount' => $order->subtotal + $order->tax_amount + $shippingData['final_cost']
        ]);

        return $order;
    }

    /**
     * Check if COD is available for delivery pincode
     */
    public function isCodAvailable($deliveryPincode)
    {
        return $this->zoneService->isCodAvailable($deliveryPincode);
    }

    /**
     * Get zone for pickup and delivery pincode combination
     */
    public function getZone($pickupPincode, $deliveryPincode)
    {
        return $this->zoneService->determineZone($pickupPincode, $deliveryPincode);
    }

    /**
     * Check if pincode is serviceable
     */
    public function isServiceable($pincode)
    {
        return $this->zoneService->isServiceable($pincode);
    }

    /**
     * Get estimated delivery days
     */
    public function getEstimatedDeliveryDays($zone, $deliveryPincode = null)
    {
        if ($deliveryPincode) {
            $pincodeData = Pincode::getPincodeDetails($deliveryPincode);
            return $pincodeData ? 5 : 7; // Default 5 days for serviceable, 7 for non-serviceable
        }

        return $this->zoneService->getEstimatedDeliveryDays($zone);
    }

    /**
     * Calculate insurance options for the order
     */
    protected function calculateInsuranceOptions(float $orderValue, string $zone, array $options = []): array
    {
        $isRemote = $options['is_remote'] ?? false;
        $hasFragileItems = $options['has_fragile_items'] ?? false;
        $hasElectronics = $options['has_electronics'] ?? false;

        $insuranceContext = [
            'zone' => $zone,
            'is_remote' => $isRemote,
            'has_fragile_items' => $hasFragileItems,
            'has_electronics' => $hasElectronics,
        ];

        // Check if insurance is mandatory
        $isMandatory = ShippingInsurance::isMandatoryForConditions($orderValue, $insuranceContext);

        // Get all available options
        $availableOptions = ShippingInsurance::getAvailableOptionsForOrder($orderValue, $insuranceContext);

        // Find recommended option (lowest premium that covers full value)
        $recommended = null;
        $minPremium = PHP_FLOAT_MAX;

        foreach ($availableOptions as $option) {
            if ($option['coverage_percentage'] >= 100 && $option['premium'] < $minPremium) {
                $recommended = $option;
                $minPremium = $option['premium'];
            }
        }

        return [
            'is_mandatory' => $isMandatory,
            'options' => $availableOptions,
            'recommended' => $recommended,
        ];
    }

    /**
     * Calculate total shipping cost including insurance
     */
    public function calculateTotalShippingCost($shippingData, $insuranceId = null)
    {
        $totalCost = $shippingData['final_cost'];
        $insurancePremium = 0;
        $insuranceDetails = null;

        if ($insuranceId) {
            $insurance = ShippingInsurance::find($insuranceId);
            if ($insurance) {
                $orderValue = $shippingData['order_value'] ?? 1000;
                $insuranceOptions = [
                    'zone' => $shippingData['zone'],
                    'is_remote' => $shippingData['is_remote'],
                ];

                $insuranceCalculation = $insurance->calculatePremium($orderValue, $insuranceOptions);

                if ($insuranceCalculation['eligible']) {
                    $insurancePremium = $insuranceCalculation['premium'];
                    $insuranceDetails = $insuranceCalculation;
                }
            }
        }

        return [
            'shipping_cost' => $shippingData['final_cost'],
            'insurance_premium' => $insurancePremium,
            'total_shipping_cost' => $totalCost + $insurancePremium,
            'insurance_details' => $insuranceDetails,
        ];
    }

    /**
     * Get insurance options for display
     */
    public function getInsuranceOptions($orderValue, array $options = [])
    {
        return ShippingInsurance::getAvailableOptionsForOrder($orderValue, $options);
    }

    /**
     * Check if insurance is mandatory for given order
     */
    public function isInsuranceMandatory($orderValue, array $options = [])
    {
        return ShippingInsurance::isMandatoryForConditions($orderValue, $options);
    }

    /**
     * Get shipping zones for frontend
     */
    public function getShippingZones()
    {
        $zones = $this->zoneService->getAllZones();

        foreach ($zones as $code => &$zone) {
            $zone['free_shipping_threshold'] = $this->getFreeShippingThreshold($code);
            $zone['estimated_days'] = $zone['typical_days'];
        }

        return $zones;
    }
}
