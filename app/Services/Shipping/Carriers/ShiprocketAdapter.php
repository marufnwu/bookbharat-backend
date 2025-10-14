<?php

namespace App\Services\Shipping\Carriers;

use App\Services\Shipping\Contracts\CarrierAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ShiprocketAdapter implements CarrierAdapterInterface
{
    protected array $config;
    protected string $baseUrl;
    protected string $authToken;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->baseUrl = $config['api_endpoint'] ?? 'https://apiv2.shiprocket.in/v1/external';

        // Only get auth token if credentials are configured
        // This prevents errors during adapter creation for validation
        if (!empty($config['email']) && !empty($config['password'])) {
            try {
                $this->authToken = $this->getAuthToken();
            } catch (\Exception $e) {
                Log::warning('Shiprocket authentication skipped during construction', [
                    'error' => $e->getMessage()
                ]);
                $this->authToken = '';
            }
        } else {
            $this->authToken = '';
        }
    }

    /**
     * Get authentication token (cached for 10 days)
     */
    protected function getAuthToken(): string
    {
        $cacheKey = 'shiprocket_auth_token_' . md5($this->config['email'] ?? 'default');

        return Cache::remember($cacheKey, 60 * 60 * 24 * 10, function () {
            $response = Http::post("{$this->baseUrl}/auth/login", [
                'email' => $this->config['email'] ?? '',
                'password' => $this->config['password'] ?? $this->config['api_secret'] ?? '',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['token'])) {
                    Log::info('Shiprocket authentication successful');
                    return $data['token'];
                }
            }

            Log::error('Shiprocket authentication failed', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);

            throw new \Exception('Failed to authenticate with Shiprocket: ' . ($response->json()['message'] ?? 'Unknown error'));
        });
    }

    /**
     * Get headers with authentication
     */
    protected function getHeaders(): array
    {
        // Ensure we have a token, get it if needed
        if (empty($this->authToken)) {
            $this->authToken = $this->getAuthToken();
        }

        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->authToken,
        ];
    }

    /**
     * Create a shipment (implements CarrierAdapterInterface)
     */
    public function createShipment(array $data): array
    {
        try {
            $deliveryAddress = $data['delivery_address'] ?? [];
            $pickupAddress = $data['pickup_address'] ?? [];
            $packageDetails = $data['package_details'] ?? [];

            // First, create the order in Shiprocket
            // Shiprocket requires pickup_location to be a pre-registered location name
            // Use 'Primary' as default since it's usually the first warehouse
            $pickupLocationName = 'Primary';
            if (isset($pickupAddress['name']) && !empty($pickupAddress['name'])) {
                // Only use custom name if it's not a generic "Main Warehouse"
                // which likely doesn't exist in Shiprocket
                if (!in_array(strtolower($pickupAddress['name']), ['main warehouse', 'default warehouse', 'warehouse'])) {
                    $pickupLocationName = $pickupAddress['name'];
                }
            }

            $orderPayload = [
                'order_id' => $data['order_id'] ?? uniqid('SR'),
                'order_date' => date('Y-m-d H:i'),
                'pickup_location' => $pickupLocationName,
                'billing_customer_name' => $deliveryAddress['name'] ?? 'Customer',
                'billing_last_name' => '',
                'billing_address' => $deliveryAddress['address_1'] ?? '',
                'billing_address_2' => $deliveryAddress['address_2'] ?? '',
                'billing_city' => $deliveryAddress['city'] ?? '',
                'billing_pincode' => $deliveryAddress['pincode'] ?? '',
                'billing_state' => $deliveryAddress['state'] ?? '',
                'billing_country' => 'India',
                'billing_email' => $data['customer_details']['email'] ?? '',
                'billing_phone' => $deliveryAddress['phone'] ?? '',
                'shipping_is_billing' => true,
                'order_items' => [[
                    'name' => $packageDetails['description'] ?? 'Books',
                    'sku' => 'BOOK-' . time(),
                    'units' => $packageDetails['quantity'] ?? 1,
                    'selling_price' => $packageDetails['value'] ?? 100,
                ]],
                'payment_method' => $data['payment_mode'] === 'cod' ? 'COD' : 'Prepaid',
                'sub_total' => $packageDetails['value'] ?? 100,
                'length' => $packageDetails['length'] ?? 10,
                'breadth' => $packageDetails['width'] ?? 10,
                'height' => $packageDetails['height'] ?? 10,
                'weight' => $packageDetails['weight'] ?? 1,
            ];

            Log::info('Shiprocket creating order', ['payload' => $orderPayload]);

            $orderResponse = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/orders/create/adhoc", $orderPayload);

            if (!$orderResponse->successful()) {
                $errorMessage = $orderResponse->json()['message'] ?? 'Order creation failed';
                $errorDetails = $orderResponse->json();

                Log::error('Shiprocket order creation failed', [
                    'status' => $orderResponse->status(),
                    'error' => $errorMessage,
                    'response' => $errorDetails
                ]);

                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'details' => $errorDetails
                ];
            }

            $orderData = $orderResponse->json();
            $shipmentId = $orderData['shipment_id'] ?? null;

            Log::info('Shiprocket order created', [
                'response' => $orderData,
                'shipment_id' => $shipmentId
            ]);

            if (!$shipmentId) {
                Log::error('Shiprocket shipment_id not found in response', [
                    'full_response' => $orderData
                ]);
                return [
                    'success' => false,
                    'message' => 'Failed to get shipment ID from Shiprocket',
                    'response' => $orderData
                ];
            }

            // Auto-assign AWB (Shiprocket automatically selects best courier)
            $awbResponse = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/courier/assign/awb", [
                    'shipment_id' => $shipmentId,
                ]);

            if ($awbResponse->successful()) {
                $awbData = $awbResponse->json();
                return [
                    'success' => true,
                    'tracking_number' => $awbData['response']['data']['awb_code'] ?? $shipmentId,
                    'carrier_reference' => $shipmentId,
                    'label_url' => $awbData['response']['data']['label_url'] ?? null,
                    'pickup_date' => now()->format('Y-m-d'),
                    'expected_delivery' => now()->addDays(3)->format('Y-m-d'),
                ];
            }

            // If AWB assignment fails, still return success with shipment ID
            return [
                'success' => true,
                'tracking_number' => $shipmentId,
                'carrier_reference' => $shipmentId,
                'message' => 'Order created, AWB pending',
            ];

        } catch (\Exception $e) {
            Log::error('Shiprocket createShipment error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Error creating shipment: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Track a shipment (implements CarrierAdapterInterface)
     */
    public function trackShipment(string $trackingNumber): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/courier/track/awb/{$trackingNumber}");

            if ($response->successful()) {
                $data = $response->json();
                $tracking = $data['tracking_data'] ?? [];

                if (empty($tracking)) {
                    return [
                        'success' => false,
                        'tracking_number' => $trackingNumber,
                        'status' => 'unknown',
                        'status_description' => 'No tracking data available',
                        'events' => []
                    ];
                }

                $currentStatus = $tracking['shipment_track'][0] ?? [];

                return [
                    'success' => true,
                    'tracking_number' => $trackingNumber,
                    'status' => $this->mapStatus($currentStatus['current_status'] ?? ''),
                    'status_description' => $currentStatus['current_status'] ?? '',
                    'current_location' => $currentStatus['location'] ?? '',
                    'events' => $this->mapTrackingEvents($tracking['shipment_track_activities'] ?? []),
                ];
            }

            return [
                'success' => false,
                'tracking_number' => $trackingNumber,
                'status' => 'unknown',
                'status_description' => $response->json()['message'] ?? 'Tracking failed',
                'events' => []
            ];
        } catch (\Exception $e) {
            Log::error('Shiprocket tracking error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'tracking_number' => $trackingNumber,
                'status' => 'unknown',
                'status_description' => $e->getMessage(),
                'events' => []
            ];
        }
    }

    /**
     * Cancel a shipment (implements CarrierAdapterInterface)
     */
    public function cancelShipment(string $trackingNumber): bool
    {
        try {
            // Shiprocket uses shipment IDs for cancellation
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/orders/cancel", [
                    'ids' => [$trackingNumber],
                ]);

            if ($response->successful()) {
                Log::info('Shiprocket shipment cancelled successfully', [
                    'tracking_number' => $trackingNumber
                ]);
                return true;
            }

            Log::warning('Shiprocket cancellation failed', [
                'tracking_number' => $trackingNumber,
                'response' => $response->json()
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Shiprocket cancellation error', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Schedule a pickup (implements CarrierAdapterInterface)
     */
    public function schedulePickup(array $pickup): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/courier/generate/pickup", [
                    'shipment_id' => $pickup['shipment_ids'] ?? [],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'pickup_scheduled' => $data['pickup_scheduled'] ?? false,
                    'pickup_token' => $data['pickup_token_number'] ?? '',
                    'pickup_status' => $data['pickup_status'] ?? '',
                ];
            }

            return [
                'success' => false,
                'message' => $response->json()['message'] ?? 'Pickup scheduling failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shiprocket schedulePickup error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function checkServiceability(string $pickupPincode, string $deliveryPincode, string $paymentMode): bool
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/courier/serviceability", [
                    'pickup_postcode' => $pickupPincode,
                    'delivery_postcode' => $deliveryPincode,
                    'weight' => 0.5,
                    'cod' => $paymentMode === 'cod' ? 1 : 0,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $couriers = $data['data']['available_courier_companies'] ?? [];
                return !empty($couriers);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Shiprocket serviceability error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Map Shiprocket status to standard status
     */
    protected function mapStatus(string $status): string
    {
        $statusMap = [
            'NEW' => 'created',
            'AWB ASSIGNED' => 'awb_assigned',
            'PICKUP SCHEDULED' => 'pickup_scheduled',
            'PICKED UP' => 'picked_up',
            'IN TRANSIT' => 'in_transit',
            'OUT FOR DELIVERY' => 'out_for_delivery',
            'DELIVERED' => 'delivered',
            'CANCELED' => 'cancelled',
            'RTO INITIATED' => 'return_initiated',
            'RTO DELIVERED' => 'returned',
            'LOST' => 'lost',
        ];

        return $statusMap[strtoupper($status)] ?? 'in_transit';
    }

    /**
     * Map tracking events to standard format
     */
    protected function mapTrackingEvents(array $events): array
    {
        return array_map(function ($event) {
            return [
                'timestamp' => $event['date'] ?? '',
                'status' => $event['activity'] ?? '',
                'location' => $event['location'] ?? '',
                'description' => $event['sr_status'] ?? '',
            ];
        }, $events);
    }

    /**
     * Validate Shiprocket credentials
     */
    public function validateCredentials(): array
    {
        try {
            // Check if required credentials are provided
            if (empty($this->config['email']) || empty($this->config['password'])) {
                return [
                    'success' => false,
                    'error' => 'Email and Password are required',
                    'details' => [
                        'missing_credentials' => [
                            'email' => empty($this->config['email']),
                            'password' => empty($this->config['password'])
                        ]
                    ]
                ];
            }

            // Attempt login to validate credentials
            $response = Http::post("{$this->baseUrl}/auth/login", [
                'email' => $this->config['email'],
                'password' => $this->config['password']
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['token'])) {
                    return [
                        'success' => true,
                        'message' => 'Credentials validated successfully',
                        'details' => [
                            'company_id' => $data['company_id'] ?? null,
                            'api_mode' => 'live',
                            'endpoint_tested' => "{$this->baseUrl}/auth/login"
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Login successful but no access token received',
                        'details' => [
                            'response' => $data,
                            'endpoint_tested' => "{$this->baseUrl}/auth/login"
                        ]
                    ];
                }
            } elseif ($response->status() === 401) {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password',
                    'details' => [
                        'http_status' => $response->status(),
                        'endpoint_tested' => "{$this->baseUrl}/auth/login"
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Authentication endpoint unreachable',
                    'details' => [
                        'http_status' => $response->status(),
                        'response' => $response->body(),
                        'endpoint_tested' => "{$this->baseUrl}/auth/login"
                    ]
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Network error or invalid endpoint configuration',
                'details' => [
                    'exception' => $e->getMessage(),
                    'endpoint_tested' => "{$this->baseUrl}/auth/login"
                ]
            ];
        }
    }

    /**
     * Get shipping rates (implements CarrierAdapterInterface)
     */
    public function getRates(array $shipment): array
    {
        try {
            $invoiceAmount = $shipment['invoice_amount'] ?? $shipment['order_value'] ?? 0;

            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/courier/serviceability", [
                    'pickup_postcode' => $shipment['pickup_pincode'],
                    'delivery_postcode' => $shipment['delivery_pincode'],
                    'weight' => $shipment['billable_weight'] ?? $shipment['weight'] ?? 1,
                    'cod' => $shipment['payment_mode'] === 'cod' ? 1 : 0,
                    'declared_value' => $invoiceAmount,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $couriers = $data['data']['available_courier_companies'] ?? [];

                $services = [];
                foreach ($couriers as $courier) {
                    $deliveryDays = intval($courier['estimated_delivery_days'] ?? 3);

                    $services[] = [
                        'code' => $courier['courier_company_id'] ?? 'STANDARD',
                        'name' => $courier['courier_name'] ?? 'Standard Delivery',
                        'base_charge' => floatval($courier['rate'] ?? 0),
                        'fuel_surcharge' => 0,
                        'gst' => 0,
                        'cod_charge' => floatval($courier['cod_charges'] ?? 0),
                        'insurance_charge' => 0,
                        'other_charges' => floatval($courier['other_charges'] ?? 0),
                        'total_charge' => floatval($courier['rate'] ?? 0),
                        'delivery_days' => $deliveryDays,
                        'estimated_delivery_date' => now()->addDays($deliveryDays)->format('Y-m-d'),
                        'features' => ['tracking', 'doorstep_delivery'],
                        'tracking_available' => true
                    ];
                }

                return [
                    'success' => true,
                    'services' => $services
                ];
            }

            Log::error('Shiprocket rate API failed', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fetch rates from Shiprocket',
                'services' => []
            ];

        } catch (\Exception $e) {
            Log::error('Shiprocket getRates error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error fetching rates: ' . $e->getMessage(),
                'services' => []
            ];
        }
    }

    /**
     * Get rate async for parallel processing
     */
    public function getRateAsync(array $shipment): \GuzzleHttp\Promise\PromiseInterface
    {
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
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/courier/generate/label", [
                    'shipment_id' => [$trackingNumber],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['label_url'] ?? '';
            }

            return '';
        } catch (\Exception $e) {
            Log::error('Shiprocket printLabel error', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Get warehouse requirement type for Shiprocket
     *
     * Shiprocket can use full address or pre-registered pickup locations
     *
     * @return string 'full_address'
     */
    public function getWarehouseRequirementType(): string
    {
        return 'full_address';
    }

    /**
     * Get registered pickup locations from Shiprocket
     */
    public function getRegisteredWarehouses(): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/settings/company/pickup");

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['data']) && isset($data['data']['shipping_address'])) {
                    $addresses = $data['data']['shipping_address'];

                    Log::info('Shiprocket pickup locations fetched', [
                        'count' => count($addresses)
                    ]);

                    return [
                        'success' => true,
                        'warehouses' => $addresses
                    ];
                }
            }

            Log::warning('Shiprocket pickup locations fetch failed', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'warehouses' => []
            ];

        } catch (\Exception $e) {
            Log::error('Shiprocket getRegisteredWarehouses error', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'warehouses' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Normalize Shiprocket registered warehouses
     */
    public function normalizeRegisteredWarehouses(array $warehouses): array
    {
        return array_map(function($warehouse) {
            return [
                'id' => $warehouse['id'] ?? null,
                'name' => $warehouse['pickup_location'] ?? $warehouse['nickname'] ?? 'Unknown',
                'carrier_warehouse_name' => $warehouse['pickup_location'] ?? $warehouse['nickname'] ?? 'Unknown',
                'address' => $warehouse['address'] ?? '',
                'city' => $warehouse['city'] ?? '',
                'pincode' => $warehouse['pin_code'] ?? '',
                'phone' => $warehouse['phone'] ?? '',
                'is_enabled' => true,
                'carrier_code' => 'SHIPROCKET',
                'is_registered' => true
            ];
        }, $warehouses);
    }
}
