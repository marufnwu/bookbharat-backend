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

                // Check if carrier already exists
                $carrier = ShippingCarrier::updateOrCreate(
                    ['code' => $carrierConfig['code']],
                    [
                        'name' => $carrierConfig['name'],
                        'display_name' => $carrierConfig['display_name'] ?? $carrierConfig['name'],
                        'logo_url' => $carrierConfig['logo_url'] ?? '',
                        'api_endpoint' => $modeConfig['api_endpoint'] ?? '',
                        'api_key' => $modeConfig['api_key'] ?? '',
                        'api_secret' => $modeConfig['api_secret'] ?? $modeConfig['password'] ?? $modeConfig['login_id'] ?? '',
                        'api_mode' => $mode,
                        'is_active' => $isEnabled,
                        'status' => $isEnabled ? 'active' : 'inactive',
                        'is_primary' => ($carrierKey === $defaultCarrier),
                        'max_weight' => $carrierConfig['max_weight'] ?? 50,
                        'max_insurance_value' => $carrierConfig['max_insurance_value'] ?? 50000,
                        'rating' => 4.5,
                        'success_rate' => 95.00,
                        'total_shipments' => 0,
                        'config' => json_encode([
                            'features' => $carrierConfig['features'] ?? [],
                            'services' => $carrierConfig['services'] ?? [],
                            'pickup_days' => $carrierConfig['pickup_days'] ?? [],
                            'webhook_url' => $carrierConfig['webhook_url'] ?? '',
                            'product_codes' => $carrierConfig['product_codes'] ?? [],
                            'account_id' => $modeConfig['account_id'] ?? '',
                            'client_name' => $modeConfig['client_name'] ?? '',
                            'customer_code' => $modeConfig['customer_code'] ?? '',
                            'email' => $modeConfig['email'] ?? '',
                            'cutoff_time' => $carrierConfig['cutoff_time'] ?? '17:00',
                            'weight_unit' => $carrierConfig['weight_unit'] ?? 'kg',
                            'dimension_unit' => $carrierConfig['dimension_unit'] ?? 'cm',
                        ]),
                    ]
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
