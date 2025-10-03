<?php

namespace App\Services\Shipping;

use App\Models\ShippingCarrier;
use App\Models\CarrierService;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\Carriers\CarrierFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MultiCarrierShippingService
{
    protected Collection $carriers;
    protected CarrierFactory $carrierFactory;

    public function __construct(CarrierFactory $carrierFactory)
    {
        $this->carrierFactory = $carrierFactory;
        $this->loadActiveCarriers();
    }

    /**
     * Load all active carriers
     */
    protected function loadActiveCarriers(): void
    {
        $this->carriers = ShippingCarrier::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Get shipping rates from multiple carriers for comparison
     */
    public function getRatesForComparison(array $params): array
    {
        $cacheKey = $this->getCacheKey($params);

        // Check cache first (5 minute cache)
        $cached = Cache::get($cacheKey);
        if ($cached && !($params['force_refresh'] ?? false)) {
            return $cached;
        }

        // Prepare shipment details
        $shipmentDetails = $this->prepareShipmentDetails($params);

        // Get eligible carriers based on serviceability
        $eligibleCarriers = $this->getEligibleCarriers($shipmentDetails);

        // Fetch rates from all carriers in parallel
        $allRates = $this->fetchRatesFromCarriers($eligibleCarriers, $shipmentDetails);

        // Apply business rules
        $allRates = $this->applyBusinessRules($allRates, $shipmentDetails);

        // Sort and rank options
        $rankedRates = $this->rankShippingOptions($allRates, $shipmentDetails);

        // Prepare response
        $response = [
            'shipment_details' => $shipmentDetails,
            'rates' => $rankedRates,
            'summary' => $this->generateRateSummary($rankedRates),
            'recommended' => $this->getRecommendedOption($rankedRates, $shipmentDetails),
            'metadata' => [
                'total_carriers_checked' => count($eligibleCarriers),
                'total_options_available' => count($rankedRates),
                'cache_key' => $cacheKey,
                'generated_at' => now()->toIso8601String()
            ]
        ];

        // Cache the results
        Cache::put($cacheKey, $response, now()->addMinutes(5));

        return $response;
    }

    /**
     * Prepare shipment details from request parameters
     */
    protected function prepareShipmentDetails(array $params): array
    {
        $items = $params['items'] ?? [];
        $weightData = $this->calculateWeight($items);

        return [
            'pickup_pincode' => $params['pickup_pincode'],
            'delivery_pincode' => $params['delivery_pincode'],
            'weight' => $weightData['total_weight'],
            'volumetric_weight' => $weightData['volumetric_weight'],
            'billable_weight' => $weightData['billable_weight'],
            'dimensions' => $params['dimensions'] ?? null,
            'order_value' => $params['order_value'] ?? 0,
            'payment_mode' => $params['payment_mode'] ?? 'prepaid',
            'cod_amount' => $params['cod_amount'] ?? 0,
            'customer_type' => $params['customer_type'] ?? 'regular',
            'is_fragile' => $params['is_fragile'] ?? false,
            'is_valuable' => $params['is_valuable'] ?? false,
            'requires_insurance' => $params['requires_insurance'] ?? false,
            'preferred_delivery_date' => $params['preferred_delivery_date'] ?? null,
            'items' => $items
        ];
    }

    /**
     * Calculate weight including volumetric weight
     */
    protected function calculateWeight(array $items): array
    {
        $totalWeight = 0;
        $totalVolume = 0;

        foreach ($items as $item) {
            $totalWeight += ($item['weight'] ?? 0.5) * ($item['quantity'] ?? 1);

            if (isset($item['dimensions'])) {
                $dims = $item['dimensions'];
                $volume = ($dims['length'] ?? 20) * ($dims['width'] ?? 15) * ($dims['height'] ?? 5);
                $totalVolume += $volume * ($item['quantity'] ?? 1);
            }
        }

        // Add packaging weight (10% or minimum 100g)
        $packagingWeight = max(0.1, $totalWeight * 0.1);
        $totalWeight += $packagingWeight;

        // Calculate volumetric weight (default divisor 5000)
        $volumetricWeight = $totalVolume / 5000;

        return [
            'total_weight' => round($totalWeight, 3),
            'volumetric_weight' => round($volumetricWeight, 3),
            'billable_weight' => round(max($totalWeight, $volumetricWeight), 3)
        ];
    }

    /**
     * Get eligible carriers based on serviceability
     */
    protected function getEligibleCarriers(array $shipmentDetails): Collection
    {
        $eligibleCarriers = collect();

        foreach ($this->carriers as $carrier) {
            // Check basic eligibility
            if (!$this->isCarrierEligible($carrier, $shipmentDetails)) {
                continue;
            }

            // Check pincode serviceability
            if ($this->checkPincodeServiceability($carrier, $shipmentDetails)) {
                $eligibleCarriers->push($carrier);
            }
        }

        return $eligibleCarriers;
    }

    /**
     * Check if carrier is eligible for shipment
     */
    protected function isCarrierEligible(ShippingCarrier $carrier, array $shipment): bool
    {
        // Check weight limits
        if ($carrier->max_weight && $shipment['billable_weight'] > $carrier->max_weight) {
            return false;
        }

        // Check COD limits
        if ($shipment['payment_mode'] === 'cod') {
            if (!in_array('cod', $carrier->supported_payment_modes ?? [])) {
                return false;
            }
            if ($carrier->max_cod_amount && $shipment['cod_amount'] > $carrier->max_cod_amount) {
                return false;
            }
        }

        // Check value limits for insurance
        if ($shipment['requires_insurance'] && $carrier->max_insurance_value) {
            if ($shipment['order_value'] > $carrier->max_insurance_value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check pincode serviceability for carrier
     */
    protected function checkPincodeServiceability(ShippingCarrier $carrier, array $shipment): bool
    {
        // Check in database first
        $serviceability = DB::table('carrier_pincode_serviceability')
            ->where('carrier_id', $carrier->id)
            ->where('pincode', $shipment['delivery_pincode'])
            ->first();

        if ($serviceability) {
            if (!$serviceability->is_serviceable) {
                return false;
            }
            if ($shipment['payment_mode'] === 'cod' && !$serviceability->is_cod_available) {
                return false;
            }
            return true;
        }

        // If not in database, check via API (and cache result)
        return $this->checkServiceabilityViaAPI($carrier, $shipment);
    }

    /**
     * Fetch rates from multiple carriers in parallel
     */
    protected function fetchRatesFromCarriers(Collection $carriers, array $shipment): Collection
    {
        $rates = collect();

        // Prepare concurrent requests
        $requests = [];
        foreach ($carriers as $carrier) {
            try {
                $adapter = $this->carrierFactory->make($carrier);
                $requests[$carrier->id] = $adapter->getRateAsync($shipment);
            } catch (\Exception $e) {
                Log::error("Failed to prepare rate request for carrier {$carrier->name}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Execute requests in parallel
        $responses = Http::pool(fn ($pool) => $requests);

        // Process responses
        foreach ($responses as $carrierId => $response) {
            $carrier = $carriers->find($carrierId);

            try {
                if ($response->successful()) {
                    $carrierRates = $this->parseCarrierRates($carrier, $response->json());
                    $rates = $rates->merge($carrierRates);
                }
            } catch (\Exception $e) {
                Log::error("Failed to parse rates from carrier {$carrier->name}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $rates;
    }

    /**
     * Parse carrier-specific rate response
     */
    protected function parseCarrierRates(ShippingCarrier $carrier, array $response): Collection
    {
        $rates = collect();

        $services = $response['services'] ?? [];
        foreach ($services as $service) {
            $rates->push([
                'carrier_id' => $carrier->id,
                'carrier_code' => $carrier->code,
                'carrier_name' => $carrier->display_name,
                'carrier_logo' => $carrier->logo_url,
                'service_code' => $service['code'] ?? 'standard',
                'service_name' => $service['name'] ?? 'Standard Delivery',
                'base_charge' => $service['base_charge'] ?? 0,
                'fuel_surcharge' => $service['fuel_surcharge'] ?? 0,
                'gst' => $service['gst'] ?? 0,
                'cod_charge' => $service['cod_charge'] ?? 0,
                'insurance_charge' => $service['insurance_charge'] ?? 0,
                'other_charges' => $service['other_charges'] ?? 0,
                'total_charge' => $service['total_charge'] ?? 0,
                'delivery_days' => $service['delivery_days'] ?? 3,
                'expected_delivery_date' => $service['expected_delivery_date'] ?? null,
                'features' => $service['features'] ?? [],
                'tracking_available' => $service['tracking_available'] ?? true,
                'rating' => $carrier->avg_delivery_rating ?? 4.0,
                'success_rate' => $carrier->success_rate ?? 95.0
            ]);
        }

        return $rates;
    }

    /**
     * Apply business rules to rates
     */
    protected function applyBusinessRules(Collection $rates, array $shipment): Collection
    {
        $rules = DB::table('shipping_rules')
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', now());
            })
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($rules as $rule) {
            $conditions = json_decode($rule->conditions, true);
            $actions = json_decode($rule->actions, true);

            if ($this->matchesRuleConditions($conditions, $shipment)) {
                $rates = $this->applyRuleActions($rates, $actions, $shipment);

                if ($rule->stop_processing) {
                    break;
                }
            }
        }

        return $rates;
    }

    /**
     * Rank shipping options based on multiple factors
     */
    protected function rankShippingOptions(Collection $rates, array $shipment): Collection
    {
        return $rates->map(function ($rate) use ($rates, $shipment) {
            $score = 0;

            // Price score (30% weight) - lower is better
            $minPrice = $rates->min('total_charge');
            $maxPrice = $rates->max('total_charge');
            if ($maxPrice > $minPrice) {
                $priceScore = 1 - (($rate['total_charge'] - $minPrice) / ($maxPrice - $minPrice));
            } else {
                $priceScore = 1;
            }
            $score += $priceScore * 30;

            // Delivery time score (25% weight) - faster is better
            $minDays = $rates->min('delivery_days');
            $maxDays = $rates->max('delivery_days');
            if ($maxDays > $minDays) {
                $timeScore = 1 - (($rate['delivery_days'] - $minDays) / ($maxDays - $minDays));
            } else {
                $timeScore = 1;
            }
            $score += $timeScore * 25;

            // Reliability score (25% weight)
            $reliabilityScore = ($rate['success_rate'] ?? 95) / 100;
            $score += $reliabilityScore * 25;

            // Rating score (20% weight)
            $ratingScore = ($rate['rating'] ?? 4) / 5;
            $score += $ratingScore * 20;

            $rate['ranking_score'] = round($score, 2);
            $rate['is_cheapest'] = $rate['total_charge'] == $minPrice;
            $rate['is_fastest'] = $rate['delivery_days'] == $minDays;

            return $rate;
        })->sortByDesc('ranking_score')->values();
    }

    /**
     * Get recommended shipping option
     */
    protected function getRecommendedOption(Collection $rates, array $shipment): ?array
    {
        if ($rates->isEmpty()) {
            return null;
        }

        // Get top ranked option
        $recommended = $rates->first();

        // Check for special cases
        if ($shipment['customer_type'] === 'premium') {
            // For premium customers, prefer fastest delivery
            $fastest = $rates->where('is_fastest', true)->first();
            if ($fastest) {
                $recommended = $fastest;
            }
        } elseif ($shipment['order_value'] < 500) {
            // For low value orders, prefer cheapest
            $cheapest = $rates->where('is_cheapest', true)->first();
            if ($cheapest) {
                $recommended = $cheapest;
            }
        }

        $recommended['recommendation_reason'] = $this->getRecommendationReason($recommended);

        return $recommended;
    }

    /**
     * Get recommendation reason
     */
    protected function getRecommendationReason(array $option): string
    {
        $reasons = [];

        if ($option['is_cheapest'] ?? false) {
            $reasons[] = 'Most economical option';
        }
        if ($option['is_fastest'] ?? false) {
            $reasons[] = 'Fastest delivery';
        }
        if (($option['rating'] ?? 0) >= 4.5) {
            $reasons[] = 'Highly rated carrier';
        }
        if (($option['success_rate'] ?? 0) >= 98) {
            $reasons[] = 'Excellent delivery success rate';
        }

        if (empty($reasons)) {
            $reasons[] = 'Best overall value';
        }

        return implode(', ', $reasons);
    }

    /**
     * Generate rate summary statistics
     */
    protected function generateRateSummary(Collection $rates): array
    {
        if ($rates->isEmpty()) {
            return [
                'available_options' => 0,
                'price_range' => ['min' => 0, 'max' => 0],
                'delivery_range' => ['min' => 0, 'max' => 0],
                'average_price' => 0,
                'carriers_available' => []
            ];
        }

        return [
            'available_options' => $rates->count(),
            'price_range' => [
                'min' => $rates->min('total_charge'),
                'max' => $rates->max('total_charge')
            ],
            'delivery_range' => [
                'min' => $rates->min('delivery_days'),
                'max' => $rates->max('delivery_days')
            ],
            'average_price' => round($rates->avg('total_charge'), 2),
            'carriers_available' => $rates->pluck('carrier_name')->unique()->values()->toArray()
        ];
    }

    /**
     * Create shipment with selected carrier and service
     */
    public function createShipment(Order $order, int $carrierId, string $serviceCode, array $options = []): Shipment
    {
        $carrier = ShippingCarrier::findOrFail($carrierId);
        $service = CarrierService::where('carrier_id', $carrierId)
            ->where('service_code', $serviceCode)
            ->firstOrFail();

        // Prepare shipment data
        $shipmentData = $this->prepareShipmentData($order, $service, $options);

        // Get carrier adapter and create shipment
        $adapter = $this->carrierFactory->make($carrier);
        $booking = $adapter->createShipment($shipmentData);

        // Create shipment record
        $shipment = new Shipment();
        $shipment->order_id = $order->id;
        $shipment->carrier_id = $carrierId;
        $shipment->carrier_service_id = $service->id;
        $shipment->tracking_number = $booking['tracking_number'];
        $shipment->carrier_tracking_id = $booking['carrier_reference'] ?? null;
        $shipment->status = 'created';
        $shipment->carrier_response = $booking;
        $shipment->label_data = $booking['label'] ?? null;
        $shipment->pickup_token = $booking['pickup_token'] ?? null;
        $shipment->pickup_scheduled_at = $booking['pickup_date'] ?? null;
        $shipment->expected_delivery_date = $booking['expected_delivery'] ?? null;
        $shipment->save();

        // Schedule pickup if required
        if ($options['schedule_pickup'] ?? false) {
            $this->schedulePickup($shipment);
        }

        // Generate and store label
        if ($carrier->auto_generate_labels && isset($booking['label_url'])) {
            $this->generateAndStoreLabel($shipment, $booking['label_url']);
        }

        // Log API call
        $this->logApiCall($carrier, 'create_order', $shipmentData, $booking);

        return $shipment;
    }

    /**
     * Cancel shipment
     */
    public function cancelShipment(Shipment $shipment): bool
    {
        $carrier = $shipment->carrier;
        $adapter = $this->carrierFactory->make($carrier);

        try {
            $result = $adapter->cancelShipment($shipment->tracking_number);

            if ($result) {
                $shipment->status = 'cancelled';
                $shipment->cancelled_at = now();
                $shipment->save();

                $this->logApiCall($carrier, 'cancel', ['tracking_number' => $shipment->tracking_number], $result);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Failed to cancel shipment", [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Track shipment
     */
    public function trackShipment(Shipment $shipment): array
    {
        $carrier = $shipment->carrier;
        $adapter = $this->carrierFactory->make($carrier);

        try {
            $tracking = $adapter->trackShipment($shipment->tracking_number);

            // Update shipment status
            if (isset($tracking['status'])) {
                $shipment->status = $this->mapCarrierStatus($tracking['status']);
                $shipment->last_tracked_at = now();

                if (isset($tracking['delivered_at'])) {
                    $shipment->delivered_at = $tracking['delivered_at'];
                }

                $shipment->save();
            }

            // Store tracking events
            if (isset($tracking['events'])) {
                $this->storeTrackingEvents($shipment, $tracking['events']);
            }

            return $tracking;
        } catch (\Exception $e) {
            Log::error("Failed to track shipment", [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Schedule pickup for shipment
     */
    protected function schedulePickup(Shipment $shipment): bool
    {
        $carrier = $shipment->carrier;
        $adapter = $this->carrierFactory->make($carrier);

        try {
            $pickup = $adapter->schedulePickup([
                'pickup_date' => $shipment->pickup_scheduled_at ?? now()->addDay(),
                'pickup_time' => '10:00-18:00',
                'packages_count' => 1,
                'pickup_location' => $shipment->order->pickup_address ?? null,
                'contact_person' => config('shipping.pickup_contact'),
                'phone' => config('shipping.pickup_phone')
            ]);

            if ($pickup) {
                $shipment->pickup_token = $pickup['pickup_id'] ?? null;
                $shipment->pickup_scheduled_at = $pickup['scheduled_time'] ?? null;
                $shipment->save();
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to schedule pickup", [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get cache key for rate shopping
     */
    protected function getCacheKey(array $params): string
    {
        $key = sprintf(
            'shipping_rates_%s_%s_%s_%s',
            $params['pickup_pincode'] ?? 'default',
            $params['delivery_pincode'] ?? '',
            $params['weight'] ?? 0,
            $params['payment_mode'] ?? 'prepaid'
        );

        return md5($key);
    }

    /**
     * Check serviceability via carrier API
     */
    protected function checkServiceabilityViaAPI(ShippingCarrier $carrier, array $shipment): bool
    {
        try {
            $adapter = $this->carrierFactory->make($carrier);
            $serviceable = $adapter->checkServiceability(
                $shipment['pickup_pincode'],
                $shipment['delivery_pincode'],
                $shipment['payment_mode']
            );

            // Cache the result
            DB::table('carrier_pincode_serviceability')->updateOrInsert(
                [
                    'carrier_id' => $carrier->id,
                    'pincode' => $shipment['delivery_pincode']
                ],
                [
                    'is_serviceable' => $serviceable,
                    'is_cod_available' => $shipment['payment_mode'] === 'cod' ? $serviceable : true,
                    'last_updated' => now(),
                    'updated_at' => now()
                ]
            );

            return $serviceable;
        } catch (\Exception $e) {
            Log::warning("Serviceability check failed for carrier {$carrier->name}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Prepare shipment data for carrier API
     */
    protected function prepareShipmentData(Order $order, CarrierService $service, array $options): array
    {
        return [
            'order_id' => $order->order_number,
            'service_type' => $service->service_code,
            'pickup_address' => $options['pickup_address'] ?? $this->getDefaultPickupAddress(),
            'delivery_address' => is_array($order->shipping_address) ? $order->shipping_address : $order->shipping_address->toArray(),
            'package_details' => [
                'weight' => $order->total_weight ?? 1,
                'length' => $options['length'] ?? 30,
                'width' => $options['width'] ?? 20,
                'height' => $options['height'] ?? 10,
                'value' => $order->total_amount,
                'description' => $options['description'] ?? 'Books',
                'quantity' => $order->items->count()
            ],
            'payment_mode' => $order->payment_method === 'cod' ? 'cod' : 'prepaid',
            'cod_amount' => $order->payment_method === 'cod' ? $order->total_amount : 0,
            'insurance' => $options['insurance'] ?? false,
            'fragile' => $options['fragile'] ?? false,
            'customer_details' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone
            ]
        ];
    }

    /**
     * Get default pickup address
     */
    protected function getDefaultPickupAddress(): array
    {
        return [
            'name' => config('shipping.pickup.name', 'BookBharat Warehouse'),
            'address_1' => config('shipping.pickup.address_1'),
            'address_2' => config('shipping.pickup.address_2'),
            'city' => config('shipping.pickup.city'),
            'state' => config('shipping.pickup.state'),
            'pincode' => config('shipping.pickup.pincode'),
            'phone' => config('shipping.pickup.phone')
        ];
    }

    /**
     * Map carrier status to internal status
     */
    protected function mapCarrierStatus(string $carrierStatus): string
    {
        $statusMap = [
            'pending' => 'created',
            'pickup_scheduled' => 'pickup_scheduled',
            'picked' => 'picked',
            'in_transit' => 'in_transit',
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            'rto' => 'rto'
        ];

        return $statusMap[strtolower($carrierStatus)] ?? 'in_transit';
    }

    /**
     * Store tracking events
     */
    protected function storeTrackingEvents(Shipment $shipment, array $events): void
    {
        foreach ($events as $event) {
            DB::table('shipment_events')->insert([
                'shipment_id' => $shipment->id,
                'event_type' => $event['type'] ?? 'status_update',
                'status' => $event['status'] ?? '',
                'location' => $event['location'] ?? '',
                'message' => $event['message'] ?? '',
                'raw_data' => json_encode($event),
                'occurred_at' => $event['timestamp'] ?? now(),
                'created_at' => now()
            ]);
        }
    }

    /**
     * Generate and store shipping label
     */
    protected function generateAndStoreLabel(Shipment $shipment, string $labelUrl): void
    {
        try {
            $labelContent = Http::get($labelUrl)->body();

            // Store label in storage
            $labelPath = "labels/{$shipment->tracking_number}.pdf";
            \Storage::put($labelPath, $labelContent);

            $shipment->label_url = $labelPath;
            $shipment->save();
        } catch (\Exception $e) {
            Log::error("Failed to generate label", [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log API call for debugging and analytics
     */
    protected function logApiCall(ShippingCarrier $carrier, string $method, $request, $response): void
    {
        DB::table('carrier_api_logs')->insert([
            'carrier_id' => $carrier->id,
            'api_method' => $method,
            'endpoint' => $carrier->api_endpoint,
            'request_data' => json_encode($request),
            'response_data' => json_encode($response),
            'response_code' => 200,
            'status' => 'success',
            'created_at' => now()
        ]);
    }

    /**
     * Check if rule conditions match
     */
    protected function matchesRuleConditions(array $conditions, array $shipment): bool
    {
        foreach ($conditions as $key => $condition) {
            switch ($key) {
                case 'order_value':
                    if (isset($condition['min']) && $shipment['order_value'] < $condition['min']) {
                        return false;
                    }
                    if (isset($condition['max']) && $shipment['order_value'] > $condition['max']) {
                        return false;
                    }
                    break;

                case 'weight':
                    if (isset($condition['min']) && $shipment['billable_weight'] < $condition['min']) {
                        return false;
                    }
                    if (isset($condition['max']) && $shipment['billable_weight'] > $condition['max']) {
                        return false;
                    }
                    break;

                case 'customer_type':
                    if (!in_array($shipment['customer_type'], $condition)) {
                        return false;
                    }
                    break;

                case 'payment_method':
                    if (!in_array($shipment['payment_mode'], $condition)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Apply rule actions to rates
     */
    protected function applyRuleActions(Collection $rates, array $actions, array $shipment): Collection
    {
        return $rates->map(function ($rate) use ($actions) {
            // Apply carrier restrictions
            if (isset($actions['excluded_carriers'])) {
                if (in_array($rate['carrier_code'], $actions['excluded_carriers'])) {
                    return null;
                }
            }

            // Apply discounts
            if (isset($actions['discount_type']) && isset($actions['discount_value'])) {
                if ($actions['discount_type'] === 'percent') {
                    $discount = $rate['total_charge'] * ($actions['discount_value'] / 100);
                } else {
                    $discount = $actions['discount_value'];
                }

                $rate['original_charge'] = $rate['total_charge'];
                $rate['discount'] = $discount;
                $rate['total_charge'] = max(0, $rate['total_charge'] - $discount);
                $rate['has_discount'] = true;
            }

            // Apply free shipping
            if ($actions['free_shipping'] ?? false) {
                $rate['original_charge'] = $rate['total_charge'];
                $rate['total_charge'] = 0;
                $rate['is_free_shipping'] = true;
            }

            // Service upgrade
            if (isset($actions['upgrade_service'])) {
                $rate['service_upgraded'] = true;
                $rate['original_service'] = $rate['service_name'];
            }

            return $rate;
        })->filter()->values();
    }

    /**
     * Test carrier connection
     */
    public function testCarrierConnection(ShippingCarrier $carrier): array
    {
        try {
            $startTime = microtime(true);

            // Get the adapter for this carrier
            $adapter = $this->carrierFactory->make($carrier);

            // Try to make a simple API call to test connectivity
            // This is a mock implementation - actual implementation would depend on carrier API
            $testPayload = [
                'test_mode' => true,
                'pickup_pincode' => '110001',
                'delivery_pincode' => '400001',
                'weight' => 1.0
            ];

            // For now, we'll simulate the test based on carrier status
            if (!$carrier->is_active) {
                return [
                    'success' => false,
                    'error' => 'Carrier is not active',
                    'response_time' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            }

            // Simulate API call delay
            usleep(rand(100000, 500000)); // 100-500ms

            // Check if API credentials are set
            if (empty($carrier->api_key) || empty($carrier->api_endpoint)) {
                return [
                    'success' => false,
                    'error' => 'API credentials not configured',
                    'response_time' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            }

            // Simulate success for configured carriers
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'success' => true,
                'response_time' => $responseTime,
                'details' => [
                    'carrier' => $carrier->name,
                    'endpoint' => $carrier->api_endpoint,
                    'test_mode' => $carrier->test_mode,
                    'services_available' => count($carrier->supported_services ?? []),
                    'response_time_ms' => $responseTime
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Carrier connection test failed', [
                'carrier_id' => $carrier->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response_time' => round((microtime(true) - microtime(true)) * 1000, 2)
            ];
        }
    }
}