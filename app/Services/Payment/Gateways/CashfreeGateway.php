<?php

namespace App\Services\Payment\Gateways;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class CashfreeGateway implements PaymentGatewayInterface
{
    protected $config;
    protected $settings;
    protected $baseUrl;

    public function __construct()
    {
        $this->settings = PaymentSetting::where('unique_keyword', 'cashfree')->first();
        $this->config = $this->settings ? $this->settings->configuration : [];

        // Set base URL based on production/test mode
        $this->baseUrl = ($this->settings && $this->settings->is_production)
            ? 'https://api.cashfree.com/pg'
            : 'https://sandbox.cashfree.com/pg';
    }

    public function getName(): string
    {
        return 'Cashfree';
    }

    public function getDisplayName(): string
    {
        return $this->settings->name ?? 'Cashfree';
    }

    public function getDescription(): string
    {
        return $this->settings->description ?? 'Pay securely using UPI, Cards, Net Banking';
    }

    public function isAvailable(): bool
    {
        return $this->settings ? $this->settings->is_active : false;
    }

    public function getSupportedCurrencies(): array
    {
        return $this->settings->supported_currencies ?? ['INR'];
    }

    public function validateConfiguration(): bool
    {
        return !empty($this->config['app_id']) && !empty($this->config['secret_key']);
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        try {
            if (!$this->validateConfiguration()) {
                throw new \Exception('Cashfree gateway is not properly configured');
            }

            // Create order at Cashfree
            $response = Http::withHeaders([
                'x-client-id' => $this->config['app_id'],
                'x-client-secret' => $this->config['secret_key'],
                'x-api-version' => '2022-09-01',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/orders', [
                'order_id' => 'order_' . $order->id,
                'order_amount' => $order->total_amount,
                'order_currency' => $order->currency ?? 'INR',
                'customer_details' => [
                    'customer_id' => 'customer_' . $order->user_id,
                    'customer_email' => $order->customer_email,
                    'customer_phone' => $order->customer_phone,
                    'customer_name' => $order->customer_name
                ],
                'order_meta' => [
                    'return_url' => $options['return_url'] ?? config('app.url') . '/payment/callback/cashfree',
                    'notify_url' => config('app.url') . '/api/v1/payment/webhook/cashfree'
                ]
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to create Cashfree order: ' . $response->body());
            }

            $cashfreeOrder = $response->json();

            // Create payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'gateway' => 'cashfree',
                'method' => 'online',
                'amount' => $order->total_amount,
                'currency' => $order->currency ?? 'INR',
                'status' => 'pending',
                'payment_data' => [
                    'cf_order_id' => $cashfreeOrder['cf_order_id'] ?? null,
                    'payment_session_id' => $cashfreeOrder['payment_session_id'] ?? null,
                    'order_token' => $cashfreeOrder['order_token'] ?? null
                ]
            ]);

            // Update order with payment metadata
            $order->update([
                'payment_metadata' => array_merge($order->payment_metadata ?? [], [
                    'cf_order_id' => $cashfreeOrder['cf_order_id'] ?? null
                ])
            ]);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'payment_url' => $cashfreeOrder['payment_link'] ?? null,
                'payment_session_id' => $cashfreeOrder['payment_session_id'] ?? null,
                'cf_order_id' => $cashfreeOrder['cf_order_id'] ?? null,
                'message' => 'Payment initiated successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Cashfree payment initiation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function verifyPayment(string $paymentId): array
    {
        try {
            $payment = Payment::find($paymentId);
            if (!$payment) {
                throw new \Exception('Payment record not found');
            }

            $cfOrderId = $payment->payment_data['cf_order_id'] ?? null;
            if (!$cfOrderId) {
                throw new \Exception('Cashfree order ID not found');
            }

            // Get order status from Cashfree
            $response = Http::withHeaders([
                'x-client-id' => $this->config['app_id'],
                'x-client-secret' => $this->config['secret_key'],
                'x-api-version' => '2022-09-01'
            ])->get($this->baseUrl . '/orders/' . $cfOrderId);

            if (!$response->successful()) {
                throw new \Exception('Failed to verify payment: ' . $response->body());
            }

            $orderData = $response->json();
            $status = 'pending';

            if ($orderData['order_status'] === 'PAID') {
                $status = 'completed';
            } elseif (in_array($orderData['order_status'], ['EXPIRED', 'CANCELLED', 'FAILED'])) {
                $status = 'failed';
            }

            // Update payment record
            if ($status !== $payment->status) {
                $payment->update([
                    'status' => $status,
                    'payment_data' => array_merge($payment->payment_data ?? [], [
                        'verification_response' => $orderData
                    ])
                ]);
            }

            return [
                'success' => true,
                'payment_status' => $status,
                'cf_order' => $orderData,
                'message' => 'Payment verified'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function processCallback(Request $request): array
    {
        try {
            Log::info('Cashfree callback received', $request->all());

            $orderId = $request->input('order_id');
            if (!$orderId) {
                throw new \Exception('Order ID not received');
            }

            // Extract numeric order ID
            $orderIdParts = explode('_', $orderId);
            $numericOrderId = end($orderIdParts);

            // Find order and payment
            $order = Order::find($numericOrderId);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            $payment = Payment::where('order_id', $order->id)
                ->where('gateway', 'cashfree')
                ->first();

            if (!$payment) {
                throw new \Exception('Payment record not found');
            }

            // Verify payment
            $verifyResult = $this->verifyPayment($payment->id);

            if ($verifyResult['success'] && $verifyResult['payment_status'] === 'completed') {
                // Update order payment status
                $order->update([
                    'payment_status' => 'paid',
                    'payment_metadata' => array_merge($order->payment_metadata ?? [], [
                        'paid_at' => now()->toIso8601String(),
                        'cf_payment_id' => $request->input('cf_payment_id')
                    ])
                ]);

                return [
                    'success' => true,
                    'order_id' => $order->id,
                    'payment_status' => 'completed',
                    'message' => 'Payment successful'
                ];
            }

            return [
                'success' => false,
                'order_id' => $order->id,
                'payment_status' => $verifyResult['payment_status'] ?? 'failed',
                'message' => 'Payment verification failed'
            ];

        } catch (\Exception $e) {
            Log::error('Cashfree callback processing failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function processWebhook(Request $request): array
    {
        try {
            Log::info('Cashfree webhook received', $request->all());

            if (!$this->validateWebhookSignature($request)) {
                throw new \Exception('Invalid webhook signature');
            }

            $data = $request->input('data');
            if (!$data) {
                throw new \Exception('Webhook data not found');
            }

            $orderId = $data['order']['order_id'] ?? null;
            if (!$orderId) {
                throw new \Exception('Order ID not found in webhook');
            }

            // Process based on event type
            $eventType = $request->input('type');

            switch ($eventType) {
                case 'PAYMENT_SUCCESS':
                case 'PAYMENT_FAILED':
                    // Process payment status update
                    $this->processWebhookPaymentUpdate($data, $eventType);
                    break;

                default:
                    Log::info('Unhandled Cashfree webhook event', ['type' => $eventType]);
            }

            return [
                'success' => true,
                'message' => 'Webhook processed'
            ];

        } catch (\Exception $e) {
            Log::error('Cashfree webhook processing failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function refundPayment(string $paymentId, float $amount, array $options = []): array
    {
        try {
            $payment = Payment::find($paymentId);
            if (!$payment) {
                throw new \Exception('Payment record not found');
            }

            $cfOrderId = $payment->payment_data['cf_order_id'] ?? null;
            if (!$cfOrderId) {
                throw new \Exception('Cashfree order ID not found');
            }

            // Create refund at Cashfree
            $response = Http::withHeaders([
                'x-client-id' => $this->config['app_id'],
                'x-client-secret' => $this->config['secret_key'],
                'x-api-version' => '2022-09-01',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/orders/' . $cfOrderId . '/refunds', [
                'refund_amount' => $amount,
                'refund_id' => 'refund_' . $payment->id . '_' . time(),
                'refund_note' => $options['reason'] ?? 'Refund requested'
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to create refund: ' . $response->body());
            }

            $refundData = $response->json();

            // Update payment record
            $payment->update([
                'payment_data' => array_merge($payment->payment_data ?? [], [
                    'refunds' => array_merge($payment->payment_data['refunds'] ?? [], [$refundData])
                ])
            ]);

            return [
                'success' => true,
                'refund_id' => $refundData['cf_refund_id'] ?? null,
                'refund_status' => $refundData['refund_status'] ?? null,
                'message' => 'Refund initiated successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function validateWebhookSignature(Request $request): bool
    {
        $signature = $request->header('x-webhook-signature');
        if (!$signature) {
            return false;
        }

        $rawBody = $request->getContent();
        $timestamp = $request->header('x-webhook-timestamp');

        $signatureData = $timestamp . $rawBody;
        $expectedSignature = base64_encode(hash_hmac('sha256', $signatureData, $this->config['webhook_secret'] ?? '', true));

        return hash_equals($expectedSignature, $signature);
    }

    public function getPaymentStatus(string $paymentId): string
    {
        $payment = Payment::find($paymentId);
        return $payment ? $payment->status : 'unknown';
    }

    protected function processWebhookPaymentUpdate($data, $eventType)
    {
        $orderId = $data['order']['order_id'] ?? null;
        if (!$orderId) {
            return;
        }

        // Extract numeric order ID
        $orderIdParts = explode('_', $orderId);
        $numericOrderId = end($orderIdParts);

        $order = Order::find($numericOrderId);
        if (!$order) {
            return;
        }

        $payment = Payment::where('order_id', $order->id)
            ->where('gateway', 'cashfree')
            ->first();

        if (!$payment) {
            return;
        }

        $status = ($eventType === 'PAYMENT_SUCCESS') ? 'completed' : 'failed';

        $payment->update([
            'status' => $status,
            'payment_data' => array_merge($payment->payment_data ?? [], [
                'webhook_event' => $eventType,
                'webhook_data' => $data
            ])
        ]);

        if ($status === 'completed') {
            $order->update([
                'payment_status' => 'paid',
                'payment_metadata' => array_merge($order->payment_metadata ?? [], [
                    'paid_at' => now()->toIso8601String()
                ])
            ]);
        }
    }
}