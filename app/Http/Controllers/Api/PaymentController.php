<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Get available payment methods for checkout
     */
    public function getAvailablePaymentMethods(Request $request)
    {
        $orderAmount = $request->query('amount', 0);
        
        try {
            $methods = PaymentConfiguration::getEnabledMethods($orderAmount);
            
            $formattedMethods = $methods->map(function($method) use ($orderAmount) {
                $data = [
                    'payment_method' => $method->payment_method,
                    'display_name' => $method->display_name,
                    'description' => $method->description,
                    'priority' => $method->priority,
                ];

                // Add specific configuration for COD methods
                if (str_contains($method->payment_method, 'cod')) {
                    $advanceAmount = $method->getAdvancePaymentAmount($orderAmount);
                    $data['advance_payment'] = [
                        'required' => $method->requiresAdvancePayment(),
                        'amount' => $advanceAmount,
                        'cod_amount' => $orderAmount - $advanceAmount,
                        'service_charges' => $method->configuration['service_charges'] ?? null
                    ];
                }

                // Add bank details for bank transfer
                if ($method->payment_method === 'bank_transfer') {
                    $data['bank_details'] = $method->configuration['bank_details'] ?? [];
                }

                return $data;
            });

            return response()->json([
                'success' => true,
                'payment_methods' => $formattedMethods->values()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch payment methods', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment methods'
            ], 500);
        }
    }

    /**
     * Initiate payment for an order
     */
    public function initiatePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|in:cod,razorpay,cashfree',
            'return_url' => 'sometimes|url',
            'cancel_url' => 'sometimes|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $order = Order::findOrFail($request->order_id);
            
            // Check if user can access this order
            if ($order->user_id !== auth()->id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to order'
                ], 403);
            }

            // Check if payment is already completed
            if ($order->payment_status === 'completed') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment already completed for this order'
                ], 400);
            }

            $additionalData = [
                'return_url' => $request->return_url,
                'cancel_url' => $request->cancel_url
            ];

            $paymentData = $this->paymentService->processPayment(
                $order, 
                $request->payment_method, 
                $additionalData
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Payment initiated successfully',
                'data' => $paymentData
            ], 200);

        } catch (\Exception $e) {
            Log::error('Payment initiation failed', [
                'order_id' => $request->order_id,
                'payment_method' => $request->payment_method,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Payment initiation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Razorpay payment callback
     */
    public function razorpayCallback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid payment response',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify payment signature
            $isValid = $this->paymentService->verifyRazorpayPayment(
                $request->razorpay_order_id,
                $request->razorpay_payment_id,
                $request->razorpay_signature
            );

            if (!$isValid) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment verification failed'
                ], 400);
            }

            // Update payment status
            $payment = $this->paymentService->updatePaymentStatus(
                $request->razorpay_order_id,
                'completed',
                $request->all()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Payment verified and updated successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'status' => $payment->status
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Razorpay callback processing failed', [
                'razorpay_order_id' => $request->razorpay_order_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Payment callback processing failed'
            ], 500);
        }
    }

    /**
     * Handle Cashfree payment callback
     */
    public function cashfreeCallback(Request $request, $orderId)
    {
        try {
            // Verify payment with Cashfree
            $paymentData = $this->paymentService->verifyCashfreePayment($orderId);

            if (!$paymentData) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment verification failed'
                ], 400);
            }

            // Determine payment status based on Cashfree response
            $status = 'failed';
            if (!empty($paymentData) && isset($paymentData[0]['payment_status'])) {
                $cfStatus = $paymentData[0]['payment_status'];
                $status = ($cfStatus === 'SUCCESS') ? 'completed' : 'failed';
            }

            // Update payment status
            $payment = $this->paymentService->updatePaymentStatus(
                $orderId,
                $status,
                ['cashfree_response' => $paymentData]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Payment verified and updated successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'status' => $payment->status
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Cashfree callback processing failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Payment callback processing failed'
            ], 500);
        }
    }

    /**
     * Handle payment webhook (for server-to-server notifications)
     */
    public function webhook(Request $request, $gateway)
    {
        try {
            switch ($gateway) {
                case 'razorpay':
                    return $this->handleRazorpayWebhook($request);
                case 'cashfree':
                    return $this->handleCashfreeWebhook($request);
                default:
                    return response()->json(['status' => 'error', 'message' => 'Invalid gateway'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);
            return response()->json(['status' => 'error'], 500);
        }
    }

    protected function handleRazorpayWebhook(Request $request)
    {
        $webhookSignature = $request->header('X-Razorpay-Signature');
        $webhookBody = $request->getContent();
        
        // Verify webhook signature (implement according to Razorpay docs)
        // For now, we'll just process the webhook
        
        $data = $request->all();
        
        if ($data['event'] === 'payment.captured') {
            $paymentId = $data['payload']['payment']['entity']['order_id'];
            $this->paymentService->updatePaymentStatus(
                $paymentId,
                'completed',
                $data['payload']['payment']['entity']
            );
        }
        
        return response()->json(['status' => 'ok']);
    }

    protected function handleCashfreeWebhook(Request $request)
    {
        $data = $request->all();
        
        if ($data['type'] === 'PAYMENT_SUCCESS_WEBHOOK') {
            $orderId = $data['data']['order']['order_id'];
            $this->paymentService->updatePaymentStatus(
                $orderId,
                'completed',
                $data['data']
            );
        }
        
        return response()->json(['status' => 'ok']);
    }

    /**
     * Get payment status for an order
     */
    public function getPaymentStatus(Request $request, $orderId)
    {
        try {
            $order = Order::findOrFail($orderId);
            
            // Check if user can access this order
            if ($order->user_id !== auth()->id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to order'
                ], 403);
            }

            $payment = Payment::where('order_id', $orderId)->latest()->first();

            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found for this order'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'payment_method' => $payment->payment_method,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'created_at' => $payment->created_at,
                    'updated_at' => $payment->updated_at
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve payment status'
            ], 500);
        }
    }

    /**
     * Initiate payment refund
     */
    public function refundPayment(Request $request, $paymentId)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'sometimes|numeric|min:0.01',
            'reason' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payment = Payment::findOrFail($paymentId);
            
            // Check if user can access this payment
            if ($payment->order->user_id !== auth()->id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to payment'
                ], 403);
            }

            $refund = $this->paymentService->refundPayment(
                $paymentId,
                $request->amount,
                $request->reason ?? 'customer_request'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Refund initiated successfully',
                'data' => [
                    'refund_id' => $refund->id,
                    'amount' => $refund->amount,
                    'status' => $refund->status
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Refund initiation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}