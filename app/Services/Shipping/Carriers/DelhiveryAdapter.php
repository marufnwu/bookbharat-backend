<?php

namespace App\Services\Shipping\Carriers;

use App\Services\Shipping\Contracts\CarrierAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DelhiveryAdapter implements CarrierAdapterInterface
{
    protected array $config;
    protected string $baseUrl;
    protected string $apiToken;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->baseUrl = $config['api_mode'] === 'production'
            ? 'https://track.delhivery.com'
            : 'https://staging-express.delhivery.com';
        $this->apiToken = $config['api_key'];
    }

    /**
     * Get shipping rates from Delhivery
     */
    public function getRates(array $shipment): array
    {
        try {
            // Delhivery Rate API endpoint
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiToken,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/api/kinko/v1/invoice/charges/.json', [
                'md' => $shipment['payment_mode'] === 'cod' ? 'COD' : 'Pre-paid',
                'ss' => $shipment['payment_mode'] === 'cod' ? 'Delivered' : 'Delivered',
                'cgm' => $shipment['billable_weight'] * 1000, // Convert kg to grams
                'o_pin' => $shipment['pickup_pincode'],
                'd_pin' => $shipment['delivery_pincode'],
                'v' => $shipment['order_value'] ?? 0
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $this->formatDelhiveryRates($data, $shipment);
            }

            Log::error('Delhivery rate API failed', [
                'response' => $response->body(),
                'status' => $response->status()
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error('Delhivery adapter error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Format Delhivery rate response
     */
    protected function formatDelhiveryRates(array $response, array $shipment): array
    {
        $services = [];

        // Delhivery typically returns a single rate, but we'll structure for multiple services
        if (isset($response[0])) {
            $rate = $response[0];

            // Surface/Standard Service
            $services[] = [
                'code' => 'SURFACE',
                'name' => 'Surface Express',
                'base_charge' => $rate['total_amount'] ?? 0,
                'fuel_surcharge' => $rate['fuel_surcharge'] ?? 0,
                'gst' => $rate['gst_amount'] ?? 0,
                'cod_charge' => $rate['cod_charges'] ?? 0,
                'insurance_charge' => 0,
                'other_charges' => $rate['docket_charge'] ?? 0,
                'total_charge' => $rate['total_amount'] ?? 0,
                'delivery_days' => $this->estimateDeliveryDays($shipment['pickup_pincode'], $shipment['delivery_pincode'], 'SURFACE'),
                'expected_delivery_date' => now()->addDays(4)->format('Y-m-d'),
                'features' => ['tracking', 'insurance_optional', 'doorstep_delivery'],
                'tracking_available' => true
            ];

            // Express/Air Service (typically 20-30% more expensive)
            if ($shipment['billable_weight'] <= 10) { // Air service for packages under 10kg
                $expressRate = $rate['total_amount'] * 1.25; // 25% premium for express
                $services[] = [
                    'code' => 'EXPRESS',
                    'name' => 'Air Express',
                    'base_charge' => $expressRate * 0.8,
                    'fuel_surcharge' => $expressRate * 0.1,
                    'gst' => $expressRate * 0.1,
                    'cod_charge' => $rate['cod_charges'] ?? 0,
                    'insurance_charge' => 0,
                    'other_charges' => 0,
                    'total_charge' => $expressRate,
                    'delivery_days' => $this->estimateDeliveryDays($shipment['pickup_pincode'], $shipment['delivery_pincode'], 'EXPRESS'),
                    'expected_delivery_date' => now()->addDays(2)->format('Y-m-d'),
                    'features' => ['tracking', 'priority_handling', 'insurance_optional', 'doorstep_delivery'],
                    'tracking_available' => true
                ];
            }
        }

        return ['services' => $services];
    }

    /**
     * Create shipment with Delhivery
     */
    public function createShipment(array $data): array
    {
        try {
            // Format pickup and delivery details
            $shipmentData = [
                'shipments' => [[
                    'name' => $data['delivery_address']['name'],
                    'add' => $data['delivery_address']['address_1'] . ' ' . ($data['delivery_address']['address_2'] ?? ''),
                    'city' => $data['delivery_address']['city'],
                    'state' => $data['delivery_address']['state'],
                    'country' => 'India',
                    'phone' => $data['delivery_address']['phone'],
                    'pin' => $data['delivery_address']['pincode'],
                    'payment_mode' => $data['payment_mode'] === 'cod' ? 'COD' : 'Prepaid',
                    'cod_amount' => $data['cod_amount'] ?? 0,
                    'order' => $data['order_id'],
                    'weight' => $data['package_details']['weight'] * 1000, // Convert to grams
                    'quantity' => $data['package_details']['quantity'] ?? 1,
                    'seller_name' => $data['pickup_address']['name'] ?? 'BookBharat',
                    'seller_add' => $data['pickup_address']['address_1'] ?? '',
                    'seller_cst' => '',
                    'seller_tin' => '',
                    'seller_inv' => $data['order_id'],
                    'seller_inv_date' => now()->format('Y-m-d H:i:s'),
                    'products_desc' => $data['package_details']['description'] ?? 'Books',
                    'hsn_code' => '49011010', // HSN code for books
                    'dangerous_goods' => false,
                    'ewbn' => '' // E-way bill number if applicable
                ]]
            ];

            // Create shipment via Delhivery API
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/cmu/create.json', [
                'format' => 'json',
                'data' => json_encode($shipmentData)
            ]);

            if ($response->successful()) {
                $result = $response->json();

                if (isset($result['packages'][0])) {
                    $package = $result['packages'][0];

                    return [
                        'success' => true,
                        'tracking_number' => $package['waybill'],
                        'carrier_reference' => $package['refnum'] ?? $package['waybill'],
                        'label_url' => $this->generateLabelUrl($package['waybill']),
                        'pickup_date' => now()->addDay()->format('Y-m-d'),
                        'expected_delivery' => now()->addDays($data['service_type'] === 'EXPRESS' ? 2 : 4)->format('Y-m-d'),
                        'rates' => [
                            'base_rate' => $package['rate'] ?? 0,
                            'cod_fee' => $package['cod_charges'] ?? 0,
                            'total' => $package['total_amount'] ?? 0
                        ]
                    ];
                }
            }

            throw new \Exception('Failed to create Delhivery shipment: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Delhivery create shipment error', ['error' => $e->getMessage()]);
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
                'Authorization' => 'Token ' . $this->apiToken
            ])->get($this->baseUrl . '/api/v1/packages/json/', [
                'waybill' => $trackingNumber
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['ShipmentData'][0])) {
                    $shipment = $data['ShipmentData'][0];

                    return [
                        'status' => $this->mapStatus($shipment['Status']['Status']),
                        'current_location' => $shipment['Origin'] ?? '',
                        'last_updated' => $shipment['Status']['StatusDateTime'] ?? now(),
                        'delivered_at' => $shipment['Status']['Status'] === 'Delivered' ? $shipment['Status']['StatusDateTime'] : null,
                        'events' => $this->parseTrackingEvents($shipment['Scans'] ?? [])
                    ];
                }
            }

            return ['status' => 'unknown', 'events' => []];

        } catch (\Exception $e) {
            Log::error('Delhivery tracking error', ['error' => $e->getMessage()]);
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
                'Authorization' => 'Token ' . $this->apiToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/p/edit', [
                'waybill' => $trackingNumber,
                'cancellation' => true
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Delhivery cancel shipment error', ['error' => $e->getMessage()]);
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
                'Authorization' => 'Token ' . $this->apiToken
            ])->get($this->baseUrl . '/c/api/pin-codes/json/', [
                'filter_codes' => $deliveryPincode
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['delivery_codes'][0])) {
                    $pincode = $data['delivery_codes'][0];

                    // Check if COD is required and available
                    if ($paymentMode === 'cod') {
                        return $pincode['postal_code']['cash'] === 'Y';
                    }

                    // For prepaid, just check if pincode is serviceable
                    return $pincode['postal_code']['is_oda'] === 'N';
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Delhivery serviceability check error', ['error' => $e->getMessage()]);
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
                'Authorization' => 'Token ' . $this->apiToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/fm/request/new/', [
                'pickup_date' => $pickup['pickup_date'],
                'pickup_time' => $pickup['pickup_time'] ?? '10:00:00 - 19:00:00',
                'pickup_location' => $this->config['pickup_location'] ?? 'Registered Address',
                'expected_package_count' => $pickup['packages_count'] ?? 1
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
            Log::error('Delhivery pickup scheduling error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get rate async (for parallel processing)
     */
    public function getRateAsync(array $shipment): \GuzzleHttp\Promise\PromiseInterface
    {
        $client = new \GuzzleHttp\Client();

        return $client->getAsync($this->baseUrl . '/api/kinko/v1/invoice/charges/.json', [
            'headers' => [
                'Authorization' => 'Token ' . $this->apiToken
            ],
            'query' => [
                'md' => $shipment['payment_mode'] === 'cod' ? 'COD' : 'Pre-paid',
                'cgm' => $shipment['billable_weight'] * 1000,
                'o_pin' => $shipment['pickup_pincode'],
                'd_pin' => $shipment['delivery_pincode'],
                'v' => $shipment['order_value'] ?? 0
            ]
        ]);
    }

    /**
     * Print shipping label
     */
    public function printLabel(string $trackingNumber): string
    {
        return $this->generateLabelUrl($trackingNumber);
    }

    /**
     * Generate label URL
     */
    protected function generateLabelUrl(string $waybill): string
    {
        return $this->baseUrl . '/api/p/packing_slip?wbns=' . $waybill . '&pdf=true';
    }

    /**
     * Estimate delivery days based on zones
     */
    protected function estimateDeliveryDays(string $origin, string $destination, string $service): int
    {
        // Simple zone-based estimation
        $originZone = substr($origin, 0, 2);
        $destZone = substr($destination, 0, 2);

        if ($originZone === $destZone) {
            return $service === 'EXPRESS' ? 1 : 2;
        }

        // Metro to metro
        $metros = ['11', '12', '40', '56', '60', '70', '80'];
        if (in_array($originZone, $metros) && in_array($destZone, $metros)) {
            return $service === 'EXPRESS' ? 2 : 3;
        }

        // Default
        return $service === 'EXPRESS' ? 3 : 5;
    }

    /**
     * Map Delhivery status to internal status
     */
    protected function mapStatus(string $status): string
    {
        $statusMap = [
            'Manifested' => 'created',
            'In Transit' => 'in_transit',
            'Dispatched' => 'out_for_delivery',
            'Delivered' => 'delivered',
            'RTO Initiated' => 'rto',
            'Lost' => 'failed',
            'Pending' => 'pending'
        ];

        return $statusMap[$status] ?? 'in_transit';
    }

    /**
     * Parse tracking events
     */
    protected function parseTrackingEvents(array $scans): array
    {
        $events = [];

        foreach ($scans as $scan) {
            $events[] = [
                'timestamp' => $scan['ScanDetail']['ScanDateTime'] ?? '',
                'status' => $scan['ScanDetail']['Scan'] ?? '',
                'location' => $scan['ScanDetail']['ScannedLocation'] ?? '',
                'message' => $scan['ScanDetail']['Instructions'] ?? '',
                'type' => 'status_update'
            ];
        }

        return $events;
    }

    /**
     * Validate Delhivery credentials
     */
    public function validateCredentials(): array
    {
        try {
            // Test the API key by making a simple authenticated request
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiToken,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/api/backend/clientwarehouse/', [
                'limit' => 1
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Check if we got valid warehouse data
                if (isset($data['data']) && is_array($data['data'])) {
                    return [
                        'success' => true,
                        'details' => [
                            'message' => 'API key is valid and authenticated',
                            'warehouses_count' => count($data['data']),
                            'api_mode' => $this->config['api_mode'],
                            'endpoint_tested' => $this->baseUrl . '/api/backend/clientwarehouse/'
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'API key authenticated but no warehouse data returned',
                        'details' => [
                            'response' => $data,
                            'endpoint_tested' => $this->baseUrl . '/api/backend/clientwarehouse/'
                        ]
                    ];
                }
            } elseif ($response->status() === 401) {
                return [
                    'success' => false,
                    'error' => 'Invalid API key or unauthorized access',
                    'details' => [
                        'http_status' => $response->status(),
                        'endpoint_tested' => $this->baseUrl . '/api/backend/clientwarehouse/'
                    ]
                ];
            } elseif ($response->status() === 403) {
                return [
                    'success' => false,
                    'error' => 'API key lacks required permissions',
                    'details' => [
                        'http_status' => $response->status(),
                        'endpoint_tested' => $this->baseUrl . '/api/backend/clientwarehouse/'
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'API endpoint unreachable or returned unexpected status',
                    'details' => [
                        'http_status' => $response->status(),
                        'response_body' => $response->body(),
                        'endpoint_tested' => $this->baseUrl . '/api/backend/clientwarehouse/'
                    ]
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Network error or invalid endpoint configuration',
                'details' => [
                    'exception' => $e->getMessage(),
                    'endpoint_tested' => $this->baseUrl . '/api/backend/clientwarehouse/'
                ]
            ];
        }
    }
}
