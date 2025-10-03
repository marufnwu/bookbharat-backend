<?php

namespace App\Services\Shipping\Carriers;

use App\Services\Shipping\Contracts\CarrierAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EcomExpressAdapter implements CarrierAdapterInterface
{
    protected array $config;
    protected string $baseUrl;
    protected array $headers;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->baseUrl = $config['api_endpoint'] ?? 'https://plapi.ecomexpress.in/api/v2';
        $this->headers = [
            'Content-Type' => 'application/json',
            'username' => $config['username'] ?? $config['api_key'] ?? '',
            'password' => $config['password'] ?? $config['api_secret'] ?? '',
        ];
    }

    public function calculateRate(array $params): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->post("{$this->baseUrl}/rate/calculate", [
                    'origin_pincode' => $params['origin_pincode'],
                    'destination_pincode' => $params['destination_pincode'],
                    'weight' => $params['weight'],
                    'length' => $params['length'] ?? null,
                    'breadth' => $params['breadth'] ?? null,
                    'height' => $params['height'] ?? null,
                    'cod' => $params['cod'] ?? false,
                    'declared_value' => $params['declared_value'] ?? 0,
                    'service_type' => $params['service_type'] ?? 'REGULAR',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'rate' => $data['rate'] ?? 0,
                    'currency' => 'INR',
                    'estimated_delivery_days' => $data['estimated_days'] ?? 5,
                    'service_type' => $data['service_type'] ?? 'REGULAR',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Rate calculation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Ecom Express rate calculation error: ' . $e->getMessage());
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
                ->post("{$this->baseUrl}/shipment/create", [
                    'awb' => $params['awb'] ?? null,
                    'order_number' => $params['order_number'],
                    'product_type' => $params['cod'] ? 'COD' : 'PPD',
                    'service_type' => $params['service_type'] ?? 'REGULAR',
                    'consignee' => [
                        'name' => $params['receiver_name'],
                        'address' => $params['receiver_address'],
                        'city' => $params['receiver_city'],
                        'state' => $params['receiver_state'],
                        'pincode' => $params['receiver_pincode'],
                        'phone' => $params['receiver_phone'],
                        'email' => $params['receiver_email'] ?? null,
                    ],
                    'pickup' => [
                        'name' => $params['sender_name'] ?? $this->config['pickup_location']['name'] ?? '',
                        'address' => $params['sender_address'] ?? $this->config['pickup_location']['address'] ?? '',
                        'city' => $params['sender_city'] ?? $this->config['pickup_location']['city'] ?? '',
                        'state' => $params['sender_state'] ?? $this->config['pickup_location']['state'] ?? '',
                        'pincode' => $params['sender_pincode'] ?? $this->config['pickup_location']['pincode'] ?? '',
                        'phone' => $params['sender_phone'] ?? $this->config['pickup_location']['phone'] ?? '',
                    ],
                    'shipment_details' => [
                        'weight' => $params['weight'],
                        'length' => $params['length'] ?? null,
                        'breadth' => $params['breadth'] ?? null,
                        'height' => $params['height'] ?? null,
                        'declared_value' => $params['declared_value'] ?? 0,
                        'cod_amount' => $params['cod'] ? $params['cod_amount'] : 0,
                        'invoice_number' => $params['invoice_number'] ?? null,
                        'invoice_date' => $params['invoice_date'] ?? null,
                    ],
                    'products' => $params['products'] ?? [],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'tracking_number' => $data['awb_number'] ?? '',
                    'label_url' => $data['label_url'] ?? '',
                    'shipment_id' => $data['shipment_id'] ?? '',
                    'estimated_delivery_date' => $data['estimated_delivery_date'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Shipment creation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Ecom Express shipment creation error: ' . $e->getMessage());
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
                ->get("{$this->baseUrl}/track", [
                    'awb' => $trackingNumber,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'status' => $this->mapStatus($data['status'] ?? ''),
                    'current_location' => $data['current_location'] ?? '',
                    'events' => $this->mapTrackingEvents($data['scan_details'] ?? []),
                    'delivered_at' => $data['delivered_date'] ?? null,
                    'signed_by' => $data['receiver_name'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Tracking failed',
            ];
        } catch (\Exception $e) {
            Log::error('Ecom Express tracking error: ' . $e->getMessage());
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
                ->post("{$this->baseUrl}/shipment/cancel", [
                    'awb' => $trackingNumber,
                    'reason' => 'Customer request',
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Shipment cancelled successfully',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Cancellation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Ecom Express cancellation error: ' . $e->getMessage());
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
                ->get("{$this->baseUrl}/label", [
                    'awb' => $trackingNumber,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'label_url' => $data['label_url'] ?? '',
                    'label_base64' => $data['label_base64'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Label fetch failed',
            ];
        } catch (\Exception $e) {
            Log::error('Ecom Express label fetch error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function schedulePickup(array $params): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->post("{$this->baseUrl}/pickup/schedule", [
                    'pickup_date' => $params['pickup_date'],
                    'pickup_time' => $params['pickup_time'] ?? '10:00-19:00',
                    'location' => $params['pickup_location'] ?? 'default',
                    'awb_numbers' => $params['tracking_numbers'] ?? [],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'pickup_id' => $data['pickup_id'] ?? '',
                    'scheduled_date' => $data['scheduled_date'] ?? '',
                    'scheduled_time' => $data['scheduled_time'] ?? '',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Pickup scheduling failed',
            ];
        } catch (\Exception $e) {
            Log::error('Ecom Express pickup scheduling error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function checkServiceability(string $originPincode, string $destinationPincode): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/serviceability", [
                    'origin_pincode' => $originPincode,
                    'destination_pincode' => $destinationPincode,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'serviceable' => $data['serviceable'] ?? false,
                    'services' => $data['available_services'] ?? [],
                    'estimated_days' => $data['estimated_days'] ?? null,
                    'zone' => $data['zone'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Serviceability check failed',
            ];
        } catch (\Exception $e) {
            Log::error('Ecom Express serviceability error: ' . $e->getMessage());
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
            'PICKED' => 'picked_up',
            'IN_TRANSIT' => 'in_transit',
            'OUT_FOR_DELIVERY' => 'out_for_delivery',
            'DELIVERED' => 'delivered',
            'RTO' => 'return_to_origin',
            'CANCELLED' => 'cancelled',
            'PENDING' => 'pending',
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
                'timestamp' => $event['scan_date_time'] ?? '',
                'status' => $event['scan_type'] ?? '',
                'location' => $event['location'] ?? '',
                'description' => $event['instructions'] ?? '',
            ];
        }, $events);
    }
}