<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shipping\MultiCarrierShippingService;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingCarrier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MultiCarrierShippingController extends Controller
{
    protected MultiCarrierShippingService $shippingService;

    public function __construct(MultiCarrierShippingService $shippingService)
    {
        $this->shippingService = $shippingService;
    }

    /**
     * Get and compare rates from multiple carriers
     */
    public function compareRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'nullable|exists:orders,id',
            'pickup_pincode' => 'required|string|size:6',
            'delivery_pincode' => 'required|string|size:6',
            'weight' => 'nullable|numeric|min:0.1',
            'dimensions' => 'nullable|array',
            'dimensions.length' => 'nullable|numeric|min:1',
            'dimensions.width' => 'nullable|numeric|min:1',
            'dimensions.height' => 'nullable|numeric|min:1',
            'order_value' => 'required|numeric|min:0',
            'payment_mode' => 'required|in:prepaid,cod',
            'cod_amount' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|integer',
            'items.*.name' => 'required|string',
            'items.*.weight' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.value' => 'nullable|numeric|min:0',
            'customer_type' => 'nullable|string|in:regular,premium,vip',
            'is_fragile' => 'nullable|boolean',
            'is_valuable' => 'nullable|boolean',
            'requires_insurance' => 'nullable|boolean',
            'preferred_delivery_date' => 'nullable|date',
            'force_refresh' => 'nullable|boolean'
        ]);

        try {
            // If order_id is provided, fetch order details
            if (isset($validated['order_id'])) {
                $order = Order::with(['orderItems'])->find($validated['order_id']);
                if ($order) {
                    $validated = $this->mergeOrderDataWithRequest($order, $validated);
                }
            }

            // Get rates from multiple carriers
            $rates = $this->shippingService->getRatesForComparison($validated);

            return response()->json([
                'success' => true,
                'message' => 'Rates fetched successfully',
                'data' => $rates
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch shipping rates', [
                'error' => $e->getMessage(),
                'request' => $validated
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shipping rates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a shipment with selected carrier
     */
    public function createShipment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'carrier_id' => 'required|exists:shipping_carriers,id',
            'service_code' => 'required|string',
            'shipping_cost' => 'required|numeric|min:0',
            'warehouse_id' => 'nullable|string', // Can be numeric ID or carrier-registered alias
            'expected_delivery_date' => 'nullable|date',
            'schedule_pickup' => 'nullable|boolean',
            'insurance' => 'nullable|boolean',
            'fragile' => 'nullable|boolean',
            'length' => 'nullable|numeric|min:1',
            'width' => 'nullable|numeric|min:1',
            'height' => 'nullable|numeric|min:1',
            'description' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            $order = Order::findOrFail($validated['order_id']);

            // Check if shipment already exists
            if ($order->shipment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shipment already exists for this order'
                ], 400);
            }

            // Create shipment
            $shipment = $this->shippingService->createShipment(
                $order,
                $validated['carrier_id'],
                $validated['service_code'],
                $validated
            );

            // Update order status
            $order->status = 'processing';
            $order->shipping_cost = $validated['shipping_cost'];
            $order->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Shipment created successfully',
                'data' => [
                    'shipment_id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_number,
                    'carrier' => $shipment->carrier->name,
                    'status' => $shipment->status,
                    'expected_delivery' => $shipment->expected_delivery_date,
                    'label_url' => $shipment->label_url
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create shipment', [
                'error' => $e->getMessage(),
                'order_id' => $validated['order_id']
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create shipment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a shipment
     */
    public function cancelShipment($shipmentId): JsonResponse
    {
        try {
            $shipment = Shipment::findOrFail($shipmentId);

            if ($shipment->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Shipment is already cancelled'
                ], 400);
            }

            if (in_array($shipment->status, ['delivered', 'out_for_delivery'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel shipment in current status'
                ], 400);
            }

            $result = $this->shippingService->cancelShipment($shipment);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Shipment cancelled successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel shipment with carrier'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to cancel shipment', [
                'error' => $e->getMessage(),
                'shipment_id' => $shipmentId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel shipment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track a shipment
     */
    public function trackShipment($trackingNumber): JsonResponse
    {
        try {
            $shipment = Shipment::where('tracking_number', $trackingNumber)->firstOrFail();

            $tracking = $this->shippingService->trackShipment($shipment);

            return response()->json([
                'success' => true,
                'message' => 'Tracking information retrieved',
                'data' => [
                    'tracking_number' => $shipment->tracking_number,
                    'carrier' => $shipment->carrier->name,
                    'status' => $shipment->status,
                    'current_location' => $tracking['current_location'] ?? null,
                    'last_updated' => $tracking['last_updated'] ?? $shipment->last_tracked_at,
                    'expected_delivery' => $shipment->expected_delivery_date,
                    'delivered_at' => $shipment->delivered_at,
                    'events' => $tracking['events'] ?? []
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track shipment', [
                'error' => $e->getMessage(),
                'tracking_number' => $trackingNumber
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to track shipment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pickup location configuration
     */
    public function getPickupLocation(): JsonResponse
    {
        try {
            $pickupLocation = config('shipping-carriers.pickup_location');

            return response()->json([
                'success' => true,
                'data' => $pickupLocation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pickup location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active carriers
     */
    public function getCarriers(): JsonResponse
    {
        try {
            // Get all carriers for admin panel (both active and inactive)
            $carriers = ShippingCarrier::orderBy('priority', 'desc')
                ->orderBy('is_primary', 'desc')
                ->orderBy('name', 'asc')
                ->get();

            // Transform to include config data and flatten credentials
            $carriers->transform(function ($carrier) {
                $config = is_array($carrier->config) ? $carrier->config : json_decode($carrier->config, true) ?? [];

                // Flatten config data
                $carrier->features = $config['features'] ?? [];
                $carrier->services = $config['services'] ?? [];
                $carrier->pickup_days = $config['pickup_days'] ?? [];
                $carrier->webhook_url = $config['webhook_url'] ?? '';
                $carrier->cutoff_time = $config['cutoff_time'] ?? '17:00';
                $carrier->weight_unit = $config['weight_unit'] ?? 'kg';
                $carrier->dimension_unit = $config['dimension_unit'] ?? 'cm';

                // Include credential field structure (defines what fields admin can edit)
                $carrier->credential_fields = $config['credential_fields'] ?? [];

                // Flatten credentials from config.credentials to carrier root level for frontend
                // This makes it easier for frontend to read/write credentials
                if (isset($config['credentials']) && is_array($config['credentials'])) {
                    foreach ($config['credentials'] as $key => $value) {
                        $carrier->{$key} = $value;
                    }
                }

                return $carrier;
            });

            return response()->json([
                'success' => true,
                'data' => $carriers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch carriers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get carrier services
     */
    public function getCarrierServices($carrierId): JsonResponse
    {
        try {
            $carrier = ShippingCarrier::with('services')->findOrFail($carrierId);

            return response()->json([
                'success' => true,
                'data' => [
                    'carrier' => [
                        'id' => $carrier->id,
                        'name' => $carrier->display_name,
                        'logo' => $carrier->logo_url
                    ],
                    'services' => $carrier->services->map(function ($service) {
                        return [
                            'id' => $service->id,
                            'code' => $service->service_code,
                            'name' => $service->display_name,
                            'description' => $service->description,
                            'mode' => $service->mode,
                            'delivery_time' => "{$service->min_delivery_hours}-{$service->max_delivery_hours} hours",
                            'features' => [
                                'cod' => $service->supports_cod,
                                'insurance' => $service->supports_insurance,
                                'fragile' => $service->supports_fragile,
                                'doorstep_qc' => $service->supports_doorstep_qc
                            ]
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch carrier services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check pincode serviceability
     */
    public function checkServiceability(Request $request, $carrierId): JsonResponse
    {
        $validated = $request->validate([
            'pincode' => 'required|string|size:6',
            'service_type' => 'nullable|string|in:pickup,delivery'
        ]);

        try {
            $carrier = ShippingCarrier::findOrFail($carrierId);
            $serviceType = $validated['service_type'] ?? 'delivery';

            // Check in database
            $serviceability = DB::table('carrier_pincode_serviceability')
                ->where('carrier_id', $carrierId)
                ->where('pincode', $validated['pincode'])
                ->first();

            if ($serviceability) {
                $isServiceable = $serviceType === 'pickup'
                    ? $serviceability->is_pickup_available
                    : $serviceability->is_serviceable;

                return response()->json([
                    'success' => true,
                    'data' => [
                        'serviceable' => $isServiceable,
                        'pincode' => $validated['pincode'],
                        'city' => $serviceability->city,
                        'state' => $serviceability->state,
                        'zone' => $serviceability->zone,
                        'cod_available' => $serviceability->is_cod_available,
                        'prepaid_available' => $serviceability->is_prepaid_available,
                        'is_oda' => $serviceability->is_oda,
                        'delivery_days' => $serviceability->standard_delivery_days
                    ]
                ]);
            }

            // If not in database, return default response
            return response()->json([
                'success' => true,
                'data' => [
                    'serviceable' => false,
                    'pincode' => $validated['pincode'],
                    'message' => 'Pincode not serviceable by this carrier'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check serviceability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shipment label
     */
    public function getLabel($shipmentId): JsonResponse
    {
        try {
            $shipment = Shipment::findOrFail($shipmentId);

            if (!$shipment->label_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'Label not available for this shipment'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'label_url' => \Storage::url($shipment->label_url),
                    'tracking_number' => $shipment->tracking_number
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get label',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process carrier webhook
     */
    public function processWebhook(Request $request, $carrierCode): JsonResponse
    {
        try {
            $carrier = ShippingCarrier::where('code', $carrierCode)->firstOrFail();

            // Log webhook data
            Log::info("Webhook received from {$carrier->name}", [
                'data' => $request->all()
            ]);

            // Process based on carrier
            switch ($carrierCode) {
                case 'delhivery':
                    $this->processDelhiveryWebhook($request->all());
                    break;
                case 'bluedart':
                    $this->processBluedartWebhook($request->all());
                    break;
                case 'xpressbees':
                    $this->processXpressbeesWebhook($request->all());
                    break;
                // Add more carriers as needed
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error("Webhook processing failed for carrier {$carrierCode}", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get shipping performance analytics
     */
    public function getPerformanceAnalytics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'carrier_id' => 'nullable|exists:shipping_carriers,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date'
        ]);

        try {
            $query = DB::table('carrier_performance_metrics');

            if (isset($validated['carrier_id'])) {
                $query->where('carrier_id', $validated['carrier_id']);
            }

            if (isset($validated['date_from'])) {
                $query->where('date', '>=', $validated['date_from']);
            }

            if (isset($validated['date_to'])) {
                $query->where('date', '<=', $validated['date_to']);
            }

            $metrics = $query->get();

            // Calculate aggregates
            $summary = [
                'total_shipments' => $metrics->sum('total_shipments'),
                'on_time_deliveries' => $metrics->sum('on_time_deliveries'),
                'delayed_deliveries' => $metrics->sum('delayed_deliveries'),
                'failed_deliveries' => $metrics->sum('failed_deliveries'),
                'average_delivery_hours' => $metrics->avg('average_delivery_hours'),
                'average_sla_achievement' => $metrics->avg('sla_achievement_percent'),
                'average_cost_efficiency' => $metrics->avg('cost_efficiency_score')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'daily_metrics' => $metrics
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Merge order data with request
     */
    private function mergeOrderDataWithRequest(Order $order, array $validated): array
    {
        if (!isset($validated['delivery_pincode']) && $order->shipping_address) {
            $validated['delivery_pincode'] = $order->shipping_address['pincode'] ?? $order->delivery_pincode;
        }

        if (!isset($validated['order_value'])) {
            $validated['order_value'] = $order->total_amount;
        }

        if (!isset($validated['payment_mode'])) {
            $validated['payment_mode'] = $order->payment_method === 'cod' ? 'cod' : 'prepaid';
        }

        if (!isset($validated['cod_amount']) && $order->payment_method === 'cod') {
            $validated['cod_amount'] = $order->total_amount;
        }

        if (!isset($validated['items']) && $order->orderItems) {
            $validated['items'] = $order->orderItems->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'name' => $item->product_name,
                    'weight' => $item->weight ?? 0.5,
                    'quantity' => $item->quantity,
                    'value' => $item->unit_price
                ];
            })->toArray();
        }

        return $validated;
    }

    /**
     * Process Delhivery webhook
     */
    private function processDelhiveryWebhook(array $data): void
    {
        // Extract tracking data
        $trackingNumber = $data['waybill'] ?? null;
        $status = $data['status'] ?? null;

        if (!$trackingNumber) return;

        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if ($shipment) {
            $shipment->status = $this->mapDelhiveryStatus($status);
            $shipment->last_tracked_at = now();

            if ($status === 'Delivered') {
                $shipment->delivered_at = now();
            }

            $shipment->save();

            // Record event
            DB::table('shipment_events')->insert([
                'shipment_id' => $shipment->id,
                'event_type' => 'webhook_update',
                'status' => $status,
                'location' => $data['current_location'] ?? '',
                'message' => $data['instructions'] ?? '',
                'raw_data' => json_encode($data),
                'occurred_at' => now(),
                'created_at' => now()
            ]);
        }
    }

    /**
     * Process Bluedart webhook
     */
    private function processBluedartWebhook(array $data): void
    {
        // Implementation for Bluedart webhook processing
        // Similar to Delhivery but with Bluedart-specific field mappings
    }

    /**
     * Process Xpressbees webhook
     */
    private function processXpressbeesWebhook(array $data): void
    {
        // Implementation for Xpressbees webhook processing
        // Similar to Delhivery but with Xpressbees-specific field mappings
    }

    /**
     * Map Delhivery status to internal status
     */
    private function mapDelhiveryStatus(string $status): string
    {
        $statusMap = [
            'Manifested' => 'created',
            'In Transit' => 'in_transit',
            'Dispatched' => 'out_for_delivery',
            'Delivered' => 'delivered',
            'RTO Initiated' => 'rto',
            'RTO Delivered' => 'rto',
            'Lost' => 'failed'
        ];

        return $statusMap[$status] ?? 'in_transit';
    }

    /**
     * Toggle carrier active status
     */
    public function toggleCarrier($carrierId): JsonResponse
    {
        try {
            $carrier = ShippingCarrier::findOrFail($carrierId);
            $carrier->is_active = !$carrier->is_active;
            $carrier->status = $carrier->is_active ? 'active' : 'inactive';
            $carrier->save();

            Log::info('Carrier status toggled', [
                'carrier_id' => $carrierId,
                'new_status' => $carrier->is_active ? 'active' : 'inactive'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Carrier status updated successfully',
                'data' => [
                    'carrier_id' => $carrier->id,
                    'is_active' => $carrier->is_active,
                    'status' => $carrier->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to toggle carrier status', [
                'error' => $e->getMessage(),
                'carrier_id' => $carrierId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update carrier status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test carrier connection
     */
    public function testCarrier($carrierId): JsonResponse
    {
        try {
            $carrier = ShippingCarrier::findOrFail($carrierId);

            // Test connection based on carrier
            $testResult = $this->shippingService->testCarrierConnection($carrier);

            if ($testResult['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Connection test successful',
                    'data' => [
                        'carrier_id' => $carrier->id,
                        'carrier_name' => $carrier->name,
                        'response_time' => $testResult['response_time'] ?? null,
                        'details' => $testResult['details'] ?? null
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Connection test failed',
                    'error' => $testResult['error'] ?? 'Unknown error',
                    'details' => $testResult['details'] ?? null
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Failed to test carrier connection', [
                'error' => $e->getMessage(),
                'carrier_id' => $carrierId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to test carrier connection',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get carrier configuration
     */
    public function getCarrierConfig($carrierId): JsonResponse
    {
        try {
            $carrier = ShippingCarrier::with(['services'])
                ->findOrFail($carrierId);

            return response()->json([
                'success' => true,
                'data' => $carrier
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get carrier config', [
                'error' => $e->getMessage(),
                'carrier_id' => $carrierId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get carrier configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate carrier credentials
     */
    public function validateCredentials(Request $request, $carrierId): JsonResponse
    {
        $request->validate([
            'api_endpoint' => 'sometimes|string|url',
            'api_key' => 'sometimes|string',
            'api_secret' => 'sometimes|string',
            'api_token' => 'sometimes|string',
            'license_key' => 'sometimes|string',
            'login_id' => 'sometimes|string',
            'access_token' => 'sometimes|string',
            'customer_code' => 'sometimes|string',
            'username' => 'sometimes|string',
            'password' => 'sometimes|string',
            'email' => 'sometimes|email',
            'account_id' => 'sometimes|string',
            'client_name' => 'sometimes|string',
            // webhook_url is developer-configured, not editable by admin
        ]);

        try {
            $carrier = ShippingCarrier::findOrFail($carrierId);

            // Create a temporary carrier instance with the new credentials for testing
            $tempCarrier = clone $carrier;

            // Update basic fields
            $tempCarrier->fill($request->only([
                'api_endpoint', 'api_key', 'api_secret', 'client_name'
            ]));

            // Get current config and update credentials
            $config = is_array($tempCarrier->config) ? $tempCarrier->config : json_decode($tempCarrier->config, true) ?? [];

            // Get predefined credential fields for this carrier
            $credentialFieldStructure = $config['credential_fields'] ?? [];
            $allowedCredentialKeys = array_column($credentialFieldStructure, 'key');

            // Update credentials from request
            if (!empty($allowedCredentialKeys)) {
                foreach ($allowedCredentialKeys as $fieldKey) {
                    if ($request->has($fieldKey)) {
                        $config['credentials'][$fieldKey] = $request->input($fieldKey);
                    }
                }
            }

            // Set updated config
            $tempCarrier->config = $config;

            // Test the credentials using the service
            $validationResult = $this->shippingService->validateCarrierCredentials($tempCarrier);

            if ($validationResult['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Credentials validated successfully',
                    'data' => [
                        'carrier_id' => $carrier->id,
                        'carrier_name' => $carrier->name,
                        'validation_details' => $validationResult['details'] ?? null,
                        'response_time' => $validationResult['response_time'] ?? null
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $validationResult['error'] ?? 'Credential validation failed',
                    'details' => $validationResult['details'] ?? null
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Failed to validate carrier credentials', [
                'error' => $e->getMessage(),
                'carrier_id' => $carrierId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to validate credentials',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update carrier configuration
     */
    public function updateCarrierConfig(Request $request, $carrierId): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'display_name' => 'sometimes|string|max:255',
            'api_endpoint' => 'sometimes|string|url',
            'api_key' => 'sometimes|string',
            'api_secret' => 'sometimes|string',
            'api_token' => 'sometimes|string', // For Shadowfax
            'license_key' => 'sometimes|string', // For BlueDart
            'login_id' => 'sometimes|string', // For BlueDart
            'access_token' => 'sometimes|string', // For DTDC
            'customer_code' => 'sometimes|string', // For DTDC
            'username' => 'sometimes|string', // For Ecom Express
            'password' => 'sometimes|string', // For Xpressbees, Ecom Express, Shiprocket
            'email' => 'sometimes|email', // For Xpressbees, Shiprocket
            'account_id' => 'sometimes|string|nullable', // For Xpressbees
            'client_name' => 'sometimes|string', // For Delhivery
            'webhook_url' => 'sometimes|string|url|nullable',
            'is_active' => 'sometimes|boolean',
            'is_primary' => 'sometimes|boolean',
            'test_mode' => 'sometimes|boolean',
            'priority' => 'sometimes|integer|min:0',
            'supported_services' => 'sometimes|array',
            'features' => 'sometimes|array',
            'supported_payment_modes' => 'sometimes|array',
            'max_weight' => 'sometimes|numeric|min:0',
            'max_insurance_value' => 'sometimes|numeric|min:0',
            'cutoff_time' => 'sometimes|string',
            'pickup_days' => 'sometimes|array',
            'delivery_days' => 'sometimes|array',
            'configuration' => 'sometimes|array',
        ]);

        try {
            $carrier = ShippingCarrier::findOrFail($carrierId);

            // Get current config
            $config = is_array($carrier->config) ? $carrier->config : (is_string($carrier->config) ? json_decode($carrier->config, true) : []) ?? [];

            // Update basic carrier fields (keep for backward compatibility)
            $carrier->fill($request->only([
                'name', 'display_name', 'api_endpoint', 'api_key', 'api_secret', 'client_name',
                'is_active', 'is_primary',
                'priority', 'supported_services', 'features', 'supported_payment_modes',
                'max_weight', 'max_insurance_value', 'cutoff_time', 'pickup_days',
                'delivery_days'
            ]));

            // Handle test_mode boolean → api_mode string conversion
            if ($request->has('test_mode')) {
                $carrier->api_mode = $request->boolean('test_mode') ? 'test' : 'live';
            }

            // Update config JSON fields
            if ($request->has('cutoff_time')) {
                $config['cutoff_time'] = $request->input('cutoff_time');
            }

            // Get predefined credential fields for this carrier
            // Admin can ONLY update these fields, not add new ones
            $credentialFieldStructure = $config['credential_fields'] ?? [];
            $allowedCredentialKeys = array_column($credentialFieldStructure, 'key');

            // Update only the ALLOWED credential fields
            if (!empty($allowedCredentialKeys)) {
                foreach ($allowedCredentialKeys as $fieldKey) {
                    if ($request->has($fieldKey)) {
                        $config['credentials'][$fieldKey] = $request->input($fieldKey);

                        // Also set on model for backward compatibility (if column exists)
                        if (in_array($fieldKey, ['api_key', 'api_secret', 'client_name'])) {
                            $carrier->{$fieldKey} = $request->input($fieldKey);
                        }
                    }
                }
            }

            // Save updated config
            $carrier->config = $config;

            // If setting as primary, unset other carriers as primary
            if ($request->has('is_primary') && $request->is_primary) {
                ShippingCarrier::where('id', '!=', $carrierId)
                    ->update(['is_primary' => false]);
            }

            $carrier->save();

            // Refresh carrier to get updated data
            $carrier->refresh();

            // Flatten credentials for frontend response (same as getCarriers)
            $responseCarrier = $carrier->toArray();
            if (isset($config['credentials']) && is_array($config['credentials'])) {
                foreach ($config['credentials'] as $key => $value) {
                    $responseCarrier[$key] = $value;
                }
            }

            Log::info('Carrier configuration updated', [
                'carrier_id' => $carrierId,
                'updated_fields' => array_keys($request->all())
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Carrier configuration updated successfully',
                'data' => $responseCarrier
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update carrier config', [
                'error' => $e->getMessage(),
                'carrier_id' => $carrierId,
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update carrier configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete carrier
     */
    public function deleteCarrier($carrierId): JsonResponse
    {
        try {
            $carrier = ShippingCarrier::findOrFail($carrierId);

            // Check if carrier has active shipments
            $activeShipments = DB::table('shipments')
                ->where('carrier_id', $carrierId)
                ->whereNotIn('status', ['delivered', 'cancelled', 'returned'])
                ->count();

            if ($activeShipments > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete carrier with active shipments',
                    'data' => [
                        'active_shipments' => $activeShipments
                    ]
                ], 400);
            }

            $carrierName = $carrier->name;
            $carrier->delete();

            Log::info('Carrier deleted', [
                'carrier_id' => $carrierId,
                'carrier_name' => $carrierName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Carrier deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete carrier', [
                'error' => $e->getMessage(),
                'carrier_id' => $carrierId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete carrier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync carriers from configuration file
     */
    public function syncFromConfig(Request $request): JsonResponse
    {
        try {
            // Run the seeder to sync carriers from config
            \Artisan::call('db:seed', [
                '--class' => 'ShippingCarrierSeeder',
                '--force' => true
            ]);

            // Get updated carriers
            $carriers = ShippingCarrier::with(['services'])->get();

            // Transform config data for display
            $carriers->transform(function ($carrier) {
                $config = is_array($carrier->config) ? $carrier->config : (is_string($carrier->config) ? json_decode($carrier->config, true) : []) ?? [];
                $carrier->features = $config['features'] ?? [];
                $carrier->services = $config['services'] ?? [];
                $carrier->pickup_days = $config['pickup_days'] ?? [];
                $carrier->webhook_url = $config['webhook_url'] ?? '';
                return $carrier;
            });

            return response()->json([
                'success' => true,
                'message' => 'Carriers synced from configuration successfully',
                'data' => $carriers
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync carriers from config: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync carriers from configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
