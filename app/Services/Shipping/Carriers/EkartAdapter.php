<?php

namespace App\Services\Shipping\Carriers;

use App\Services\Shipping\Contracts\CarrierAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EkartAdapter implements CarrierAdapterInterface
{
    protected array $config;
    protected string $baseUrl;
    protected string $clientId;
    protected string $username;
    protected string $password;
    protected ?string $cachedToken = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->baseUrl = $config['api_endpoint'] ?? 'https://app.elite.ekartlogistics.in';
        $this->clientId = $config['client_id'] ?? '';
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
    }

    /**
     * Get authentication token (cached for 24 hours)
     */
    protected function getAuthToken(): string
    {
        $cacheKey = "ekart_token_{$this->clientId}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            $response = Http::post("{$this->baseUrl}/integrations/v2/auth/token/{$this->clientId}", [
                'username' => $this->username,
                'password' => $this->password
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['access_token'] ?? null;
                $expiresIn = $data['expires_in'] ?? 86400; // Default 24 hours

                if ($token) {
                    // Cache for slightly less than expires_in to be safe
                    Cache::put($cacheKey, $token, now()->addSeconds($expiresIn - 300));
                    return $token;
                }
            }

            throw new \Exception('Failed to get auth token: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Ekart authentication failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get shipping rates from Ekart
     */
    public function getRates(array $shipment): array
    {
        try {
            $token = $this->getAuthToken();

            // Ekart requires dimensions, use defaults if not provided
            $dimensions = $shipment['dimensions'] ?? ['length' => 30, 'width' => 20, 'height' => 10];

            // Prepare payment mode - Ekart uses: COD, Pickup, or Prepaid (capital first letter)
            $paymentMode = $shipment['payment_mode'] === 'cod' ? 'COD' : 'Prepaid';

            $requestData = [
                'pickupPincode' => (int) $shipment['pickup_pincode'],
                'dropPincode' => (int) $shipment['delivery_pincode'],
                'weight' => (int) ($shipment['billable_weight'] * 1000), // Convert to grams
                'length' => (int) ($dimensions['length'] ?? 30),
                'width' => (int) ($dimensions['width'] ?? 20),
                'height' => (int) ($dimensions['height'] ?? 10),
                'serviceType' => 'SURFACE', // Default to surface
                'invoiceAmount' => $shipment['order_value'] ?? 0,
                'paymentMode' => $paymentMode,
                'codAmount' => $shipment['payment_mode'] === 'cod' ? ($shipment['cod_amount'] ?? $shipment['order_value']) : 0, // Required even for prepaid (0 for prepaid)
                'billingClientType' => 'EXISTING_CLIENT', // Required: PROSPECTIVE_CLIENT, EXISTING_CLIENT, or EXISTING_CLIENT_CUSTOM_RATE_SNAPSHOT
                'shippingDirection' => 'FORWARD'
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/data/pricing/estimate', $requestData);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Ekart rate API response', ['data' => $data]);
                return $this->formatEkartRates($data, $shipment);
            }

            Log::error('Ekart rate API failed', [
                'response' => $response->body(),
                'status' => $response->status()
            ]);

            return ['services' => []];

        } catch (\Exception $e) {
            Log::error('Ekart adapter error', ['error' => $e->getMessage()]);
            return ['services' => []];
        }
    }

    /**
     * Format Ekart rate response
     */
    protected function formatEkartRates(array $response, array $shipment): array
    {
        $services = [];

        // Ekart returns a single rate estimate
        if (isset($response['total'])) {
            $total = (float) $response['total'];
            $shippingCharge = (float) ($response['shippingCharge'] ?? 0);
            $fuelSurcharge = (float) ($response['fuelSurcharge'] ?? 0);
            $taxes = (float) ($response['taxes'] ?? 0);
            $codCharge = (float) ($response['codCharge'] ?? 0);

            $services[] = [
                'code' => 'SURFACE',
                'name' => 'Ekart Surface',
                'base_charge' => $shippingCharge,
                'fuel_surcharge' => $fuelSurcharge,
                'gst' => $taxes,
                'cod_charge' => $codCharge,
                'insurance_charge' => 0,
                'other_charges' => (float) ($response['qcCharge'] ?? 0),
                'total_charge' => $total,
                'delivery_days' => $this->estimateDeliveryDays($shipment['pickup_pincode'], $shipment['delivery_pincode']),
                'expected_delivery_date' => now()->addDays(4)->format('Y-m-d'),
                'features' => ['tracking', 'cod', 'doorstep_delivery'],
                'tracking_available' => true
            ];
        }

        return ['services' => $services];
    }

    /**
     * Create shipment with Ekart
     */
    public function createShipment(array $data): array
    {
        try {
            $token = $this->getAuthToken();

            $shipmentData = [
                'order_id' => $data['order_id'],
                'consignee_name' => $data['delivery_address']['name'],
                'consignee_phone' => $data['delivery_address']['phone'],
                'consignee_address' => $data['delivery_address']['address_1'],
                'consignee_pincode' => $data['delivery_address']['pincode'],
                'consignee_city' => $data['delivery_address']['city'],
                'consignee_state' => $data['delivery_address']['state'],
                'payment_mode' => strtoupper($data['payment_mode']), // COD or PREPAID
                'cod_amount' => $data['cod_amount'] ?? 0,
                'invoice_amount' => $data['package_details']['value'] ?? 0,
                'weight' => (int) ($data['package_details']['weight'] * 1000), // Convert to grams
                'length' => (int) ($data['package_details']['length'] ?? 30),
                'width' => (int) ($data['package_details']['width'] ?? 20),
                'height' => (int) ($data['package_details']['height'] ?? 10),
                'quantity' => $data['package_details']['quantity'] ?? 1,
                'product_description' => $data['package_details']['description'] ?? 'Books',

                // Pickup location - Ekart requires only the registered warehouse name
                // The warehouse must be pre-registered with Ekart through their portal
                'pickup_location' => $this->getEkartWarehouseName($data['pickup_address']),

                // Return location - same as pickup for forward shipments
                'return_location' => $this->getEkartWarehouseName($data['pickup_address']),

                // Drop location (customer address)
                'drop_location' => [
                    'name' => $data['delivery_address']['name'],
                    'phone' => $data['delivery_address']['phone'],
                    'address' => $data['delivery_address']['address_1'],
                    'pincode' => $data['delivery_address']['pincode'],
                    'city' => $data['delivery_address']['city'],
                    'state' => $data['delivery_address']['state']
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->put($this->baseUrl . '/api/v1/package/create', $shipmentData);

            if ($response->successful()) {
                $result = $response->json();

        return [
            'success' => true,
                    'tracking_number' => $result['tracking_id'] ?? '',
                    'carrier_reference' => $result['awb_number'] ?? '',
            'label_url' => null,
                    'pickup_date' => now()->addDay()->format('Y-m-d'),
                    'expected_delivery' => now()->addDays(4)->format('Y-m-d')
                ];
            }

            throw new \Exception('Failed to create Ekart shipment: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Ekart create shipment error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Track shipment
     */
    public function trackShipment(string $trackingNumber): array
    {
        try {
            $token = $this->getAuthToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token
            ])->get($this->baseUrl . '/api/v1/track/' . $trackingNumber);

            if ($response->successful()) {
                $data = $response->json();

        return [
                    'status' => $this->mapStatus($data['status'] ?? ''),
                    'current_location' => $data['current_location'] ?? '',
                    'last_updated' => $data['updated_at'] ?? now(),
                    'delivered_at' => $data['status'] === 'DELIVERED' ? ($data['delivered_at'] ?? null) : null,
                    'events' => $this->parseTrackingEvents($data['track'] ?? [])
                ];
            }

            return ['status' => 'unknown', 'events' => []];

        } catch (\Exception $e) {
            Log::error('Ekart tracking error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Cancel shipment
     */
    public function cancelShipment(string $trackingNumber): bool
    {
        try {
            $token = $this->getAuthToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/v1/package/cancel', [
                'awb_numbers' => [$trackingNumber]
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Ekart cancel shipment error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check serviceability
     */
    public function checkServiceability(string $pickupPincode, string $deliveryPincode, string $paymentMode): bool
    {
        try {
            $token = $this->getAuthToken();

            // Using V3 serviceability API - Ekart requires full shipment details even for serviceability check
            // Prepare payment mode
            $ekartPaymentMode = $paymentMode === 'cod' ? 'COD' : 'Prepaid';

            $request = [
                'pickupPincode' => (int) $pickupPincode,
                'dropPincode' => (int) $deliveryPincode,
                'weight' => 500, // Default 500 grams for serviceability check
                'length' => 10,  // Default dimensions
                'width' => 10,
                'height' => 10,
                'invoiceAmount' => 1000, // Default invoice amount
                'paymentMode' => $ekartPaymentMode,
                'serviceType' => 'SURFACE',
                'billingClientType' => 'EXISTING_CLIENT',
                'shippingDirection' => 'FORWARD'
            ];

            // Ekart requires codAmount even for prepaid orders (can be 0 for prepaid)
            if ($paymentMode === 'cod') {
                $request['codAmount'] = 1000; // Default COD amount for serviceability check
            } else {
                $request['codAmount'] = 0; // Required but 0 for prepaid
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/data/v3/serviceability', $request);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Ekart serviceability API response', [
                    'pickup' => $pickupPincode,
                    'delivery' => $deliveryPincode,
                    'data' => $data
                ]);

                // V3 returns an array of serviceability results
                if (is_array($data) && count($data) > 0) {
                    $result = $data[0];

                    // Check if serviceable - the response includes TAT, which means it's serviceable
                    if (isset($result['tat'])) {
                        return true;
                    }
                }
            }

            Log::warning('Ekart serviceability check failed', [
                'pickup' => $pickupPincode,
                'delivery' => $deliveryPincode,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Ekart serviceability check error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Schedule pickup
     */
    public function schedulePickup(array $pickup): array
    {
        try {
            $token = $this->getAuthToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/v1/pickup/schedule', [
                'pickup_date' => $pickup['pickup_date'],
                'pickup_time' => $pickup['pickup_time'] ?? '10:00-18:00',
                'packages_count' => $pickup['packages_count'] ?? 1
            ]);

            if ($response->successful()) {
                $data = $response->json();

        return [
                    'pickup_id' => $data['pickup_id'] ?? uniqid('PU'),
                    'scheduled_time' => $data['pickup_time'] ?? $pickup['pickup_date']
                ];
            }

            throw new \Exception('Failed to schedule pickup');

        } catch (\Exception $e) {
            Log::error('Ekart pickup scheduling error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get rate async (for parallel processing)
     */
    public function getRateAsync(array $shipment): \GuzzleHttp\Promise\PromiseInterface
    {
        $client = new \GuzzleHttp\Client();

        try {
            $token = $this->getAuthToken();
            $dimensions = $shipment['dimensions'] ?? ['length' => 30, 'width' => 20, 'height' => 10];

            return $client->postAsync($this->baseUrl . '/data/pricing/estimate', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'pickupPincode' => (int) $shipment['pickup_pincode'],
                    'dropPincode' => (int) $shipment['delivery_pincode'],
                    'weight' => (int) ($shipment['billable_weight'] * 1000),
                    'length' => (int) ($dimensions['length'] ?? 30),
                    'width' => (int) ($dimensions['width'] ?? 20),
                    'height' => (int) ($dimensions['height'] ?? 10),
                    'serviceType' => 'SURFACE',
                    'invoiceAmount' => $shipment['order_value'] ?? 0,
                    'codAmount' => $shipment['payment_mode'] === 'cod' ? ($shipment['cod_amount'] ?? 0) : 0,
                    'billingClientType' => 'SELLER',
                    'shippingDirection' => 'FORWARD'
                ]
            ]);
        } catch (\Exception $e) {
            // If token fails, return a rejected promise
            $client = new \GuzzleHttp\Client();
            return \GuzzleHttp\Promise\Create::rejectionFor($e);
        }
    }

    /**
     * Print shipping label
     */
    public function printLabel(string $trackingNumber): string
    {
        try {
            $token = $this->getAuthToken();

            // Ekart label endpoint
            return $this->baseUrl . '/api/v1/label/' . $trackingNumber;
        } catch (\Exception $e) {
            Log::error('Ekart label generation error', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Validate Ekart credentials
     */
    public function validateCredentials(): array
    {
        try {
            // Test authentication by getting a token
            $response = Http::post("{$this->baseUrl}/integrations/v2/auth/token/{$this->clientId}", [
                'username' => $this->username,
                'password' => $this->password
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['access_token'])) {
        return [
            'success' => true,
                        'details' => [
                            'message' => 'Ekart credentials are valid',
                            'token_type' => $data['token_type'] ?? 'Bearer',
                            'expires_in' => $data['expires_in'] ?? 86400,
                            'scope' => $data['scope'] ?? '',
                            'endpoint_tested' => "{$this->baseUrl}/integrations/v2/auth/token/{$this->clientId}"
                        ]
                    ];
                }
            } elseif ($response->status() === 401) {
                return [
                    'success' => false,
                    'error' => 'Invalid Client ID or Access Key',
                    'details' => [
                        'http_status' => 401,
                        'endpoint_tested' => "{$this->baseUrl}/integrations/v2/auth/token/{$this->clientId}"
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Authentication failed',
                    'details' => [
                        'http_status' => $response->status(),
                        'response_body' => substr($response->body(), 0, 500),
                        'endpoint_tested' => "{$this->baseUrl}/integrations/v2/auth/token/{$this->clientId}"
                    ]
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Network error or invalid endpoint configuration',
                'details' => [
                    'exception' => $e->getMessage(),
                    'endpoint_tested' => "{$this->baseUrl}/integrations/v2/auth/token/{$this->clientId}"
                ]
            ];
        }

        return ['success' => false, 'error' => 'Unknown error'];
    }

    /**
     * Estimate delivery days based on zones
     */
    protected function estimateDeliveryDays(string $origin, string $destination): int
    {
        $originZone = substr($origin, 0, 2);
        $destZone = substr($destination, 0, 2);

        if ($originZone === $destZone) {
            return 2; // Same state
        }

        // Metro to metro
        $metros = ['11', '12', '40', '56', '60', '70', '80'];
        if (in_array($originZone, $metros) && in_array($destZone, $metros)) {
            return 3;
        }

        return 4; // Default
    }

    /**
     * Map Ekart status to internal status
     */
    protected function mapStatus(string $status): string
    {
        $statusMap = [
            'CREATED' => 'created',
            'PICKED_UP' => 'picked_up',
            'IN_TRANSIT' => 'in_transit',
            'OUT_FOR_DELIVERY' => 'out_for_delivery',
            'DELIVERED' => 'delivered',
            'RTO' => 'rto',
            'CANCELLED' => 'cancelled',
            'LOST' => 'failed'
        ];

        return $statusMap[$status] ?? 'in_transit';
    }

    /**
     * Parse tracking events
     */
    protected function parseTrackingEvents(array $events): array
    {
        $parsed = [];

        foreach ($events as $event) {
            $parsed[] = [
                'timestamp' => $event['timestamp'] ?? '',
                'status' => $event['status'] ?? '',
                'location' => $event['location'] ?? '',
                'message' => $event['remarks'] ?? '',
                'type' => 'status_update'
            ];
        }

        return $parsed;
    }

    /**
     * Get Ekart-registered warehouse name (alias)
     * Ekart requires the warehouse to be pre-registered in their system
     */
    protected function getEkartWarehouseName(array $pickupAddress): string
    {
        // Try to get warehouse name from carrier_warehouse pivot table
        $warehouse = \App\Models\Warehouse::where('name', $pickupAddress['name'] ?? '')
            ->orWhere('pincode', $pickupAddress['pincode'] ?? '')
            ->first();

        if ($warehouse) {
            // Get carrier ID from ShippingCarrier model
            $carrier = \App\Models\ShippingCarrier::where('code', 'EKART')->first();
            if ($carrier) {
                $carrierConfig = $warehouse->getCarrierConfig($carrier->id);
                if ($carrierConfig && !empty($carrierConfig['carrier_warehouse_name'])) {
                    Log::info('Using Ekart registered warehouse alias', [
                        'warehouse' => $warehouse->name,
                        'ekart_alias' => $carrierConfig['carrier_warehouse_name']
                    ]);
                    return $carrierConfig['carrier_warehouse_name'];
                }
            }
        }

        // Fallback to pickup address name
        // Note: This must match the warehouse alias registered in Ekart's system
        Log::warning('No Ekart warehouse mapping found, using fallback', [
            'fallback_name' => $pickupAddress['name'] ?? 'Main Warehouse'
        ]);
        return $pickupAddress['name'] ?? 'Main Warehouse';
    }

    /**
     * Register a new pickup/warehouse address with Ekart
     */
    public function registerAddress(array $address): array
    {
        try {
            $token = $this->getAuthToken();

            $addressData = [
                'alias' => $address['alias'] ?? $address['name'], // Required unique identifier
                'phone' => (int) preg_replace('/[^0-9]/', '', $address['phone']), // Must be 10-digit integer
                'address_line1' => $address['address_1'] ?? $address['address_line_1'],
                'address_line2' => $address['address_2'] ?? $address['address_line_2'] ?? '',
                'pincode' => (int) $address['pincode'],
                'city' => $address['city'],
                'state' => $address['state'],
                'country' => $address['country'] ?? 'India'
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/v2/address', $addressData);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'alias' => $result['alias'] ?? $addressData['alias'],
                    'message' => 'Address registered successfully'
                ];
            }

            Log::error('Ekart address registration failed', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to register address',
                'error' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('Ekart address registration error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Address registration error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get list of registered addresses from Ekart
     */
    public function getRegisteredAddresses(): array
    {
        try {
            $token = $this->getAuthToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/api/v2/addresses');

            if ($response->successful()) {
                $addresses = $response->json();
                return [
                    'success' => true,
                    'addresses' => $addresses,
                    'count' => count($addresses)
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to fetch addresses',
                'addresses' => []
            ];

        } catch (\Exception $e) {
            Log::error('Ekart get addresses error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Error fetching addresses',
                'addresses' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Normalize Ekart address data to standard format
     */
    public function normalizeRegisteredAddresses(array $addresses): array
    {
        return array_map(function($address) {
            return [
                'id' => $address['alias'] ?? $address['name'] ?? null,
                'name' => $address['alias'] ?? $address['name'] ?? 'Unknown',
                'carrier_warehouse_name' => $address['alias'] ?? $address['name'] ?? 'Unknown',
                'address' => $address['address_line1'] ?? $address['address'] ?? '',
                'city' => $address['city'] ?? '',
                'pincode' => $address['pincode'] ?? $address['pin'] ?? '',
                'phone' => $address['phone'] ?? '',
                'is_enabled' => true,
                'is_registered' => true
            ];
        }, $addresses);
    }
}
