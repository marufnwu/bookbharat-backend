<?php

namespace App\Services\Payment\Contracts;

use App\Models\Order;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Initialize payment for an order
     *
     * @param Order $order
     * @param array $options
     * @return array
     */
    public function initiatePayment(Order $order, array $options = []): array;

    /**
     * Verify payment status
     *
     * @param string $paymentId
     * @return array
     */
    public function verifyPayment(string $paymentId): array;

    /**
     * Process callback from payment gateway
     *
     * @param Request $request
     * @return array
     */
    public function processCallback(Request $request): array;

    /**
     * Process webhook from payment gateway
     *
     * @param Request $request
     * @return array
     */
    public function processWebhook(Request $request): array;

    /**
     * Initiate refund for a payment
     *
     * @param string $paymentId
     * @param float $amount
     * @param array $options
     * @return array
     */
    public function refundPayment(string $paymentId, float $amount, array $options = []): array;

    /**
     * Get gateway name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if gateway is available
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get supported currencies
     *
     * @return array
     */
    public function getSupportedCurrencies(): array;

    /**
     * Validate webhook signature
     *
     * @param Request $request
     * @return bool
     */
    public function validateWebhookSignature(Request $request): bool;
}