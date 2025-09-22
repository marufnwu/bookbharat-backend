<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct()
    {
        // Using unified payment gateway system - no initialization needed
    }

    public function processPayment(Order $order, string $paymentMethod, array $additionalData = [])
    {
        try {
            // Use unified payment gateway system
            $gateway = \App\Services\Payment\PaymentGatewayFactory::create($paymentMethod);

            if (!$gateway || !$gateway->isAvailable()) {
                throw new \Exception("Payment method '{$paymentMethod}' is not available");
            }

            $result = $gateway->initiatePayment($order, $additionalData);

            // Format the response for consistency
            return [
                'status' => $result['success'] ? 'pending' : 'failed',
                'transaction_id' => $result['payment_id'] ?? null,
                'payment_method' => $paymentMethod,
                'message' => $result['message'] ?? 'Payment initiated',
                'payment_data' => $result
            ];
        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    // Old payment processing methods removed - using unified gateway system

    public function updatePaymentStatus($transactionId, $status, $gatewayData = [])
    {
        $payment = Payment::where('payment_data->gateway_order_id', $transactionId)->first();
        
        if (!$payment) {
            throw new \Exception('Payment not found for transaction ID: ' . $transactionId);
        }

        $payment->update([
            'status' => $status,
            'gateway_response' => $gatewayData,
            'payment_data' => array_merge($payment->payment_data ?? [], [
                'gateway_payment_id' => $gatewayData['razorpay_payment_id'] ?? $gatewayData['cf_payment_id'] ?? null,
                'gateway_signature' => $gatewayData['razorpay_signature'] ?? null,
                'updated_at' => now()
            ]),
            'updated_at' => now()
        ]);

        // Update order payment status
        $order = $payment->order;
        $order->update([
            'payment_status' => $status,
            'updated_at' => now()
        ]);
        
        // Log payment status update
        Log::info('Payment status updated', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'old_status' => $payment->getOriginal('status'),
            'new_status' => $status,
            'transaction_id' => $transactionId
        ]);

        return $payment;
    }

    public function refundPayment($paymentId, $amount = null, $reason = 'order_cancellation')
    {
        $payment = Payment::findOrFail($paymentId);

        if ($payment->status !== 'completed') {
            throw new \Exception('Cannot refund incomplete payment');
        }

        $refundAmount = $amount ?? $payment->amount;

        // Use unified gateway system for refunds
        try {
            $gateway = \App\Services\Payment\PaymentGatewayFactory::create($payment->gateway);

            if (!$gateway) {
                throw new \Exception("Payment gateway '{$payment->gateway}' not available for refund");
            }

            $refundResult = $gateway->refundPayment($paymentId, $refundAmount, [
                'reason' => $reason
            ]);

            return $refundResult;

        } catch (\Exception $e) {
            Log::error('Refund failed', [
                'payment_id' => $paymentId,
                'amount' => $refundAmount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Verify payment using unified gateway system
     */
    public function verifyPayment($paymentId)
    {
        try {
            $payment = Payment::findOrFail($paymentId);
            $gateway = \App\Services\Payment\PaymentGatewayFactory::create($payment->gateway);

            if (!$gateway) {
                throw new \Exception("Payment gateway '{$payment->gateway}' not available");
            }

            return $gateway->verifyPayment($paymentId);

        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}