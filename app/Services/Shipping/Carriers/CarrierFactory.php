<?php

namespace App\Services\Shipping\Carriers;

use App\Models\ShippingCarrier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Services\Shipping\Contracts\CarrierAdapterInterface;

class CarrierFactory
{
    /**
     * Create carrier adapter instance from database model
     */
    public function make(ShippingCarrier $carrier): CarrierAdapterInterface
    {
        $config = $this->prepareConfig($carrier);

        return $this->createAdapter($carrier->code, $config);
    }

    /**
     * Create carrier adapter from config by code
     */
    public function makeFromConfig(string $carrierCode): CarrierAdapterInterface
    {
        $carrierConfigs = Config::get('shipping-carriers.carriers', []);

        // Find carrier config by matching code
        $configKey = null;
        $carrierConfig = null;

        foreach ($carrierConfigs as $key => $config) {
            if (strtoupper($config['code']) === strtoupper($carrierCode)) {
                $configKey = $key;
                $carrierConfig = $config;
                break;
            }
        }

        if (!$carrierConfig) {
            throw new \Exception("Carrier configuration not found: {$carrierCode}");
        }

        if (!$carrierConfig['enabled']) {
            throw new \Exception("Carrier is not enabled: {$carrierCode}");
        }

        $config = $this->prepareConfigFromFile($configKey, $carrierConfig);

        return $this->createAdapter($carrierCode, $config);
    }

    /**
     * Create the appropriate adapter based on carrier code
     */
    protected function createAdapter(string $carrierCode, array $config): CarrierAdapterInterface
    {
        switch (strtolower($carrierCode)) {
            case 'delhivery':
                return new DelhiveryAdapter($config);
            case 'bluedart':
                return new BluedartAdapter($config);
            case 'xpressbees':
                return new XpressbeesAdapter($config);
            case 'dtdc':
                return new DtdcAdapter($config);
            case 'ecom_express':
                return new EcomExpressAdapter($config);
            case 'shadowfax':
                return new ShadowfaxAdapter($config);
            case 'shiprocket':
                return new ShiprocketAdapter($config);
            case 'fedex':
                return new FedexAdapter($config);
            case 'ekart':
                return new EkartAdapter($config);
            case 'rapidshyp':
                return new RapidshypAdapter($config);
            case 'bigship':
                return new BigshipAdapter($config);
            default:
                throw new \Exception("Unsupported carrier: {$carrierCode}");
        }
    }

    /**
     * Prepare configuration for carrier adapter from database model
     */
    protected function prepareConfig(ShippingCarrier $carrier): array
    {
        // First try to load from config file for comprehensive settings
        $carrierConfigs = Config::get('shipping-carriers.carriers', []);
        $fileConfig = null;

        foreach ($carrierConfigs as $key => $config) {
            if (strtoupper($config['code']) === strtoupper($carrier->code)) {
                $fileConfig = $this->prepareConfigFromFile($key, $config);
                break;
            }
        }

        // If config file exists, merge with database settings
        if ($fileConfig) {
            // Get credentials from config.credentials
            $dbConfig = $carrier->config;
            $credentials = $dbConfig['credentials'] ?? [];

            $mergedConfig = array_merge($fileConfig, [
                'carrier_id' => $carrier->id,
                'is_active' => $carrier->is_active,
                'is_primary' => $carrier->is_primary,
                // Override with database values if they exist
                'api_endpoint' => !empty($carrier->api_endpoint) ? $carrier->api_endpoint : $fileConfig['api_endpoint'],
                'api_key' => !empty($carrier->api_key) ? $this->decryptValue($carrier->api_key) : $fileConfig['api_key'],
                'api_secret' => !empty($carrier->api_secret) ? $this->decryptValue($carrier->api_secret) : $fileConfig['api_secret'],
                'api_mode' => $carrier->api_mode ?? ($fileConfig['api_mode'] ?? 'test'),
            ]);

            // Merge credentials from config.credentials (new structure)
            foreach ($credentials as $key => $value) {
                if (!empty($value)) {
                    $mergedConfig[$key] = $this->decryptValue($value);
                }
            }

            return $mergedConfig;
        }

        // Fallback to pure database config (for backward compatibility)
        $dbConfig = is_string($carrier->config) ? json_decode($carrier->config, true) : ($carrier->config ?? []);

        return [
            'carrier_id' => $carrier->id,
            'code' => $carrier->code,
            'name' => $carrier->name,
            'display_name' => $carrier->display_name ?? $carrier->name,
            'api_endpoint' => $carrier->api_endpoint,
            'api_key' => $this->decryptValue($carrier->api_key),
            'api_secret' => $this->decryptValue($carrier->api_secret),
            'api_mode' => $carrier->api_mode ?? 'test',
            'is_active' => $carrier->is_active,
            'is_primary' => $carrier->is_primary,
            'features' => $dbConfig['features'] ?? [],
            'services' => $dbConfig['services'] ?? [],
            'webhook_url' => $dbConfig['webhook_url'] ?? '',
            'pickup_days' => $dbConfig['pickup_days'] ?? [],
            'weight_unit' => $carrier->weight_unit ?? 'kg',
            'dimension_unit' => $carrier->dimension_unit ?? 'cm',
            'max_weight' => $carrier->max_weight ?? 50,
            'max_insurance_value' => $carrier->max_insurance_value ?? 50000,
            'cutoff_time' => $carrier->cutoff_time ?? '17:00',
        ];
    }

