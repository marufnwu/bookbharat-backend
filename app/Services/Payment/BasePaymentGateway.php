<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

abstract class BasePaymentGateway implements PaymentGatewayInterface
{
    protected $config;
    protected $isProduction;
    protected $gatewayName;
    protected $supportedCurrencies = ['INR'];

    public function __construct()
    {
        $this->loadConfiguration();
    }

    /**
     * Load configuration from database
     */
    protected function loadConfiguration()
    {
        $setting = PaymentMethod::where('payment_method', $this->getGatewayKeyword())->first();

        if ($setting) {
            // Merge both credentials and configuration into config
            // This allows hasRequiredConfiguration() to check credentials too
            $this->config = array_merge(
                $setting->credentials ?? [],
                $setting->configuration ?? []
            );
            $this->isProduction = $setting->is_production_mode ?? false;
            $this->supportedCurrencies = ['INR']; // Fixed to INR
        } else {
            Log::warning("Payment gateway {$this->getGatewayKeyword()} configuration not found");
            $this->config = [];
        }
    }

    /**
     * Get gateway keyword for configuration
     */
    abstract protected function getGatewayKeyword(): string;

    /**
     * Get gateway name
     */
    public function getName(): string
    {
        return $this->gatewayName ?? class_basename($this);
    }

    /**
     * Check if gateway is available
     */
    public function isAvailable(): bool
    {
        $setting = PaymentMethod::where('payment_method', $this->getGatewayKeyword())
            ->where('is_enabled', true)
            ->first();

        return $setting !== null && $this->hasRequiredConfiguration();
    }

    /**
     * Check if required configuration exists
     */
    abstract protected function hasRequiredConfiguration(): bool;

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array
    {
        return $this->supportedCurrencies;
    }

    /**
     * Check if currency is supported
     */
    protected function isCurrencySupported(string $currency): bool
    {
        return in_array($currency, $this->supportedCurrencies);
    }

    /**
     * Create payment record
     */
    protected function createPaymentRecord(Order $order, string $status, array $data = []): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'payment_method' => $this->getGatewayKeyword(),
            'amount' => $order->total_amount,
            'currency' => $order->currency ?? 'INR',
            'status' => $status,
            'payment_data' => $data
        ]);
    }

    /**
     * Update payment record
     */
    protected function updatePaymentRecord(Payment $payment, string $status, array $data = []): Payment
    {
        $payment->update([
            'status' => $status,
            'payment_data' => array_merge($payment->payment_data ?? [], $data)
        ]);

        return $payment;
    }

    /**
     * Log gateway activity
     */
    protected function logActivity(string $action, array $data = [], string $level = 'info')
    {
        $logData = [
            'gateway' => $this->getName(),
            'action' => $action,
            'data' => $data,
            'timestamp' => now()
        ];

        switch ($level) {
            case 'error':
                Log::error("Payment Gateway Error: {$action}", $logData);
                break;
            case 'warning':
                Log::warning("Payment Gateway Warning: {$action}", $logData);
                break;
            default:
                Log::info("Payment Gateway: {$action}", $logData);
        }
    }

    /**
     * Format response
     */
    protected function formatResponse(bool $success, array $data = [], string $message = ''): array
    {
        return [
            'success' => $success,
            'gateway' => $this->getName(),
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toIso8601String()
        ];
    }

    /**
     * Handle gateway exception
     */
    protected function handleException(\Exception $e, string $action): array
    {
        $this->logActivity($action, [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 'error');

        return $this->formatResponse(false, [], $e->getMessage());
    }

    /**
     * Get configuration value
     */
    protected function getConfig(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Get API endpoint based on environment
     */
    protected function getApiEndpoint(string $productionUrl, string $sandboxUrl): string
    {
        return $this->isProduction ? $productionUrl : $sandboxUrl;
    }

    /**
     * Validate order amount
     */
    protected function validateOrderAmount(Order $order): bool
    {
        if ($order->total_amount <= 0) {
            throw new \InvalidArgumentException('Invalid order amount');
        }

        return true;
    }

    /**
     * Generate unique transaction ID
     */
    protected function generateTransactionId(Order $order): string
    {
        return strtoupper($this->getGatewayKeyword()) . '_' . $order->id . '_' . time();
    }

    /**
     * Get webhook URL
     */
    protected function getWebhookUrl(): string
    {
        return route('api.payment.webhook', ['gateway' => $this->getGatewayKeyword()]);
    }

    /**
     * Get callback URL
     */
    protected function getCallbackUrl(): string
    {
        return route('api.payment.callback', ['gateway' => $this->getGatewayKeyword()]);
    }

    /**
     * Get display name (for UI)
     */
    public function getDisplayName(): string
    {
        $setting = PaymentMethod::where('payment_method', $this->getGatewayKeyword())->first();
        return $setting ? $setting->display_name : $this->getName();
    }

    /**
     * Get description (for UI)
     */
    public function getDescription(): string
    {
        $setting = PaymentMethod::where('payment_method', $this->getGatewayKeyword())->first();
        return $setting ? $setting->description : '';
    }

    /**
     * Validate configuration
     */
    public function validateConfiguration(): bool
    {
        return $this->hasRequiredConfiguration();
    }

    /**
     * Default webhook signature validation (override in specific gateways)
     */
    public function validateWebhookSignature(Request $request): bool
    {
        return true;
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $paymentId): string
    {
        $payment = Payment::find($paymentId);
        return $payment ? $payment->status : 'unknown';
    }
}
