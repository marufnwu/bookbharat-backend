<?php

namespace App\Services\Payment\Gateways;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CodGateway implements PaymentGatewayInterface
{
    protected $config;
    protected $settings;

    public function __construct()
    {
        $this->settings = PaymentSetting::where('unique_keyword', 'cod')->first();
        $this->config = $this->settings ? $this->settings->configuration : [
            'service_charge' => 0,
            'min_order_amount' => 100,
            'max_order_amount' => 50000,
        ];
    }

    public function getName(): string
    {
        return 'Cash on Delivery';
    }

    public function getDisplayName(): string
    {
        return $this->settings->name ?? 'Cash on Delivery';
    }

    public function getDescription(): string
    {
        return $this->settings->description ?? 'Pay when your order is delivered to your doorstep';
    }

    public function isAvailable(): bool
    {
        return $this->settings ? $this->settings->is_active : true;
    }

    public function getSupportedCurrencies(): array
    {
        return $this->settings->supported_currencies ?? ['INR'];
    }

    public function validateConfiguration(): bool
    {
        return true; // COD doesn't need API keys
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        try {
            Log::info('COD payment initiated', [
                'order_id' => $order->id,
                'amount' => $order->total_amount
            ]);

            // Check order amount limits
            if ($order->total_amount < $this->config['min_order_amount']) {
                throw new \Exception("Minimum order amount for COD is ₹{$this->config['min_order_amount']}");
            }

            if ($order->total_amount > $this->config['max_order_amount']) {
                throw new \Exception("Maximum order amount for COD is ₹{$this->config['max_order_amount']}");
            }

            // Create payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'gateway' => 'cod',
                'method' => 'cod',
                'amount' => $order->total_amount,
                'currency' => $order->currency ?? 'INR',
                'status' => 'pending',
                'payment_data' => [
                    'service_charge' => $this->config['service_charge'],
                    'payment_on_delivery' => true,
                    'initiated_at' => now()->toIso8601String()
                ]
            ]);

            // Update order payment status
            $order->update([
                'payment_status' => 'pending',
                'payment_method' => 'cod',
                'payment_metadata' => [
                    'cod' => true,
                    'payment_id' => $payment->id
                ]
            ]);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'currency' => $order->currency ?? 'INR',
                'cod' => true,
                'message' => 'COD order placed successfully',
                'redirect_url' => null // No redirect needed for COD
            ];

        } catch (\Exception $e) {
            Log::error('COD payment initiation failed', [
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

            // COD payments are marked as completed when delivered
            return [
                'success' => true,
                'payment_status' => $payment->status,
                'payment_id' => $payment->id,
                'message' => 'COD payment status: ' . $payment->status
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
        // COD doesn't have callbacks
        return [
            'success' => false,
            'message' => 'COD does not support payment callbacks'
        ];
    }

    public function processWebhook(Request $request): array
    {
        // COD doesn't have webhooks
        return [
            'success' => false,
            'message' => 'COD does not support webhooks'
        ];
    }

    public function refundPayment(string $paymentId, float $amount, array $options = []): array
    {
        try {
            $payment = Payment::find($paymentId);

            if (!$payment) {
                throw new \Exception('Payment record not found');
            }

            if ($payment->gateway !== 'cod') {
                throw new \Exception('Payment is not a COD payment');
            }

            // For COD, refund means cancelling the order if not delivered
            if ($payment->status === 'pending') {
                $payment->update([
                    'status' => 'cancelled',
                    'payment_data' => array_merge($payment->payment_data ?? [], [
                        'cancelled_at' => now()->toIso8601String(),
                        'refund_reason' => $options['reason'] ?? 'Order cancelled'
                    ])
                ]);

                return [
                    'success' => true,
                    'refund_id' => 'cod_cancel_' . $payment->id,
                    'message' => 'COD order cancelled successfully'
                ];
            }

            // If already delivered and paid, handle refund differently
            if ($payment->status === 'completed') {
                // Log refund request for manual processing
                Log::info('COD refund requested for delivered order', [
                    'payment_id' => $paymentId,
                    'amount' => $amount,
                    'reason' => $options['reason'] ?? null
                ]);

                return [
                    'success' => true,
                    'refund_id' => 'cod_refund_' . $payment->id,
                    'message' => 'Refund request logged for manual processing',
                    'manual_processing_required' => true
                ];
            }

            throw new \Exception('Cannot process refund for COD payment with status: ' . $payment->status);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function validateWebhookSignature(Request $request): bool
    {
        return true; // COD doesn't have webhooks
    }

    public function getPaymentStatus(string $paymentId): string
    {
        $payment = Payment::find($paymentId);
        return $payment ? $payment->status : 'unknown';
    }

    /**
     * Mark COD payment as completed when order is delivered
     */
    public function markAsDelivered(string $paymentId): array
    {
        try {
            $payment = Payment::find($paymentId);

            if (!$payment) {
                throw new \Exception('Payment record not found');
            }

            if ($payment->gateway !== 'cod') {
                throw new \Exception('Payment is not a COD payment');
            }

            $payment->update([
                'status' => 'completed',
                'payment_data' => array_merge($payment->payment_data ?? [], [
                    'delivered_at' => now()->toIso8601String(),
                    'payment_collected' => true
                ])
            ]);

            // Update order payment status
            $order = Order::find($payment->order_id);
            if ($order) {
                $order->update([
                    'payment_status' => 'paid',
                    'payment_metadata' => array_merge($order->payment_metadata ?? [], [
                        'paid_at' => now()->toIso8601String(),
                        'payment_method' => 'cod'
                    ])
                ]);
            }

            return [
                'success' => true,
                'message' => 'COD payment marked as completed'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}