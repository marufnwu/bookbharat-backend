<?php

namespace App\Services\Shipping\Carriers;

use App\Services\Shipping\Contracts\CarrierAdapterInterface;

class DtdcAdapter implements CarrierAdapterInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function calculateRate(array $params): array
    {
        return [
            'success' => true,
            'rates' => [
                [
                    'service_code' => 'STANDARD',
                    'service_name' => 'Standard Delivery',
                    'base_charge' => 100,
                    'fuel_surcharge' => 15,
                    'gst' => 20,
                    'cod_charge' => 0,
                    'other_charges' => 0,
                    'total_charge' => 135,
                    'delivery_days' => 3,
                    'estimated_delivery_date' => now()->addDays(3)->format('Y-m-d')
                ]
            ]
        ];
    }

    public function createShipment(array $params): array
    {
        return [
            'success' => true,
            'tracking_number' => strtoupper(uniqid('TRK')),
            'awb_number' => strtoupper(uniqid('AWB')),
            'label_url' => null,
            'carrier_response' => []
        ];
    }

    public function trackShipment(string $trackingNumber): array
    {
        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'status' => 'in_transit',
            'status_description' => 'In Transit',
            'current_location' => 'Unknown',
            'events' => []
        ];
    }

    public function cancelShipment(string $trackingNumber): bool
    {
        return [
            'success' => true,
            'message' => 'Shipment cancelled',
            'awb_number' => $awbNumber
        ];
    }

    public function getLabel(string $awbNumber): array
    {
        return [
            'success' => true,
            'label_url' => null,
            'label_format' => 'pdf'
        ];
    }

    public function checkServiceability(string $pickupPincode, string $deliveryPincode, string $paymentMode): bool
    {
        // Basic implementation - always return true for now
        return true;
    }

    public function schedulePickup(array $pickup): array
    {
        return [
            'success' => true,
            'pickup_id' => uniqid('DTDC'),
            'scheduled_time' => $pickup['pickup_date'] ?? now()->toDateTimeString()
        ];
    }

    public function getRateAsync(array $shipment): \GuzzleHttp\Promise\PromiseInterface
    {
        // Basic implementation
        $promise = \GuzzleHttp\Promise\promise_for($this->calculateRate($shipment));
        return $promise;
    }

    public function printLabel(string $trackingNumber): string
    {
        return '';
    }

    /**
     * Validate DTDC credentials
     */
    public function validateCredentials(): array
    {
        try {
            // Check if required credentials are provided
            if (empty($this->config['access_token']) || empty($this->config['customer_code'])) {
                return [
                    'success' => false,
                    'error' => 'Access Token and Customer Code are required',
                    'details' => [
                        'missing_credentials' => [
                            'access_token' => empty($this->config['access_token']),
                            'customer_code' => empty($this->config['customer_code'])
                        ]
                    ]
                ];
            }

            // Basic credential format validation
            // DTDC access tokens are typically long strings
            if (strlen($this->config['access_token']) < 10) {
                return [
                    'success' => false,
                    'error' => 'Access token appears to be invalid (too short)',
                    'details' => [
                        'token_length' => strlen($this->config['access_token'])
                    ]
                ];
            }

            // Customer codes are typically alphanumeric
            if (!preg_match('/^[A-Z0-9]+$/i', $this->config['customer_code'])) {
                return [
                    'success' => false,
                    'error' => 'Customer code format appears invalid',
                    'details' => [
                        'customer_code' => $this->config['customer_code']
                    ]
                ];
            }

            return [
                'success' => true,
                'details' => [
                    'message' => 'Credentials format validated successfully',
                    'customer_code' => $this->config['customer_code'],
                    'api_mode' => $this->config['api_mode'] ?? 'test',
                    'validation_type' => 'format_check'
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Credential validation failed',
                'details' => [
                    'exception' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Get warehouse requirement type for DTDC
     * 
     * DTDC accepts full pickup address details
     * 
     * @return string 'full_address'
     */
    public function getWarehouseRequirementType(): string
    {
        return 'full_address';
    }
}
