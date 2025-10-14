<?php

namespace App\Services\Shipping\Carriers;

use App\Services\Shipping\Contracts\CarrierAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BluedartAdapter implements CarrierAdapterInterface
{
    protected array $config;
    protected string $baseUrl;
    protected array $headers;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->baseUrl = $config['api_endpoint'] ?? 'https://api.bluedart.com/v1';
        $this->headers = [
            'LicenseKey' => $config['license_key'] ?? $config['api_key'] ?? '',
            'LoginID' => $config['login_id'] ?? $config['api_secret'] ?? '',
            'Content-Type' => 'application/json',
        ];
    }

    public function calculateRate(array $params): array
    {
        try {
            // BlueDart specific rate calculation logic
            $weight = $params['weight'] ?? 1.0;
            $distance = $this->calculateDistance($params['origin_pincode'], $params['destination_pincode']);

            // Mock calculation for now
            $baseRate = 80;
            $weightCharge = ($weight - 0.5) * 40;
            $distanceCharge = $distance * 0.05;

            $subtotal = $baseRate + $weightCharge + $distanceCharge;
            $fuelSurcharge = $subtotal * 0.15;
            $gst = ($subtotal + $fuelSurcharge) * 0.18;

            $rates = [];

            // Express Service
            $rates[] = [
                'service_code' => 'EXPRESS',
                'service_name' => 'BlueDart Express',
                'base_charge' => round($subtotal, 2),
                'fuel_surcharge' => round($fuelSurcharge, 2),
                'gst' => round($gst, 2),
                'cod_charge' => $params['payment_mode'] === 'cod' ? 50 : 0,
                'other_charges' => 0,
                'total_charge' => round($subtotal + $fuelSurcharge + $gst + ($params['payment_mode'] === 'cod' ? 50 : 0), 2),
                'delivery_days' => 2,
                'estimated_delivery_date' => now()->addDays(2)->format('Y-m-d')
            ];

            // Priority Service
            $priorityMultiplier = 1.5;
            $rates[] = [
                'service_code' => 'PRIORITY',
                'service_name' => 'BlueDart Priority',
                'base_charge' => round($subtotal * $priorityMultiplier, 2),
                'fuel_surcharge' => round($fuelSurcharge * $priorityMultiplier, 2),
                'gst' => round($gst * $priorityMultiplier, 2),
                'cod_charge' => $params['payment_mode'] === 'cod' ? 50 : 0,
                'other_charges' => 0,
                'total_charge' => round(($subtotal + $fuelSurcharge + $gst) * $priorityMultiplier + ($params['payment_mode'] === 'cod' ? 50 : 0), 2),
                'delivery_days' => 1,
                'estimated_delivery_date' => now()->addDays(1)->format('Y-m-d')
            ];

            return [
                'success' => true,
                'rates' => $rates
            ];

        } catch (\Exception $e) {
            Log::error('BlueDart rate calculation failed', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'rates' => []
            ];
        }
    }

    public function createShipment(array $params): array
    {
        try {
            // BlueDart specific shipment creation logic
            $trackingNumber = 'BD' . strtoupper(uniqid());

            // In production, this would make actual API call
            if ($this->config['api_mode'] === 'test') {
                return [
                    'success' => true,
                    'tracking_number' => $trackingNumber,
                    'awb_number' => $trackingNumber,
                    'label_url' => 'https://bluedart.com/labels/' . $trackingNumber . '.pdf',
                    'carrier_response' => [
                        'status' => 'success',
                        'message' => 'Test shipment created'
                    ]
                ];
            }

            // Actual API call would go here
            $response = Http::withHeaders($this->headers)
                ->post($this->baseUrl . '/shipments', [
                    'pickup_location' => $params['pickup_address'],
                    'delivery_location' => $params['delivery_address'],
                    'weight' => $params['weight'],
                    'payment_mode' => $params['payment_mode'],
                    'product_type' => $params['service_code'] ?? 'EXPRESS',
                    'cod_amount' => $params['cod_amount'] ?? 0
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'tracking_number' => $data['tracking_number'] ?? $trackingNumber,
                    'awb_number' => $data['awb_number'] ?? $trackingNumber,
                    'label_url' => $data['label_url'] ?? null,
                    'carrier_response' => $data
                ];
            } else {
                throw new \Exception('API request failed: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('BlueDart shipment creation failed', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function trackShipment(string $trackingNumber): array
    {
        try {
            // BlueDart specific tracking logic
            if ($this->config['api_mode'] === 'test') {
                return [
                    'success' => true,
                    'tracking_number' => $trackingNumber,
                    'status' => 'in_transit',
                    'status_description' => 'Package in transit',
                    'current_location' => 'Mumbai Hub',
                    'events' => [
                        [
                            'date' => now()->subDays(1)->format('Y-m-d H:i:s'),
                            'status' => 'picked_up',
                            'location' => 'Delhi',
                            'description' => 'Package picked up'
                        ],
                        [
                            'date' => now()->format('Y-m-d H:i:s'),
                            'status' => 'in_transit',
                            'location' => 'Mumbai Hub',
                            'description' => 'Package in transit'
                        ]
                    ]
                ];
            }

            // Actual API call
            $response = Http::withHeaders($this->headers)
                ->get($this->baseUrl . '/track/' . $trackingNumber);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'tracking_number' => $trackingNumber,
                    'status' => $this->mapStatus($data['status'] ?? 'unknown'),
                    'status_description' => $data['status_description'] ?? '',
                    'current_location' => $data['current_location'] ?? '',
                    'events' => $data['events'] ?? []
                ];
            } else {
                throw new \Exception('Tracking request failed');
            }

        } catch (\Exception $e) {
            Log::error('BlueDart tracking failed', [
                'error' => $e->getMessage(),
                'tracking_number' => $trackingNumber
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function cancelShipment(string $trackingNumber): bool
    {
        try {
            // BlueDart specific cancellation logic
            if ($this->config['api_mode'] === 'test') {
                Log::info('BlueDart shipment cancelled (test mode)', [
                    'tracking_number' => $trackingNumber
                ]);
                return true;
            }

            $response = Http::withHeaders($this->headers)
                ->delete($this->baseUrl . '/shipments/' . $trackingNumber);

            if ($response->successful()) {
                Log::info('BlueDart shipment cancelled successfully', [
                    'tracking_number' => $trackingNumber
                ]);
                return true;
            }

            Log::warning('BlueDart cancellation request failed', [
                'tracking_number' => $trackingNumber,
                'response' => $response->body()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('BlueDart shipment cancellation failed', [
                'error' => $e->getMessage(),
                'tracking_number' => $trackingNumber
            ]);
            return false;
        }
    }

    public function getLabel(string $awbNumber): array
    {
        try {
            // BlueDart specific label generation
            if ($this->config['api_mode'] === 'test') {
                return [
                    'success' => true,
                    'label_url' => 'https://bluedart.com/labels/' . $awbNumber . '.pdf',
                    'label_format' => 'pdf'
                ];
            }

            $response = Http::withHeaders($this->headers)
                ->get($this->baseUrl . '/labels/' . $awbNumber);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'label_url' => $data['label_url'] ?? null,
                    'label_format' => $data['format'] ?? 'pdf'
                ];
            } else {
                throw new \Exception('Label generation failed');
            }

        } catch (\Exception $e) {
            Log::error('BlueDart label generation failed', [
                'error' => $e->getMessage(),
                'awb_number' => $awbNumber
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function checkServiceability(string $pincode): array
    {
        try {
            // BlueDart specific serviceability check
            if ($this->config['api_mode'] === 'test') {
                // Mock serviceability - most pincodes are serviceable
                $serviceable = !in_array(substr($pincode, 0, 2), ['19', '79', '99']);

                return [
                    'success' => true,
                    'serviceable' => $serviceable,
                    'services' => $serviceable ? ['EXPRESS', 'PRIORITY'] : [],
                    'cod_available' => $serviceable && substr($pincode, 0, 1) !== '9',
                    'pickup_available' => $serviceable,
                    'delivery_days' => $serviceable ? 2 : null
                ];
            }

            $response = Http::withHeaders($this->headers)
                ->get($this->baseUrl . '/serviceability/' . $pincode);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'serviceable' => $data['serviceable'] ?? false,
                    'services' => $data['services'] ?? [],
                    'cod_available' => $data['cod_available'] ?? false,
                    'pickup_available' => $data['pickup_available'] ?? false,
                    'delivery_days' => $data['delivery_days'] ?? null
                ];
            } else {
                throw new \Exception('Serviceability check failed');
            }

        } catch (\Exception $e) {
            Log::error('BlueDart serviceability check failed', [
                'error' => $e->getMessage(),
                'pincode' => $pincode
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    protected function calculateDistance(string $origin, string $destination): float
    {
        // Simple distance calculation based on pincode zones
        $originZone = substr($origin, 0, 1);
        $destZone = substr($destination, 0, 1);

        $zoneDistance = abs((int)$originZone - (int)$destZone);

        // Approximate distance in km
        return $zoneDistance * 500 + 100;
    }

    protected function mapStatus(string $status): string
    {
        $statusMap = [
            'SHIPPED' => 'shipped',
            'IN_TRANSIT' => 'in_transit',
            'OUT_FOR_DELIVERY' => 'out_for_delivery',
            'DELIVERED' => 'delivered',
            'RTO' => 'rto',
            'CANCELLED' => 'cancelled'
        ];

        return $statusMap[strtoupper($status)] ?? 'unknown';
    }

    /**
     * Validate Bluedart credentials
     */
    public function validateCredentials(): array
    {
        try {
            // Check if basic credentials are provided
            if (empty($this->config['api_key']) || empty($this->config['api_secret'])) {
                return [
                    'success' => false,
                    'error' => 'License Key and Login ID are required',
                    'details' => [
                        'missing_credentials' => [
                            'license_key' => empty($this->config['api_key']),
                            'login_id' => empty($this->config['api_secret'])
                        ]
                    ]
                ];
            }

            // Attempt a basic API call to validate credentials
            // This is a placeholder - actual implementation would depend on BlueDart's API
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/profile");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'details' => [
                        'message' => 'Credentials validated successfully',
                        'api_mode' => $this->config['api_mode'] ?? 'test',
                        'endpoint_tested' => "{$this->baseUrl}/profile",
                        'response_data' => $data
                    ]
                ];
            } elseif ($response->status() === 401) {
                return [
                    'success' => false,
                    'error' => 'Invalid License Key or Login ID',
                    'details' => [
                        'http_status' => $response->status(),
                        'endpoint_tested' => "{$this->baseUrl}/profile"
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'API endpoint unreachable or credentials invalid',
                    'details' => [
                        'http_status' => $response->status(),
                        'endpoint_tested' => "{$this->baseUrl}/profile"
                    ]
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Network error or invalid endpoint configuration',
                'details' => [
                    'exception' => $e->getMessage(),
                    'endpoint_tested' => "{$this->baseUrl}/profile"
                ]
            ];
        }
    }

    /**
     * Get warehouse requirement type for Bluedart
     * 
     * BlueDart uses customer code + full address
     * 
     * @return string 'full_address'
     */
    public function getWarehouseRequirementType(): string
    {
        return 'full_address';
    }
}
