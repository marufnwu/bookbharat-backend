<?php

namespace App\Services\Shipping\Carriers;

use App\Services\Shipping\Contracts\CarrierAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShadowfaxAdapter implements CarrierAdapterInterface
{
    protected array $config;
    protected string $baseUrl;
    protected array $headers;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->baseUrl = $config['api_endpoint'] ?? 'https://api.shadowfax.in/v3';
        $this->headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . ($config['api_token'] ?? $config['api_key'] ?? ''),
        ];
    }

    public function calculateRate(array $params): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->post("{$this->baseUrl}/rates/calculate", [
                    'pickup_details' => [
                        'lat' => $params['origin_lat'] ?? null,
                        'lng' => $params['origin_lng'] ?? null,
                        'pincode' => $params['origin_pincode'],
                    ],
                    'drop_details' => [
                        'lat' => $params['destination_lat'] ?? null,
                        'lng' => $params['destination_lng'] ?? null,
                        'pincode' => $params['destination_pincode'],
                    ],
                    'package_details' => [
                        'weight' => $params['weight'],
                        'length' => $params['length'] ?? null,
                        'breadth' => $params['breadth'] ?? null,
                        'height' => $params['height'] ?? null,
                    ],
                    'service_type' => $params['service_type'] ?? 'STANDARD',
                    'cod_amount' => $params['cod'] ? $params['cod_amount'] : 0,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'rate' => $data['total_charge'] ?? 0,
                    'currency' => 'INR',
                    'estimated_delivery_time' => $data['estimated_time'] ?? '2-3 days',
                    'service_type' => $data['service_type'] ?? 'STANDARD',
                    'breakdown' => [
                        'base_charge' => $data['base_charge'] ?? 0,
                        'distance_charge' => $data['distance_charge'] ?? 0,
                        'cod_charge' => $data['cod_charge'] ?? 0,
                    ],
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Rate calculation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shadowfax rate calculation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function createShipment(array $params): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->post("{$this->baseUrl}/orders/create", [
                    'order_id' => $params['order_number'],
                    'service_type' => $params['service_type'] ?? 'STANDARD',
                    'pickup_details' => [
                        'name' => $params['sender_name'] ?? $this->config['pickup_location']['name'] ?? '',
                        'address' => $params['sender_address'] ?? $this->config['pickup_location']['address'] ?? '',
                        'city' => $params['sender_city'] ?? $this->config['pickup_location']['city'] ?? '',
                        'state' => $params['sender_state'] ?? $this->config['pickup_location']['state'] ?? '',
                        'pincode' => $params['sender_pincode'] ?? $this->config['pickup_location']['pincode'] ?? '',
                        'phone' => $params['sender_phone'] ?? $this->config['pickup_location']['phone'] ?? '',
                        'lat' => $params['sender_lat'] ?? null,
                        'lng' => $params['sender_lng'] ?? null,
                    ],
                    'drop_details' => [
                        'name' => $params['receiver_name'],
                        'address' => $params['receiver_address'],
                        'city' => $params['receiver_city'],
                        'state' => $params['receiver_state'],
                        'pincode' => $params['receiver_pincode'],
                        'phone' => $params['receiver_phone'],
                        'email' => $params['receiver_email'] ?? null,
                        'lat' => $params['receiver_lat'] ?? null,
                        'lng' => $params['receiver_lng'] ?? null,
                    ],
                    'package_details' => [
                        'weight' => $params['weight'],
                        'length' => $params['length'] ?? null,
                        'breadth' => $params['breadth'] ?? null,
                        'height' => $params['height'] ?? null,
                        'value' => $params['declared_value'] ?? 0,
                        'invoice_number' => $params['invoice_number'] ?? null,
                        'description' => $params['description'] ?? 'Books',
                    ],
                    'payment_details' => [
                        'payment_mode' => $params['cod'] ? 'COD' : 'PREPAID',
                        'cod_amount' => $params['cod'] ? $params['cod_amount'] : 0,
                    ],
                    'instructions' => $params['instructions'] ?? null,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'tracking_number' => $data['tracking_id'] ?? '',
                    'order_id' => $data['order_id'] ?? '',
                    'label_url' => $data['label_url'] ?? '',
                    'estimated_delivery' => $data['estimated_delivery'] ?? null,
                    'pickup_scheduled_at' => $data['pickup_scheduled_at'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Shipment creation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shadowfax shipment creation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function trackShipment(string $trackingNumber): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/orders/{$trackingNumber}/track");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'status' => $this->mapStatus($data['status'] ?? ''),
                    'current_location' => [
                        'lat' => $data['current_location']['lat'] ?? null,
                        'lng' => $data['current_location']['lng'] ?? null,
                        'address' => $data['current_location']['address'] ?? '',
                    ],
                    'rider_details' => [
                        'name' => $data['rider']['name'] ?? null,
                        'phone' => $data['rider']['phone'] ?? null,
                    ],
                    'events' => $this->mapTrackingEvents($data['tracking_history'] ?? []),
                    'delivered_at' => $data['delivered_at'] ?? null,
                    'proof_of_delivery' => $data['pod_url'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Tracking failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shadowfax tracking error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function cancelShipment(string $trackingNumber): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->post("{$this->baseUrl}/orders/{$trackingNumber}/cancel", [
                    'reason' => 'Customer request',
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Shipment cancelled successfully',
                    'refund_amount' => $response->json()['refund_amount'] ?? 0,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Cancellation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shadowfax cancellation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getLabel(string $trackingNumber): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/orders/{$trackingNumber}/label");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'label_url' => $data['label_url'] ?? '',
                    'qr_code' => $data['qr_code'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Label fetch failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shadowfax label fetch error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function schedulePickup(array $params): array
    {
        // Shadowfax automatically schedules pickup when order is created
        return [
            'success' => true,
            'message' => 'Pickup is automatically scheduled with order creation',
        ];
    }

    public function checkServiceability(string $originPincode, string $destinationPincode): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/serviceability/check", [
                    'pickup_pincode' => $originPincode,
                    'drop_pincode' => $destinationPincode,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'serviceable' => $data['serviceable'] ?? false,
                    'services' => $data['available_services'] ?? [],
                    'estimated_time' => $data['estimated_time'] ?? null,
                    'distance' => $data['distance'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Serviceability check failed',
            ];
        } catch (\Exception $e) {
            Log::error('Shadowfax serviceability error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Map carrier status to standard status
     */
    protected function mapStatus(string $status): string
    {
        $statusMap = [
            'CREATED' => 'created',
            'ASSIGNED' => 'assigned',
            'PICKUP_STARTED' => 'pickup_started',
            'PICKED_UP' => 'picked_up',
            'IN_TRANSIT' => 'in_transit',
            'REACHED_HUB' => 'in_transit',
            'OUT_FOR_DELIVERY' => 'out_for_delivery',
            'DELIVERED' => 'delivered',
            'CANCELLED' => 'cancelled',
            'FAILED' => 'failed',
            'RETURNED' => 'returned',
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
                'timestamp' => $event['timestamp'] ?? '',
                'status' => $event['status'] ?? '',
                'location' => [
                    'lat' => $event['location']['lat'] ?? null,
                    'lng' => $event['location']['lng'] ?? null,
                    'address' => $event['location']['address'] ?? '',
                ],
                'description' => $event['message'] ?? '',
            ];
        }, $events);
    }

    /**
     * Validate Shadowfax credentials
     */
    public function validateCredentials(): array
    {
        try {
            // Test the API token by making a simple authenticated request
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/merchants/profile");

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['merchant_id'])) {
                    return [
                        'success' => true,
                        'details' => [
                            'message' => 'API token is valid and authenticated',
                            'merchant_id' => $data['merchant_id'],
                            'api_mode' => $this->config['api_mode'] ?? 'production',
                            'endpoint_tested' => "{$this->baseUrl}/merchants/profile"
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'API token authenticated but invalid merchant profile',
                        'details' => [
                            'response' => $data,
                            'endpoint_tested' => "{$this->baseUrl}/merchants/profile"
                        ]
                    ];
                }
            } elseif ($response->status() === 401) {
                return [
                    'success' => false,
                    'error' => 'Invalid API token or unauthorized access',
                    'details' => [
                        'http_status' => $response->status(),
                        'endpoint_tested' => "{$this->baseUrl}/merchants/profile"
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'API endpoint unreachable or returned unexpected status',
                    'details' => [
                        'http_status' => $response->status(),
                        'response_body' => $response->body(),
                        'endpoint_tested' => "{$this->baseUrl}/merchants/profile"
                    ]
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Network error or invalid endpoint configuration',
                'details' => [
                    'exception' => $e->getMessage(),
                    'endpoint_tested' => "{$this->baseUrl}/merchants/profile"
                ]
            ];
        }
    }
}
