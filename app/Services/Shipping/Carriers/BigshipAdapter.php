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

                // BigShip returns token in data.token format
                if (isset($data['data']['token'])) {
                    Log::info('BigShip authentication successful');
                    return $data['data']['token'];
                }

                // Fallback to root level token (backward compatibility)
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

            $shipmentCategory = $shipment['shipment_category'] ?? 'b2c';

            // Get invoice amount from either invoice_amount or order_value fields
            $invoiceAmount = $shipment['invoice_amount'] ?? $shipment['order_value'] ?? 0;

            // Get dimensions - support both individual fields and dimensions array
            $dimensions = $shipment['dimensions'] ?? [];
            $length = $shipment['length'] ?? $dimensions['length'] ?? 10;
            $width = $shipment['width'] ?? $dimensions['width'] ?? 10;
            $height = $shipment['height'] ?? $dimensions['height'] ?? 10;

            $payload = [
                'shipment_category' => $shipmentCategory,
                'payment_type' => $shipment['payment_mode'] === 'cod' ? 'COD' : 'Prepaid',
                'pickup_pincode' => $shipment['pickup_pincode'],
                'destination_pincode' => $shipment['delivery_pincode'],
                'shipment_invoice_amount' => $invoiceAmount,
                'risk_type' => $shipmentCategory === 'b2b' ? ($shipment['risk_type'] ?? 'OwnerRisk') : '',
                'box_details' => [
                    [
                        'each_box_dead_weight' => $shipment['billable_weight'] ?? $shipment['weight'] ?? 1,
                        'each_box_length' => $length,
                        'each_box_width' => $width,
                        'each_box_height' => $height,
                        'box_count' => 1
                    ]
                ]
            ];

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
                            'code' => $rateData['courier_id'] ?? 'STANDARD',  // Changed from service_code
                            'name' => $rateData['courier_name'] ?? 'Standard Delivery',  // Changed from service_name
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
                            'zone' => $rateData['zone'] ?? null,
                            'features' => ['tracking'],  // Add features array
                            'tracking_available' => true
                        ];
                    }

                    return [
                        'success' => true,
                        'services' => $rates  // Use 'services' key for consistency with MultiCarrierShippingService
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
                'services' => []
            ];

        } catch (\Exception $e) {
            Log::error('BigShip getRates error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error fetching rates: ' . $e->getMessage(),
                'services' => []
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

            // Get warehouse ID - BigShip requires pre-registered warehouse ID
            $pickupAddress = $data['pickup_address'] ?? [];
            $warehouseId = $data['warehouse_id'] ?? ($pickupAddress['warehouse_id'] ?? null);

            Log::info('BigShip createShipment', [
                'warehouse_id' => $warehouseId,
                'order_id' => $data['order_id'] ?? null
            ]);

            // Ensure warehouse ID is provided
            if (!$warehouseId) {
                throw new \Exception('Warehouse ID is required for BigShip shipments. Please select a registered warehouse.');
            }

            // Split name into first and last (BigShip requires both, last must be 3-25 chars)
            $fullName = $data['delivery_address']['name'] ?? 'Customer Name';
            $nameParts = explode(' ', trim($fullName), 2);
            $firstName = $nameParts[0] ?? 'Customer';
            $lastName = $nameParts[1] ?? 'Name'; // Default last name if not provided

            // Ensure last name meets minimum length
            if (strlen($lastName) < 3) {
                $lastName = 'Name'; // Default 4-char last name
            }
            if (strlen($lastName) > 25) {
                $lastName = substr($lastName, 0, 25);
            }

            // Ensure address_line1 is 10-50 characters
            $addressLine1 = $data['delivery_address']['address_1'] ?? '';
            if (strlen($addressLine1) < 10) {
                // Pad with address_2 or city if too short
                $addressLine1 .= ', ' . ($data['delivery_address']['city'] ?? 'India');
            }
            if (strlen($addressLine1) < 10) {
                $addressLine1 = str_pad($addressLine1, 10, ' '); // Pad to minimum
            }
            if (strlen($addressLine1) > 50) {
                $addressLine1 = substr($addressLine1, 0, 50);
            }

            $payload = [
                'shipment_category' => $data['service_type'] === 'b2b' ? 'b2b' : 'b2c',
                'warehouse_detail' => [
                    'pickup_location_id' => $warehouseId,
                    'return_location_id' => $warehouseId
                ],
                'consignee_detail' => [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'company_name' => '',
                    'contact_number_primary' => $data['delivery_address']['phone'] ?? '',
                    'contact_number_secondary' => '',
                    'email_id' => '',
                    'consignee_address' => [
                        'address_line1' => $addressLine1,
                        'address_line2' => $data['delivery_address']['address_2'] ?? '',
                        'address_landmark' => '',
                        'pincode' => $data['delivery_address']['pincode'] ?? ''
                    ]
                ]
            ];

            // Ensure invoice_id is max 25 characters (BigShip requirement)
            $invoiceId = $data['order_id'] ?? uniqid('INV');
            if (strlen($invoiceId) > 25) {
                $invoiceId = substr($invoiceId, -25); // Take last 25 characters
            }

            $payload['order_detail'] = [
                'invoice_date' => now()->toISOString(),
                'invoice_id' => $invoiceId,
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
                'risk_type' => '', // Empty for B2C
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

    /**
     * Get warehouse requirement type for BigShip
     *
     * BigShip requires pre-registered warehouse IDs obtained from their API.
     * Warehouses must be registered via POST /api/warehouse/add first.
     *
     * @return string 'registered_id'
     */
    public function getWarehouseRequirementType(): string
    {
        return 'registered_id';
    }
}
