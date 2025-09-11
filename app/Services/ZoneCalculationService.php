<?php

namespace App\Services;

use App\Models\Pincode;
use Illuminate\Support\Facades\Log;

class ZoneCalculationService
{
    protected $metroCityPrefixes = [
        '110', '121', '122', // Delhi NCR
        '400', '401', '421', '422', // Mumbai
        '560', '562', // Bangalore  
        '600', '603', // Chennai
        '500', '501', // Hyderabad
        '700', '711', // Kolkata
        '380', '382', // Ahmedabad
        '411', '412', // Pune
    ];

    protected $northeastJKPrefixes = [
        '190', '191', '192', '193', '194', // J&K
        '781', '782', '783', '784', '785', // Assam
        '793', '794', // Meghalaya
        '797', // Mizoram
        '798', // Manipur
        '799', // Tripura
        '790', '791', '792', // Arunachal Pradesh
        '795', '796', // Nagaland
        '737', // Sikkim
    ];

    /**
     * Determine shipping zone based on pickup and delivery pincodes
     */
    public function determineZone(string $pickupPincode, string $deliveryPincode): string
    {
        // First try to get from database cache
        $cacheKey = "shipping_zone_{$pickupPincode}_{$deliveryPincode}";
        
        return cache()->remember($cacheKey, 3600, function () use ($pickupPincode, $deliveryPincode) {
            return $this->calculateZone($pickupPincode, $deliveryPincode);
        });
    }

