<?php

namespace App\Services\Shipping\Carriers;

use App\Services\Shipping\Contracts\CarrierAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XpressbeesAdapter implements CarrierAdapterInterface
{
    protected array $config;
    protected string $baseUrl;
    protected string $email;
    protected string $password;
    protected ?string $token = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->baseUrl = $config['api_mode'] === 'production'
            ? 'https://ship.xpressbees.com/api'
            : 'https://shipuat.xpressbees.com/api';
        $this->email = $config['api_key'] ?? $config['email'];
        $this->password = $config['api_secret'] ?? $config['password'];
        $this->authenticate();
    }

    /**
     * Authenticate and get JWT token
     */
    protected function authenticate(): void
    {
        try {
            $response = Http::post($this->baseUrl . '/users/login', [
                'email' => $this->email,
                'password' => $this->password
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->token = $data['data'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Xpressbees authentication failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get shipping rates from Xpressbees
     */
    public function getRates(array $shipment): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/shipments/charges', [
                'pickup_pincode' => $shipment['pickup_pincode'],
                'drop_pincode' => $shipment['delivery_pincode'],
                'weight' => $shipment['billable_weight'],
                'payment_type' => $shipment['payment_mode'] === 'cod' ? 'cod' : 'prepaid',
                'collectable_amount' => $shipment['cod_amount'] ?? 0,
                'invoice_value' => $shipment['order_value'] ?? 0
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->formatXpressbeesRates($data, $shipment);
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Xpressbees rate API error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Format Xpressbees rate response
     */
    protected function formatXpressbeesRates(array $response, array $shipment): array
    {
        $services = [];

        if (isset($response['data'])) {
            // Standard Service
            $standardRate = $response['data']['standard_charge'] ?? 0;
            $services[] = [
                'code' => 'STANDARD',
                'name' => 'Standard Delivery',
                'base_charge' => $standardRate * 0.75,
                'fuel_surcharge' => $standardRate * 0.12,
                'gst' => $standardRate * 0.13,
                'cod_charge' => $response['data']['cod_charges'] ?? 0,
                'insurance_charge' => 0,
                'other_charges' => 0,
                'total_charge' => $standardRate,
                'delivery_days' => 4,
                'expected_delivery_date' => now()->addDays(4)->format('Y-m-d'),
                'features' => ['tracking', 'sms_updates', 'doorstep_delivery'],
                'tracking_available' => true
            ];

            // Express Service
            if (isset($response['data']['express_charge'])) {
                $expressRate = $response['data']['express_charge'];
                $services[] = [
                    'code' => 'EXPRESS',
                    'name' => 'Express Delivery',
                    'base_charge' => $expressRate * 0.75,
                    'fuel_surcharge' => $expressRate * 0.12,
                    'gst' => $expressRate * 0.13,
                    'cod_charge' => $response['data']['cod_charges'] ?? 0,
                    'insurance_charge' => 0,
                    'other_charges' => 0,
                    'total_charge' => $expressRate,
                    'delivery_days' => 2,
                    'expected_delivery_date' => now()->addDays(2)->format('Y-m-d'),
                    'features' => ['tracking', 'priority_handling', 'sms_updates', 'doorstep_delivery'],
                    'tracking_available' => true
                ];
            }
        }

        return ['services' => $services];
    }

    /**
     * Create shipment with Xpressbees
     */
    public function createShipment(array $data): array
    {
        try {
            $shipmentData = [
                'order_id' => $data['order_id'],
                'order_date' => now()->format('Y-m-d'),
                'pickup_location' => 'Primary',
                'channel' => 'API',
                'billing_customer_name' => $data['delivery_address']['name'],
                'billing_address' => $data['delivery_address']['address_1'],
                'billing_city' => $data['delivery_address']['city'],
                'billing_pincode' => $data['delivery_address']['pincode'],
                'billing_state' => $data['delivery_address']['state'],
                'billing_country' => 'India',
                'billing_email' => $data['customer_details']['email'] ?? '',
                'billing_phone' => $data['delivery_address']['phone'],
                'shipping_is_billing' => true,
                'order_items' => [[
                    'name' => $data['package_details']['description'] ?? 'Books',
                    'qty' => $data['package_details']['quantity'] ?? 1,
                    'selling_price' => $data['package_details']['value'] ?? 0,
                    'discount' => 0,
                    'tax' => 0,
                    'hsn' => '49011010'
                ]],
                'payment_method' => $data['payment_mode'] === 'cod' ? 'COD' : 'Prepaid',
                'sub_total' => $data['package_details']['value'] ?? 0,
                'length' => $data['package_details']['length'] ?? 30,
                'breadth' => $data['package_details']['width'] ?? 20,
                'height' => $data['package_details']['height'] ?? 10,
                'weight' => $data['package_details']['weight']
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/shipments', $shipmentData);

            if ($response->successful()) {
                $result = $response->json();

                if (isset($result['data'])) {
                    return [
                        'success' => true,
                        'tracking_number' => $result['data']['awb_number'],
                        'carrier_reference' => $result['data']['shipment_id'],
                        'label_url' => $result['data']['label_url'] ?? '',
                        'pickup_date' => now()->addDay()->format('Y-m-d'),
                        'expected_delivery' => now()->addDays($data['service_type'] === 'EXPRESS' ? 2 : 4)->format('Y-m-d'),
                        'rates' => [
                            'base_rate' => $result['data']['charges'] ?? 0,
                            'cod_fee' => $result['data']['cod_charges'] ?? 0,
                            'total' => $result['data']['total_charges'] ?? 0
                        ]
                    ];
                }
            }

            throw new \Exception('Failed to create Xpressbees shipment');

        } catch (\Exception $e) {
            Log::error('Xpressbees create shipment error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Track shipment
     */
    public function trackShipment(string $trackingNumber): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token
            ])->get($this->baseUrl . '/shipments/track/' . $trackingNumber);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['data'])) {
                    $tracking = $data['data'];

                    return [
                        'status' => $this->mapStatus($tracking['current_status']),
                        'current_location' => $tracking['current_location'] ?? '',
                        'last_updated' => $tracking['updated_at'] ?? now(),
                        'delivered_at' => $tracking['delivered_date'] ?? null,
                        'events' => $this->parseTrackingEvents($tracking['tracking_details'] ?? [])
                    ];
                }
            }

            return ['status' => 'unknown', 'events' => []];

        } catch (\Exception $e) {
            Log::error('Xpressbees tracking error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Cancel shipment
     */
    public function cancelShipment(string $trackingNumber): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/shipments/cancel', [
                'awb' => $trackingNumber
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Xpressbees cancel shipment error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check serviceability
     */
    public function checkServiceability(string $pickupPincode, string $deliveryPincode, string $paymentMode): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token
            ])->get($this->baseUrl . '/serviceability', [
                'pickup_pincode' => $pickupPincode,
                'drop_pincode' => $deliveryPincode,
                'payment_type' => $paymentMode === 'cod' ? 'cod' : 'prepaid'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return isset($data['data']['serviceable']) && $data['data']['serviceable'] === true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Xpressbees serviceability check error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Schedule pickup
     */
    public function schedulePickup(array $pickup): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/pickup', [
                'pickup_date' => $pickup['pickup_date'],
                'pickup_time' => $pickup['pickup_time'] ?? '10-7',
                'packages' => $pickup['packages_count'] ?? 1,
                'pickup_location' => 'Primary'
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'pickup_id' => $data['data']['pickup_id'] ?? uniqid('XB'),
                    'scheduled_time' => $data['data']['pickup_time'] ?? $pickup['pickup_date']
                ];
            }

            throw new \Exception('Failed to schedule pickup');

        } catch (\Exception $e) {
            Log::error('Xpressbees pickup scheduling error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get rate async
     */
    public function getRateAsync(array $shipment): \GuzzleHttp\Promise\PromiseInterface
    {
        $client = new \GuzzleHttp\Client();

        return $client->postAsync($this->baseUrl . '/shipments/charges', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'pickup_pincode' => $shipment['pickup_pincode'],
                'drop_pincode' => $shipment['delivery_pincode'],
                'weight' => $shipment['billable_weight'],
                'payment_type' => $shipment['payment_mode'] === 'cod' ? 'cod' : 'prepaid',
                'collectable_amount' => $shipment['cod_amount'] ?? 0,
                'invoice_value' => $shipment['order_value'] ?? 0
            ]
        ]);
    }

    /**
     * Print shipping label
     */
    public function printLabel(string $trackingNumber): string
    {
        return $this->baseUrl . '/shipments/label/' . $trackingNumber;
    }

    /**
     * Map Xpressbees status to internal status
     */
    protected function mapStatus(string $status): string
    {
        $statusMap = [
            'pending_pickup' => 'created',
            'picked' => 'picked',
            'in_transit' => 'in_transit',
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            'return_to_origin' => 'rto'
        ];

        return $statusMap[strtolower($status)] ?? 'in_transit';
    }

    /**
     * Parse tracking events
     */
    protected function parseTrackingEvents(array $events): array
    {
        $parsedEvents = [];

        foreach ($events as $event) {
            $parsedEvents[] = [
                'timestamp' => $event['date'] ?? '',
                'status' => $event['status'] ?? '',
                'location' => $event['location'] ?? '',
                'message' => $event['activity'] ?? '',
                'type' => 'status_update'
            ];
        }

        return $parsedEvents;
    }

    /**
     * Validate Xpressbees credentials
     */
    public function validateCredentials(): array
    {
        try {
            // Check if required credentials are provided
            if (empty($this->config['email']) || empty($this->config['password']) || empty($this->config['account_id'])) {
                return [
                    'success' => false,
                    'error' => 'Email, Password, and Account ID are required',
                    'details' => [
                        'missing_credentials' => [
                            'email' => empty($this->config['email']),
                            'password' => empty($this->config['password']),
                            'account_id' => empty($this->config['account_id'])
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

                if (isset($data['token']) || isset($data['access_token'])) {
                    return [
                        'success' => true,
                        'details' => [
                            'message' => 'Credentials validated successfully',
                            'account_id' => $this->config['account_id'],
                            'api_mode' => $this->config['api_mode'] ?? 'test',
                            'endpoint_tested' => "{$this->baseUrl}/auth/login"
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Login successful but no access token received',
                        'details' => [
                            'response' => $data,
                            'endpoint_tested' => "{$this->baseUrl}/auth/login"
                        ]
                    ];
                }
            } elseif ($response->status() === 401) {
                return [
                    'success' => false,
                    'error' => 'Invalid email or password',
                    'details' => [
                        'http_status' => $response->status(),
                        'endpoint_tested' => "{$this->baseUrl}/auth/login"
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Authentication endpoint unreachable',
                    'details' => [
                        'http_status' => $response->status(),
                        'endpoint_tested' => "{$this->baseUrl}/auth/login"
                    ]
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Network error or invalid endpoint configuration',
                'details' => [
                    'exception' => $e->getMessage(),
                    'endpoint_tested' => "{$this->baseUrl}/auth/login"
                ]
            ];
        }
    }
}