    /**
     * Prepare configuration from config file
     */
    protected function prepareConfigFromFile(string $carrierKey, array $carrierConfig): array
    {
        $mode = $carrierConfig['api_mode'] ?? 'test';
        $modeConfig = $carrierConfig[$mode] ?? [];

        // Get pickup and return addresses from config
        $pickupLocation = Config::get('shipping-carriers.pickup_location', []);
        $returnAddress = Config::get('shipping-carriers.return_address', []);
        $rules = Config::get('shipping-carriers.rules', []);

        return [
            'code' => $carrierConfig['code'],
            'name' => $carrierConfig['name'],
            'display_name' => $carrierConfig['display_name'] ?? $carrierConfig['name'],
            'logo_url' => $carrierConfig['logo_url'] ?? '',
            'api_endpoint' => $modeConfig['api_endpoint'] ?? '',
            'api_key' => $modeConfig['api_key'] ?? '',
            'api_secret' => $modeConfig['api_secret'] ??
                         $modeConfig['password'] ??
                         $modeConfig['login_id'] ??
                         $modeConfig['license_key'] ?? '',
            'api_mode' => $mode,
            'features' => $carrierConfig['features'] ?? [],
            'services' => $carrierConfig['services'] ?? [],
            'webhook_url' => $carrierConfig['webhook_url'] ?? '',
            'weight_unit' => $carrierConfig['weight_unit'] ?? 'kg',
            'dimension_unit' => $carrierConfig['dimension_unit'] ?? 'cm',
            'max_weight' => $carrierConfig['max_weight'] ?? 50,
            'max_insurance_value' => $carrierConfig['max_insurance_value'] ?? 50000,
            'cutoff_time' => $carrierConfig['cutoff_time'] ?? '17:00',
            'pickup_days' => $carrierConfig['pickup_days'] ?? [],
            'product_codes' => $carrierConfig['product_codes'] ?? [],

            // Additional mode-specific config
            'account_id' => $modeConfig['account_id'] ?? '',
            'client_name' => $modeConfig['client_name'] ?? '',
            'customer_code' => $modeConfig['customer_code'] ?? '',
            'email' => $modeConfig['email'] ?? '',
            'username' => $modeConfig['username'] ?? '',
            'access_token' => $modeConfig['access_token'] ?? '',
            'api_token' => $modeConfig['api_token'] ?? '',

            // Addresses
            'pickup_location' => $pickupLocation,
            'return_address' => $returnAddress,

            // Rules
            'auto_select' => $rules['auto_select_carrier'] ?? true,
            'selection_priority' => $rules['selection_priority'] ?? 'cost',
            'max_retry_attempts' => $rules['max_retry_attempts'] ?? 3,
            'enable_insurance' => $rules['enable_insurance'] ?? true,
            'insurance_threshold' => $rules['insurance_threshold'] ?? 5000,
            'enable_real_time_tracking' => $rules['enable_real_time_tracking'] ?? true,
        ];
    }

    /**
     * Decrypt a value if it's encrypted
     */
    protected function decryptValue($value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return decrypt($value);
        } catch (\Exception $e) {
            // Value is not encrypted, return as-is
            Log::debug('Value is not encrypted, using as-is');
            return $value;
        }
    }

    /**
     * Check if carrier is available
     */
    public function isAvailable(string $carrierCode): bool
    {
        $carrierConfigs = Config::get('shipping-carriers.carriers', []);

        foreach ($carrierConfigs as $config) {
            if (strtoupper($config['code']) === strtoupper($carrierCode) && $config['enabled']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all available carrier codes from config
     */
    public function getAvailableCarriers(): array
    {
        $carrierConfigs = Config::get('shipping-carriers.carriers', []);
        $available = [];

        foreach ($carrierConfigs as $key => $config) {
            if ($config['enabled']) {
                $available[$config['code']] = $config['display_name'] ?? $config['name'];
            }
        }

        return $available;
    }
}
