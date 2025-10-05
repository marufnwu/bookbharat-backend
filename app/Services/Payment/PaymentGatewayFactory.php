<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Gateways\RazorpayGateway;
use App\Services\Payment\Gateways\PayuGateway;
use App\Services\Payment\Gateways\PhonepeGateway;
use App\Services\Payment\Gateways\CashfreeGateway;
use App\Services\Payment\Gateways\CodGateway;
use App\Services\Payment\Gateways\StripeGateway;
use App\Services\Payment\Gateways\PaypalGateway;
use App\Models\PaymentSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentGatewayFactory
{
    /**
     * Available gateway mappings
     */
    protected static $gateways = [
        'razorpay' => RazorpayGateway::class,
        'payu' => PayuGateway::class,
        'phonepe' => PhonepeGateway::class,
        'cashfree' => CashfreeGateway::class,
        'cod' => CodGateway::class,
        // 'stripe' => StripeGateway::class,
        // 'paypal' => PaypalGateway::class,
    ];

    /**
     * Gateway instances cache
     */
    protected static $instances = [];

    /**
     * Create a payment gateway instance
     *
     * @param string $gateway
     * @return PaymentGatewayInterface
     * @throws \Exception
     */
    public static function create(string $gateway): PaymentGatewayInterface
    {
        $gateway = strtolower($gateway);

        // Check if instance is already cached
        if (isset(self::$instances[$gateway])) {
            return self::$instances[$gateway];
        }

        // Check if gateway is supported
        if (!isset(self::$gateways[$gateway])) {
            throw new \Exception("Payment gateway '{$gateway}' is not supported");
        }

        // Check if gateway is enabled in database
        if (!self::isGatewayEnabled($gateway)) {
            throw new \Exception("Payment gateway '{$gateway}' is not enabled");
        }

        // Create gateway instance
        $gatewayClass = self::$gateways[$gateway];

        if (!class_exists($gatewayClass)) {
            throw new \Exception("Payment gateway class '{$gatewayClass}' not found");
        }

        $instance = new $gatewayClass();

        if (!$instance instanceof PaymentGatewayInterface) {
            throw new \Exception("Gateway class must implement PaymentGatewayInterface");
        }

        // Cache the instance
        self::$instances[$gateway] = $instance;

        return $instance;
    }

    /**
     * Get all available gateways
     *
     * @return array
     */
    public static function getAvailableGateways(): array
    {
        $cacheKey = 'payment_gateways_available';

        return Cache::remember($cacheKey, 3600, function () {
            $availableGateways = [];

            foreach (self::$gateways as $key => $gatewayClass) {
                try {
                    $gateway = self::create($key);

                    if ($gateway->isAvailable()) {
                        $setting = PaymentSetting::where('unique_keyword', $key)->first();

                        $gatewayData = [
                            'gateway' => $key,
                            'name' => $gateway->getName(),
                            'display_name' => $setting->name ?? $gateway->getName(),
                            'description' => $setting->description ?? '',
                            'supported_currencies' => $gateway->getSupportedCurrencies(),
                            'priority' => $setting->priority ?? 0,
                            'is_production' => $setting->is_production ?? false
                        ];

                        // Add COD-specific configuration if available
                        if ($setting && strpos($key, 'cod') !== false) {
                            $config = $setting->configuration ?? [];

                            // Include advance payment settings
                            if (isset($config['advance_payment'])) {
                                $gatewayData['advance_payment'] = $config['advance_payment'];
                            }

                            // Include service charges settings
                            if (isset($config['service_charges'])) {
                                $gatewayData['service_charges'] = $config['service_charges'];
                            }
                        }

                        $availableGateways[] = $gatewayData;
                    }
                } catch (\Exception $e) {
                    Log::debug("Gateway {$key} is not available: " . $e->getMessage());
                }
            }

            // Sort by priority
            usort($availableGateways, function ($a, $b) {
                return $b['priority'] <=> $a['priority'];
            });

            return $availableGateways;
        });
    }

    /**
     * Check if a gateway is enabled
     *
     * @param string $gateway
     * @return bool
     */
    protected static function isGatewayEnabled(string $gateway): bool
    {
        $cacheKey = "payment_gateway_enabled_{$gateway}";

        return Cache::remember($cacheKey, 3600, function () use ($gateway) {
            $setting = PaymentSetting::where('unique_keyword', $gateway)
                ->where('is_active', true)
                ->first();

            return $setting !== null;
        });
    }

    /**
     * Clear gateway cache
     */
    public static function clearCache(): void
    {
        Cache::forget('payment_gateways_available');

        foreach (array_keys(self::$gateways) as $gateway) {
            Cache::forget("payment_gateway_enabled_{$gateway}");
        }

        self::$instances = [];
    }

    /**
     * Register a new gateway
     *
     * @param string $key
     * @param string $className
     */
    public static function register(string $key, string $className): void
    {
        self::$gateways[$key] = $className;
        self::clearCache();
    }

    /**
     * Get gateway for a specific order amount and currency
     *
     * @param float $amount
     * @param string $currency
     * @return array
     */
    public static function getGatewaysForOrder(float $amount, string $currency = 'INR'): array
    {
        $allGateways = self::getAvailableGateways();

        return array_filter($allGateways, function ($gateway) use ($currency) {
            return in_array($currency, $gateway['supported_currencies']);
        });
    }

    /**
     * Get the best gateway for an order
     *
     * @param float $amount
     * @param string $currency
     * @param string $preferredGateway
     * @return PaymentGatewayInterface|null
     */
    public static function getBestGateway(float $amount, string $currency = 'INR', ?string $preferredGateway = null): ?PaymentGatewayInterface
    {
        try {
            // If preferred gateway is specified and available, use it
            if ($preferredGateway) {
                $gateway = self::create($preferredGateway);
                if ($gateway->isAvailable() && in_array($currency, $gateway->getSupportedCurrencies())) {
                    return $gateway;
                }
            }

            // Get all available gateways for the order
            $availableGateways = self::getGatewaysForOrder($amount, $currency);

            if (empty($availableGateways)) {
                return null;
            }

            // Return the highest priority gateway
            $bestGateway = reset($availableGateways);
            return self::create($bestGateway['gateway']);

        } catch (\Exception $e) {
            Log::error('Failed to get best gateway', [
                'amount' => $amount,
                'currency' => $currency,
                'preferred' => $preferredGateway,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Process payment using specified gateway
     *
     * @param string $gateway
     * @param \App\Models\Order $order
     * @param array $options
     * @return array
     */
    public static function processPayment(string $gateway, \App\Models\Order $order, array $options = []): array
    {
        try {
            $gatewayInstance = self::create($gateway);
            return $gatewayInstance->initiatePayment($order, $options);
        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'gateway' => $gateway,
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'gateway' => $gateway
            ];
        }
    }
}