    /**
     * Calculate the actual zone using business logic
     */
    protected function calculateZone(string $pickupPincode, string $deliveryPincode): string
    {
        try {
            // Get pincode details from database first
            $pickupDetails = Pincode::getPincodeDetails($pickupPincode);
            $deliveryDetails = Pincode::getPincodeDetails($deliveryPincode);

            // If we have database entries, use them for enhanced logic
            if ($pickupDetails && $deliveryDetails) {
                return $this->calculateZoneFromDatabase($pickupDetails, $deliveryDetails);
            }

            // Fallback to legacy logic using pincode prefixes
            return $this->calculateZoneFromPrefixes($pickupPincode, $deliveryPincode);

        } catch (\Exception $e) {
            Log::error('Zone calculation failed', [
                'pickup' => $pickupPincode,
                'delivery' => $deliveryPincode,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to default zone
            return 'D';
        }
    }

    /**
     * Calculate zone using database pincode details
     */
    protected function calculateZoneFromDatabase(array $pickupDetails, array $deliveryDetails): string
    {
        $pickupPincode = $pickupDetails['pincode'] ?? '';
        $deliveryPincode = $deliveryDetails['pincode'] ?? '';
        $pickupPrefix3 = substr($pickupPincode, 0, 3);
        $deliveryPrefix3 = substr($deliveryPincode, 0, 3);

        // Zone A: Same city
        if ($pickupDetails['city'] === $deliveryDetails['city'] && 
            !empty($pickupDetails['city'])) {
            return 'A';
        }

        // Zone E: Northeast or J&K (based on pincode prefixes)
        if (in_array($pickupPrefix3, $this->northeastJKPrefixes) || 
            in_array($deliveryPrefix3, $this->northeastJKPrefixes)) {
            return 'E';
        }

        // Zone C: Metro to Metro (based on pincode prefixes)
        if (in_array($pickupPrefix3, $this->metroCityPrefixes) && 
            in_array($deliveryPrefix3, $this->metroCityPrefixes)) {
            return 'C';
        }

        // Zone B: Same state
        if ($pickupDetails['state'] === $deliveryDetails['state'] && 
            !empty($pickupDetails['state'])) {
            return 'B';
        }

        // Zone D: Rest of India (default)
        return 'D';
    }

    /**
     * Fallback calculation using pincode prefixes
     */
    protected function calculateZoneFromPrefixes(string $pickupPincode, string $deliveryPincode): string
    {
        $pickupPrefix3 = substr($pickupPincode, 0, 3);
        $deliveryPrefix3 = substr($deliveryPincode, 0, 3);
        $pickupPrefix2 = substr($pickupPincode, 0, 2);
        $deliveryPrefix2 = substr($deliveryPincode, 0, 2);

        // Zone A: Same city (first 3 digits same)
        if ($pickupPrefix3 === $deliveryPrefix3) {
            return 'A';
        }

        // Zone E: Northeast or J&K
        if (in_array($pickupPrefix3, $this->northeastJKPrefixes) || 
            in_array($deliveryPrefix3, $this->northeastJKPrefixes)) {
            return 'E';
        }

        // Zone C: Metro to Metro
        if (in_array($pickupPrefix3, $this->metroCityPrefixes) && 
            in_array($deliveryPrefix3, $this->metroCityPrefixes)) {
            return 'C';
        }

        // Zone B: Same state (first 2 digits same)
        if ($pickupPrefix2 === $deliveryPrefix2) {
            return 'B';
        }

        // Zone D: Rest of India (default)
        return 'D';
    }

    /**
     * Get zone information with details
     */
    public function getZoneDetails(string $pickupPincode, string $deliveryPincode): array
    {
        $zone = $this->determineZone($pickupPincode, $deliveryPincode);
        
        $pickupDetails = Pincode::getPincodeDetails($pickupPincode);
        $deliveryDetails = Pincode::getPincodeDetails($deliveryPincode);

        return [
            'zone' => $zone,
            'zone_name' => $this->getZoneName($zone),
            'pickup_details' => $pickupDetails,
            'delivery_details' => $deliveryDetails,
            'estimated_days' => $this->getEstimatedDeliveryDays($zone, $deliveryDetails),
            'cod_available' => $this->isCodAvailable($deliveryPincode),
        ];
    }

    /**
     * Get zone name
     */
    public function getZoneName(string $zone): string
    {
        $names = [
            'A' => 'Same City',
            'B' => 'Same State/Region',
            'C' => 'Metro to Metro',
            'D' => 'Rest of India',
            'E' => 'Northeast & J&K',
        ];

        return $names[$zone] ?? 'Unknown Zone';
    }

    /**
     * Get estimated delivery days for a zone
     */
    public function getEstimatedDeliveryDays(string $zone, ?array $deliveryDetails = null): int
    {
        // Default estimates by zone (pincode model doesn't have delivery days)
        $estimates = [
            'A' => 1, // Same city
            'B' => 2, // Same state
            'C' => 3, // Metro to Metro
            'D' => 5, // Rest of India
            'E' => 7, // Northeast & J&K
        ];

        return $estimates[$zone] ?? 5;
    }

    /**
     * Check if COD is available for delivery pincode
     */
    public function isCodAvailable(string $deliveryPincode): bool
    {
        return true; // Assume COD available for all serviceable pincodes
    }

    /**
     * Check if a pincode is serviceable
     */
    public function isServiceable(string $pincode): bool
    {
        // Check in pin_codes table
        if (Pincode::isServiceable($pincode)) {
            return true;
        }

        // If no database entries, assume serviceable for Indian pincodes
        return preg_match('/^[1-9][0-9]{5}$/', $pincode);
    }

    /**
     * Get zone multiplier for pricing adjustments
     */
    public function getZoneMultiplier(string $deliveryPincode): float
    {
        // No zone multiplier in basic pincode model, return default
        return 1.0;
    }

    /**
     * Check if delivery location is remote
     */
    public function isRemoteLocation(string $deliveryPincode): bool
    {
        // Check if pincode is in remote areas (simplified logic)
        $pincodePrefix = substr($deliveryPincode, 0, 3);
        return in_array($pincodePrefix, $this->northeastJKPrefixes);
    }

    /**
     * Get all zones with their descriptions
     */
    public function getAllZones(): array
    {
        return [
            'A' => [
                'name' => 'Same City',
                'description' => 'Pickup and delivery within the same city',
                'typical_days' => 1,
            ],
            'B' => [
                'name' => 'Same State/Region',
                'description' => 'Pickup and delivery within the same state or region',
                'typical_days' => 2,
            ],
            'C' => [
                'name' => 'Metro to Metro',
                'description' => 'Pickup and delivery between major metro cities',
                'typical_days' => 3,
            ],
            'D' => [
                'name' => 'Rest of India',
                'description' => 'Any pickup/delivery in Rest of India (excluding Northeast & J&K)',
                'typical_days' => 5,
            ],
            'E' => [
                'name' => 'Northeast & J&K',
                'description' => 'Any pickup/delivery in Northeast states or Jammu & Kashmir',
                'typical_days' => 7,
            ],
        ];
    }
}