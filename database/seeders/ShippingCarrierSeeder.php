<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ShippingCarrier;
use App\Models\CarrierService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class ShippingCarrierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $carriers = Config::get('shipping-carriers.carriers', []);
            $defaultCarrier = Config::get('shipping-carriers.default', 'delhivery');

            foreach ($carriers as $carrierKey => $carrierConfig) {
                // Insert all carriers but mark as inactive if disabled in config
                $isEnabled = $carrierConfig['enabled'] ?? false;

                $mode = $carrierConfig['api_mode'] ?? 'test';
                $modeConfig = $carrierConfig[$mode] ?? [];

                // Define credential field structure for this carrier
                $credentialFields = $this->getCredentialFieldsStructure($carrierConfig['code']);

                // Extract actual credential values from mode config
                $credentials = [];
                foreach ($credentialFields as $field) {
                    $fieldKey = $field['key'];
                    $credentials[$fieldKey] = $modeConfig[$fieldKey] ?? '';
                }

                // Check if carrier already exists
                $existingCarrier = ShippingCarrier::where('code', $carrierConfig['code'])->first();

                // Prepare base data for all carriers (system-level config)
                $baseData = [
                    'name' => $carrierConfig['name'],
                    'display_name' => $carrierConfig['display_name'] ?? $carrierConfig['name'],
                    'logo_url' => $carrierConfig['logo_url'] ?? '',
                    'max_weight' => $carrierConfig['max_weight'] ?? 50,
                    'max_insurance_value' => $carrierConfig['max_insurance_value'] ?? 50000,
                    'supported_payment_modes' => $carrierConfig['supported_payment_modes'] ?? ['prepaid', 'cod'],
                    'rating' => 4.5,
                    'success_rate' => 95.00,
                ];

                // For new carriers, use config file defaults
                // For existing carriers, preserve admin-configured values
                if (!$existingCarrier) {
                    // New carrier - use all config file values
                    $baseData = array_merge($baseData, [
                        'api_endpoint' => $modeConfig['api_endpoint'] ?? '',
                        'api_key' => $modeConfig['api_key'] ?? '',
                        'api_secret' => $modeConfig['api_secret'] ?? '',
                        'client_name' => $modeConfig['client_name'] ?? '',
                        'api_mode' => $mode,
                        'is_active' => $isEnabled,
                        'status' => $isEnabled ? 'active' : 'inactive',
                        'is_primary' => ($carrierKey === $defaultCarrier),
                        'total_shipments' => 0,
                    ]);
                } else {
                    // Existing carrier - preserve admin-configured values
                    $baseData = array_merge($baseData, [
                        'api_endpoint' => $existingCarrier->api_endpoint ?: ($modeConfig['api_endpoint'] ?? ''),
                        'api_key' => $existingCarrier->api_key ?: ($modeConfig['api_key'] ?? ''),
                        'api_secret' => $existingCarrier->api_secret ?: ($modeConfig['api_secret'] ?? ''),
                        'client_name' => $existingCarrier->client_name ?: ($modeConfig['client_name'] ?? ''),
                        'api_mode' => $existingCarrier->api_mode ?: $mode, // PRESERVE admin-set api_mode
                        'is_active' => $existingCarrier->is_active, // PRESERVE admin-set is_active
                        'status' => $existingCarrier->status,
                        'is_primary' => $existingCarrier->is_primary, // PRESERVE admin-set is_primary
                        'total_shipments' => $existingCarrier->total_shipments,
                    ]);
                }

                // Update config JSON (always update to get latest structure and credentials)
                $baseData['config'] = [
                    'features' => $carrierConfig['features'] ?? [],
                    'services' => $carrierConfig['services'] ?? [],
                    'pickup_days' => $carrierConfig['pickup_days'] ?? [],
                    'webhook_url' => $carrierConfig['webhook_url'] ?? '',
                    'product_codes' => $carrierConfig['product_codes'] ?? [],
                    'cutoff_time' => $carrierConfig['cutoff_time'] ?? '17:00',
                    'weight_unit' => $carrierConfig['weight_unit'] ?? 'kg',
                    'dimension_unit' => $carrierConfig['dimension_unit'] ?? 'cm',
                    // Store credential field structure (admin cannot change these fields, only values)
                    'credential_fields' => $credentialFields,
                    // Preserve existing credentials or use config file defaults
                    'credentials' => $existingCarrier
                        ? ($existingCarrier->config['credentials'] ?? $credentials)
                        : $credentials,
                ];

                $carrier = ShippingCarrier::updateOrCreate(
                    ['code' => $carrierConfig['code']],
                    $baseData
                );

                // Sync carrier services (commented out - table structure doesn't match)
                // if (isset($carrierConfig['services']) && is_array($carrierConfig['services'])) {
                //     // Remove existing services
                //     CarrierService::where('carrier_id', $carrier->id)->delete();

                //     // Add new services
                //     foreach ($carrierConfig['services'] as $serviceCode => $serviceName) {
                //         CarrierService::create([
                //             'carrier_id' => $carrier->id,
                //             'code' => $serviceCode,
                //             'name' => $serviceName,
                //             'is_active' => true,
                //             'transit_time_days' => $this->getTransitTime($serviceCode),
                //             'cutoff_time' => $carrierConfig['cutoff_time'] ?? '17:00',
                //         ]);
                //     }
                // }
            }

            $this->command->info('Shipping carriers synced from config successfully!');
        });
    }

    /**
     * Get credential field structure for each carrier
     * This defines what fields are available for admin to edit
     * Admin can only UPDATE values, not add/remove fields
     */
    private function getCredentialFieldsStructure(string $carrierCode): array
    {
        $structures = [
            'DELHIVERY' => [
                ['key' => 'api_key', 'label' => 'API Token', 'type' => 'password', 'required' => true, 'description' => 'Delhivery API Token (used in Authorization: Token header)'],
            ],

            'BLUEDART' => [
                ['key' => 'license_key', 'label' => 'License Key', 'type' => 'password', 'required' => true, 'description' => 'BlueDart License Key'],
                ['key' => 'login_id', 'label' => 'Login ID', 'type' => 'text', 'required' => true, 'description' => 'BlueDart Login ID'],
            ],

            'XPRESSBEES' => [
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'description' => 'Registered email address'],
                ['key' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'description' => 'Account password'],
                ['key' => 'account_id', 'label' => 'Account ID', 'type' => 'text', 'required' => false, 'description' => 'Optional account identifier'],
            ],

            'DTDC' => [
                ['key' => 'access_token', 'label' => 'Access Token', 'type' => 'password', 'required' => true, 'description' => 'DTDC API Access Token'],
                ['key' => 'customer_code', 'label' => 'Customer Code', 'type' => 'text', 'required' => true, 'description' => 'DTDC Customer Code'],
            ],

            'ECOM_EXPRESS' => [
                ['key' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => true, 'description' => 'Ecom Express Username'],
                ['key' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'description' => 'Ecom Express Password'],
            ],

            'SHADOWFAX' => [
                ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password', 'required' => true, 'description' => 'Shadowfax API Token'],
            ],

            'EKART' => [
                ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => true, 'description' => 'Ekart Client ID (used in auth URL path)'],
                ['key' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => true, 'description' => 'Ekart API Username'],
                ['key' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'description' => 'Ekart API Password'],
            ],

            'SHIPROCKET' => [
                ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'description' => 'Shiprocket account email'],
                ['key' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'description' => 'Shiprocket account password'],
            ],

            'BIGSHIP' => [
                ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'description' => 'BigShip API Key'],
                ['key' => 'api_secret', 'label' => 'API Secret', 'type' => 'password', 'required' => true, 'description' => 'BigShip API Secret'],
            ],

            'RAPIDSHYP' => [
                ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'description' => 'RapidShyp API Key'],
            ],
        ];

        return $structures[$carrierCode] ?? [
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'description' => 'API Key'],
            ['key' => 'api_secret', 'label' => 'API Secret', 'type' => 'password', 'required' => false, 'description' => 'API Secret'],
        ];
    }

    /**
     * Get estimated transit time based on service type
     */
    private function getTransitTime(string $serviceCode): int
    {
        $transitTimes = [
            'INSTANT' => 0,
            'SAME_DAY' => 0,
            'EXPRESS' => 2,
            'NEXT_DAY' => 1,
            'DOMESTIC_PRIORITY' => 2,
            'PREMIUM' => 2,
            'STANDARD' => 5,
            'SURFACE' => 7,
            'GROUND' => 5,
            'LITE' => 6,
            'REGULAR' => 5,
            'B2C' => 4,
            'APEX' => 1,
            'ROS' => 7,
        ];

        return $transitTimes[strtoupper($serviceCode)] ?? 5;
    }
}
