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
        $this->authToken = $this->getAuthToken();
    }

    /**
     * Get authentication token (cached for 10 days)
     */
    protected function getAuthToken(): string
    {
        return Cache::remember('shiprocket_auth_token', 60 * 60 * 24 * 10, function () {
            $response = Http::post("{$this->baseUrl}/auth/login", [
                'email' => $this->config['email'] ?? '',
                'password' => $this->config['password'] ?? $this->config['api_secret'] ?? '',
            ]);

            if ($response->successful()) {
                return $response->json()['token'] ?? '';
            }

            throw new \Exception('Failed to authenticate with Shiprocket');
        });
    }

    /**
     * Get headers with authentication
     */
    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->authToken,
        ];
    }

    public function calculateRate(array $params): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/courier/serviceability", [
                    'pickup_postcode' => $params['origin_pincode'],
                    'delivery_postcode' => $params['destination_pincode'],
                    'weight' => $params['weight'],
                    'cod' => $params['cod'] ? 1 : 0,
                    'declared_value' => $params['declared_value'] ?? 0,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $couriers = $data['data']['available_courier_companies'] ?? [];

                if (empty($couriers)) {
                    return [
                        'success' => false,
                        'error' => 'No couriers available for this route',
                    ];
                }

                // Get the best rate
                $bestCourier = collect($couriers)->sortBy('rate')->first();

                return [
                    'success' => true,
                    'rate' => $bestCourier['rate'] ?? 0,
                    'currency' => 'INR',
                    'estimated_delivery_days' => $bestCourier['estimated_delivery_days'] ?? 5,
                    'courier_name' => $bestCourier['courier_name'] ?? '',
                    'courier_id' => $bestCourier['courier_company_id'] ?? '',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Rate calculation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shiprocket rate calculation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function createShipment(array $params): array
    {
        try {
            // First, create the order
            $orderResponse = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/orders/create/adhoc", [
                    'order_id' => $params['order_number'],
                    'order_date' => date('Y-m-d'),
                    'pickup_location' => $params['pickup_location'] ?? 'Primary',
                    'billing_customer_name' => $params['receiver_name'],
                    'billing_last_name' => '',
                    'billing_address' => $params['receiver_address'],
                    'billing_city' => $params['receiver_city'],
                    'billing_pincode' => $params['receiver_pincode'],
                    'billing_state' => $params['receiver_state'],
                    'billing_country' => 'India',
                    'billing_email' => $params['receiver_email'] ?? '',
                    'billing_phone' => $params['receiver_phone'],
                    'shipping_is_billing' => true,
                    'order_items' => $params['products'] ?? [[
                        'name' => 'Books',
                        'sku' => 'BOOK-001',
                        'units' => 1,
                        'selling_price' => $params['declared_value'] ?? 100,
                    ]],
                    'payment_method' => $params['cod'] ? 'COD' : 'Prepaid',
                    'sub_total' => $params['declared_value'] ?? 100,
                    'length' => $params['length'] ?? 10,
                    'breadth' => $params['breadth'] ?? 10,
                    'height' => $params['height'] ?? 10,
                    'weight' => $params['weight'],
                ]);

            if (!$orderResponse->successful()) {
                return [
                    'success' => false,
                    'error' => $orderResponse->json()['message'] ?? 'Order creation failed',
                ];
            }

            $orderData = $orderResponse->json();
            $shipmentOrderId = $orderData['order_id'] ?? null;

            if (!$shipmentOrderId) {
                return [
                    'success' => false,
                    'error' => 'Failed to get order ID from Shiprocket',
                ];
            }

            // Generate AWB for the order
            $awbResponse = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/courier/assign/awb", [
                    'shipment_id' => $orderData['shipment_id'] ?? '',
                    'courier_id' => $params['courier_id'] ?? '',
                ]);

            if ($awbResponse->successful()) {
                $awbData = $awbResponse->json();
                return [
                    'success' => true,
                    'tracking_number' => $awbData['response']['data']['awb_code'] ?? '',
                    'shipment_id' => $orderData['shipment_id'] ?? '',
                    'order_id' => $shipmentOrderId,
                    'label_url' => $awbData['response']['data']['label_url'] ?? '',
                    'manifest_url' => $awbData['response']['data']['manifest_url'] ?? '',
                ];
            }

            return [
                'success' => false,
                'error' => $awbResponse->json()['message'] ?? 'AWB generation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shiprocket shipment creation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

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
                        'error' => 'No tracking data available',
                    ];
                }

                $currentStatus = $tracking['shipment_track'][0] ?? [];

                return [
                    'success' => true,
                    'status' => $this->mapStatus($currentStatus['current_status'] ?? ''),
                    'current_location' => $currentStatus['location'] ?? '',
                    'events' => $this->mapTrackingEvents($tracking['shipment_track_activities'] ?? []),
                    'delivered_at' => $currentStatus['delivered_date'] ?? null,
                    'edd' => $currentStatus['edd'] ?? null,
                    'courier_name' => $tracking['courier_name'] ?? '',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Tracking failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shiprocket tracking error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function cancelShipment(string $trackingNumber): array
    {
        try {
            // Shiprocket requires order ID for cancellation, not AWB
            // This would need to be stored/retrieved from database
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/orders/cancel", [
                    'ids' => [$trackingNumber], // This should be order IDs
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Shipment cancellation initiated',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Cancellation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shiprocket cancellation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getLabel(string $trackingNumber): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/courier/generate/label", [
                    'shipment_id' => [$trackingNumber], // This should be shipment IDs
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'label_url' => $data['label_url'] ?? '',
                    'label_created' => $data['label_created'] ?? false,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Label generation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shiprocket label generation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function schedulePickup(array $params): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/courier/generate/pickup", [
                    'shipment_id' => $params['shipment_ids'] ?? [],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'pickup_scheduled' => $data['pickup_scheduled'] ?? false,
                    'pickup_token_number' => $data['pickup_token_number'] ?? '',
                    'pickup_status' => $data['pickup_status'] ?? '',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Pickup scheduling failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shiprocket pickup scheduling error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function checkServiceability(string $originPincode, string $destinationPincode): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/courier/serviceability", [
                    'pickup_postcode' => $originPincode,
                    'delivery_postcode' => $destinationPincode,
                    'weight' => 0.5, // Default weight for check
                    'cod' => 0,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $couriers = $data['data']['available_courier_companies'] ?? [];

                return [
                    'success' => true,
                    'serviceable' => !empty($couriers),
                    'couriers' => array_map(function ($courier) {
                        return [
                            'name' => $courier['courier_name'] ?? '',
                            'id' => $courier['courier_company_id'] ?? '',
                            'estimated_days' => $courier['estimated_delivery_days'] ?? '',
                            'rate' => $courier['rate'] ?? 0,
                        ];
                    }, $couriers),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Serviceability check failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shiprocket serviceability error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
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
            $response = Http::post("{$this->baseUrl}/v1/external/auth/login", [
                'email' => $this->config['email'],
                'password' => $this->config['password']
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['token'])) {
                    return [
                        'success' => true,
                        'details' => [
                            'message' => 'Credentials validated successfully',
                            'company_id' => $data['company_id'] ?? null,
                            'api_mode' => 'live', // Shiprocket only has live mode
                            'endpoint_tested' => "{$this->baseUrl}/v1/external/auth/login"
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Login successful but no access token received',
                        'details' => [
                            'response' => $data,
                            'endpoint_tested' => "{$this->baseUrl}/v1/external/auth/login"
                        ]
                    ];
                }
            } elseif ($response->status() === 401) {
                return [
                    'success' => false,
                    'error' => 'Invalid email or password',
                    'details' => [
                        'http_status' => $response->status(),
                        'endpoint_tested' => "{$this->baseUrl}/v1/external/auth/login"
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Authentication endpoint unreachable',
                    'details' => [
                        'http_status' => $response->status(),
                        'endpoint_tested' => "{$this->baseUrl}/v1/external/auth/login"
                    ]
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Network error or invalid endpoint configuration',
                'details' => [
                    'exception' => $e->getMessage(),
                    'endpoint_tested' => "{$this->baseUrl}/v1/external/auth/login"
                ]
            ];
        }
    }
}
