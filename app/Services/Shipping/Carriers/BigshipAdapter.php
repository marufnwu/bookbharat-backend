<?php

namespace App\Services\Shipping\Carriers;

use App\Services\Shipping\Contracts\CarrierAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BigshipAdapter implements CarrierAdapterInterface
{
    protected array $config;
    protected string $baseUrl;
    protected string $userName;
    protected string $password;
    protected string $accessKey;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->baseUrl = 'https://api.bigship.in/api';
        $this->userName = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->accessKey = $config['access_key'] ?? '';
    }

    /**
     * Get authentication token from BigShip
     */
    protected function getAuthToken(): string
    {
        $cacheKey = 'bigship_auth_token_' . md5($this->userName);

        return Cache::remember($cacheKey, 7200, function () { // Cache for 2 hours (tokens expire in 12 hours)
            $response = Http::post($this->baseUrl . '/login/user', [
                'user_name' => $this->userName,
                'password' => $this->password,
                'access_key' => $this->accessKey
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['token'])) {
                    Log::info('BigShip authentication successful');
                    return $data['token'];
                }
            }

            Log::error('BigShip authentication failed', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);

            throw new \Exception('BigShip authentication failed: ' . ($response->json()['message'] ?? 'Unknown error'));
        });
    }

    /**
     * Get shipping rates from BigShip
     */
    public function getRates(array $shipment): array
    {
        try {
            $token = $this->getAuthToken();

            $payload = [
                'shipment_category' => $shipment['shipment_category'] ?? 'b2c',
                'payment_type' => $shipment['payment_mode'] === 'cod' ? 'COD' : 'Prepaid',
                'pickup_pincode' => $shipment['pickup_pincode'],
                'destination_pincode' => $shipment['delivery_pincode'],
                'shipment_invoice_amount' => $shipment['invoice_amount'] ?? 0,
                'box_details' => [
                    [
                        'each_box_dead_weight' => $shipment['billable_weight'] ?? $shipment['weight'] ?? 1,
                        'each_box_length' => $shipment['length'] ?? 10,
                        'each_box_width' => $shipment['width'] ?? 10,
                        'each_box_height' => $shipment['height'] ?? 10,
                        'box_count' => 1
                    ]
                ]
            ];

            // Add risk_type for B2B shipments
            if (($shipment['shipment_category'] ?? 'b2c') === 'b2b') {
                $payload['risk_type'] = $shipment['risk_type'] ?? 'OwnerRisk';
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/calculator', $payload);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['data']) && is_array($data['data'])) {
                    $rates = [];

                    foreach ($data['data'] as $rateData) {
                        $rates[] = [
                            'service_code' => $rateData['courier_id'] ?? 'STANDARD',
                            'service_name' => $rateData['courier_name'] ?? 'Standard Delivery',
                            'base_charge' => $rateData['courier_charge'] ?? 0,
                            'fuel_surcharge' => 0,
                            'gst' => 0,
                            'cod_charge' => $rateData['total_shipping_charges'] - $rateData['courier_charge'] ?? 0,
                            'other_charges' => $rateData['other_additional_charges'] ?? [],
                            'total_charge' => $rateData['total_shipping_charges'] ?? 0,
                            'delivery_days' => $rateData['tat'] ?? 3,
                            'estimated_delivery_date' => now()->addDays($rateData['tat'] ?? 3)->format('Y-m-d'),
                            'courier_id' => $rateData['courier_id'] ?? null,
                            'billable_weight' => $rateData['billable_weight'] ?? 0,
                            'zone' => $rateData['zone'] ?? null
                        ];
                    }

                    return [
                        'success' => true,
                        'rates' => $rates
                    ];
                }
            }

            Log::error('BigShip rate API failed', [
                'payload' => $payload,
                'response' => $response->json(),
                'status' => $response->status()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fetch rates from BigShip',
                'rates' => []
            ];

        } catch (\Exception $e) {
            Log::error('BigShip getRates error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error fetching rates: ' . $e->getMessage(),
                'rates' => []
            ];
        }
    }

    /**
     * Create shipment with BigShip
     */
    public function createShipment(array $data): array
    {
        try {
            $token = $this->getAuthToken();

            // Get warehouse details
            $pickupAddress = $data['pickup_address'] ?? [];
            $warehouses = $this->getRegisteredWarehouses();

            if ($warehouses['success'] && !empty($warehouses['warehouses'])) {
                // Find warehouse by ID or use first available
                $warehouseId = $data['warehouse_id'] ?? null;
                $warehouse = null;

                if ($warehouseId) {
                    foreach ($warehouses['warehouses'] as $wh) {
                        if (($wh['warehouse_id'] ?? $wh['id']) == $warehouseId) {
                            $warehouse = $wh;
                            break;
                        }
                    }
                }

                if (!$warehouse) {
                    $warehouse = $warehouses['warehouses'][0]; // Use first warehouse
                }

                $warehouseId = $warehouse['warehouse_id'] ?? null;
            }

            $payload = [
                'shipment_category' => $data['service_type'] === 'b2b' ? 'b2b' : 'b2c',
                'warehouse_detail' => [
                    'pickup_location_id' => $warehouseId,
                    'return_location_id' => $warehouseId
                ],
                'consignee_detail' => [
                    'first_name' => $data['delivery_address']['name'] ?? 'Unknown',
                    'last_name' => '',
                    'company_name' => '',
                    'contact_number_primary' => $data['delivery_address']['phone'] ?? '',
                    'contact_number_secondary' => '',
                    'email_id' => '',
                    'consignee_address' => [
                        'address_line1' => $data['delivery_address']['address_1'] ?? '',
                        'address_line2' => $data['delivery_address']['address_2'] ?? '',
                        'address_landmark' => '',
                        'pincode' => $data['delivery_address']['pincode'] ?? ''
                    ]
                ],
                'order_detail' => [
                    'invoice_date' => now()->toISOString(),
                    'invoice_id' => $data['order_id'] ?? uniqid('INV'),
                    'payment_type' => $data['payment_mode'] === 'cod' ? 'COD' : 'Prepaid',
                    'shipment_invoice_amount' => $data['package_details']['value'] ?? 0,
                    'total_collectable_amount' => $data['cod_amount'] ?? 0,
                    'box_details' => [
                        [
                            'each_box_dead_weight' => $data['package_details']['weight'] ?? 1,
                            'each_box_length' => $data['package_details']['length'] ?? 10,
                            'each_box_width' => $data['package_details']['width'] ?? 10,
                            'each_box_height' => $data['package_details']['height'] ?? 10,
                            'each_box_invoice_amount' => $data['package_details']['value'] ?? 0,
                            'each_box_collectable_amount' => $data['cod_amount'] ?? 0,
                            'box_count' => $data['package_details']['quantity'] ?? 1,
                            'product_details' => [
                                [
                                    'product_category' => 'Others',
                                    'product_sub_category' => 'Books',
                                    'product_name' => 'Books',
                                    'product_quantity' => $data['package_details']['quantity'] ?? 1,
                                    'each_product_invoice_amount' => $data['package_details']['value'] ?? 0,
                                    'each_product_collectable_amount' => $data['cod_amount'] ?? 0,
                                    'hsn' => ''
                                ]
                            ]
                        ]
                    ],
                    'ewaybill_number' => '',
                    'document_detail' => [
                        'invoice_document_file' => '',
                        'ewaybill_document_file' => ''
                    ]
                ]
            ];

            // Add risk_type for B2B shipments
            if ($payload['shipment_category'] === 'b2b') {
                $payload['order_detail']['risk_type'] = $data['risk_type'] ?? 'OwnerRisk';
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/order/add/single', $payload);

            if ($response->successful()) {
                $result = $response->json();

                if (isset($result['data'])) {
                    return [
                        'success' => true,
                        'tracking_number' => $result['data'] ?? null,
                        'carrier_reference' => $result['data'] ?? null,
                        'label_url' => null,
                        'pickup_date' => now()->format('Y-m-d'),
                        'expected_delivery' => now()->addDays(3)->format('Y-m-d'),
                        'rates' => []
                    ];
                }
            }

            Log::error('BigShip createShipment failed', [
                'payload' => $payload,
                'response' => $response->json(),
                'status' => $response->status()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create shipment with BigShip',
                'error' => $response->json()['message'] ?? 'Unknown error'
            ];

        } catch (\Exception $e) {
            Log::error('BigShip createShipment error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error creating shipment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check serviceability with BigShip
     */
    public function checkServiceability(string $pickupPincode, string $deliveryPincode, string $paymentMode): bool
    {
        try {
            $token = $this->getAuthToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/calculator', [
                'shipment_category' => 'b2c',
                'payment_type' => $paymentMode === 'cod' ? 'COD' : 'Prepaid',
                'pickup_pincode' => $pickupPincode,
                'destination_pincode' => $deliveryPincode,
                'shipment_invoice_amount' => 1000,
                'box_details' => [
                    [
                        'each_box_dead_weight' => 1,
                        'each_box_length' => 10,
                        'each_box_width' => 10,
                        'each_box_height' => 10,
                        'box_count' => 1
                    ]
                ]
            ]);

            return $response->successful() && isset($response->json()['data']);

        } catch (\Exception $e) {
            Log::error('BigShip checkServiceability error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Track shipment with BigShip
     */
    public function trackShipment(string $trackingNumber): array
    {
        try {
            $token = $this->getAuthToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token
            ])->get($this->baseUrl . '/tracking', [
                'tracking_type' => 'awb',
                'tracking_id' => $trackingNumber
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['data']['order_detail'])) {
                    $orderDetail = $data['data']['order_detail'];
                    $scanHistories = $data['data']['scan_histories'] ?? [];

                    $events = array_map(function($scan) {
                        return [
                            'date' => $scan['scan_datetime'] ?? '',
                            'status' => $scan['scan_status'] ?? '',
                            'description' => $scan['scan_remarks'] ?? '',
                            'location' => $scan['scan_location'] ?? ''
                        ];
                    }, $scanHistories);

                    return [
                        'success' => true,
                        'tracking_number' => $trackingNumber,
                        'status' => $this->mapTrackingStatus($orderDetail['current_tracking_status'] ?? ''),
                        'status_description' => $orderDetail['current_tracking_status'] ?? '',
                        'current_location' => '',
                        'events' => $events
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Tracking information not found',
                'tracking_number' => $trackingNumber,
                'status' => 'unknown',
                'status_description' => 'Unable to retrieve tracking information',
                'events' => []
            ];

        } catch (\Exception $e) {
            Log::error('BigShip trackShipment error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error tracking shipment: ' . $e->getMessage(),
                'tracking_number' => $trackingNumber,
                'status' => 'unknown',
                'events' => []
            ];
        }
    }

    /**
     * Cancel shipment with BigShip
     */
    public function cancelShipment(string $trackingNumber): bool
    {
        try {
            $token = $this->getAuthToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->put($this->baseUrl . '/order/cancel', [
                $trackingNumber
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('BigShip cancelShipment error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Schedule pickup (not directly supported by BigShip, return false)
     */
    public function schedulePickup(array $pickup): array
    {
        return [
            'success' => false,
            'message' => 'Pickup scheduling not supported by BigShip'
        ];
    }

    /**
     * Get rate async for parallel processing
     */
    public function getRateAsync(array $shipment): \GuzzleHttp\Promise\PromiseInterface
    {
        // For now, return a resolved promise with synchronous result
        // In production, this should make actual async HTTP calls
        $result = $this->getRates($shipment);

        $promise = new \GuzzleHttp\Promise\Promise(function () use (&$promise, $result) {
            $promise->resolve($result);
        });

        return $promise;
    }

    /**
     * Print shipping label
     */
    public function printLabel(string $trackingNumber): string
    {
        try {
            $token = $this->getAuthToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token
            ])->post($this->baseUrl . '/shipment/data', [
                'shipment_data_id' => 2, // Label
                'system_order_id' => $trackingNumber
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data']['res_FileContent'] ?? '';
            }

        } catch (\Exception $e) {
            Log::error('BigShip printLabel error', ['error' => $e->getMessage()]);
        }

        return '';
    }

    /**
     * Validate BigShip credentials
     */
    public function validateCredentials(): array
    {
        try {
            $token = $this->getAuthToken();
            return [
                'success' => true,
                'message' => 'Credentials validated successfully',
                'details' => ['token_generated' => true]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Credential validation failed: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Get registered warehouses from BigShip
     */
    public function getRegisteredWarehouses(): array
    {
        try {
            $token = $this->getAuthToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token
            ])->get($this->baseUrl . '/warehouse/get/list', [
                'page_index' => 1,
                'page_size' => 100
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['data']['result_data'])) {
                    $warehouses = array_map(function($warehouse) {
                        return [
                            'id' => $warehouse['warehouse_id'] ?? $warehouse['warehouse_name'] ?? null,
                            'name' => $warehouse['warehouse_name'] ?? 'Unknown Warehouse',
                            'carrier_warehouse_name' => $warehouse['warehouse_name'] ?? 'Unknown Warehouse',
                            'address' => $warehouse['address_line1'] ?? '',
                            'city' => '',
                            'pincode' => $warehouse['address_pincode'] ?? '',
                            'phone' => $warehouse['warehouse_contact_number_primary'] ?? '',
                            'is_enabled' => true,
                            'is_registered' => true
                        ];
                    }, $data['data']['result_data']);

                    return [
                        'success' => true,
                        'warehouses' => $warehouses
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Failed to fetch warehouses',
                'warehouses' => []
            ];

        } catch (\Exception $e) {
            Log::error('BigShip getRegisteredWarehouses error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error fetching warehouses: ' . $e->getMessage(),
                'warehouses' => []
            ];
        }
    }

    /**
     * Normalize registered warehouses to standard format
     */
    public function normalizeRegisteredWarehouses(array $warehouses): array
    {
        return array_map(function($warehouse) {
            return [
                'id' => $warehouse['warehouse_id'] ?? $warehouse['warehouse_name'] ?? null,
                'name' => $warehouse['warehouse_name'] ?? 'Primary Warehouse',
                'carrier_warehouse_name' => $warehouse['warehouse_name'] ?? 'Primary Warehouse',
                'address' => $warehouse['address_line1'] ?? '',
                'city' => '',
                'pincode' => $warehouse['address_pincode'] ?? '',
                'phone' => $warehouse['warehouse_contact_number_primary'] ?? '',
                'is_enabled' => true,
                'is_registered' => true
            ];
        }, $warehouses);
    }

    /**
     * Map BigShip tracking status to standard format
     */
    protected function mapTrackingStatus(string $bigshipStatus): string
    {
        $statusMap = [
            'Pickup Scheduled' => 'pickup_scheduled',
            'Not Picked' => 'pickup_failed',
            'Cancelled' => 'cancelled',
            'In-Transit' => 'in_transit',
            'Out for Delivery' => 'out_for_delivery',
            'Delivered' => 'delivered',
            'Undelivered' => 'delivery_failed',
            'RTO In Transit' => 'rto_in_transit',
            'RTO Delivered' => 'rto_delivered',
            'Lost' => 'lost'
        ];

        return $statusMap[$bigshipStatus] ?? 'unknown';
    }
}
