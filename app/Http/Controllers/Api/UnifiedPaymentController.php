<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentGatewayFactory;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UnifiedPaymentController extends Controller
{
    /**
     * Get available payment gateways
     */
    public function getAvailableGateways(Request $request)
    {
        try {
            $amount = $request->query('amount', 0);
            $currency = $request->query('currency', 'INR');

            $gateways = PaymentGatewayFactory::getGatewaysForOrder($amount, $currency);

            // Get payment flow settings
            $paymentFlowType = \App\Models\AdminSetting::get('payment_flow_type', 'two_tier');
            $defaultPaymentType = \App\Models\AdminSetting::get('payment_default_type', 'none');

            return response()->json([
                'success' => true,
                'gateways' => $gateways,
                'payment_flow' => [
                    'type' => $paymentFlowType,
                    'default_payment_type' => $defaultPaymentType
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch payment gateways', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment gateways'
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
            'gateway' => 'required|string',
            'return_url' => 'sometimes|url',
            'cancel_url' => 'sometimes|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $order = Order::with(['user', 'items.product'])->findOrFail($request->order_id);

            // Check if user can access this order
            if ($request->user() && $order->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to order'
                ], 403);
            }

            // Check if payment is already completed
            if ($order->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment already completed for this order'
                ], 400);
            }

            // Process payment using factory
            $result = PaymentGatewayFactory::processPayment(
                $request->gateway,
                $order,
                [
                    'return_url' => $request->return_url,
                    'cancel_url' => $request->cancel_url
                ]
            );

            if ($result['success']) {
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Payment initiated successfully',
                    'data' => $result['data'] ?? $result
                ]);
            } else {
                DB::rollback();

                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Payment initiation failed'
                ], 400);
            }

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Payment initiation failed', [
                'order_id' => $request->order_id,
                'gateway' => $request->gateway,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle payment callback from gateway
     */
    public function handleCallback(Request $request, $gateway)
    {
        DB::beginTransaction();

        try {
            $gatewayInstance = PaymentGatewayFactory::create($gateway);
            $result = $gatewayInstance->processCallback($request);

            if ($result['success']) {
                DB::commit();

                // Determine redirect URL
                $redirectUrl = config('app.frontend_url') . '/checkout/success';
                if (isset($result['data']['order_id'])) {
                    $order = Order::find($result['data']['order_id']);
                    if ($order) {
                        $redirectUrl .= '?order=' . $order->order_number;
                    }
                }

                // If it's an API request, return JSON
                if ($request->expectsJson()) {
                    return response()->json($result);
                }

                // Otherwise redirect to frontend
                return redirect($redirectUrl);

            } else {
                DB::rollback();

                $redirectUrl = config('app.frontend_url') . '/checkout/failed';

                if ($request->expectsJson()) {
                    return response()->json($result, 400);
                }

                return redirect($redirectUrl);
            }

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Payment callback processing failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment callback processing failed'
                ], 500);
            }

            return redirect(config('app.frontend_url') . '/checkout/failed');
        }
    }

    /**
     * Handle payment webhook from gateway
     */
    public function handleWebhook(Request $request, $gateway)
    {
        Log::info("Payment webhook received", [
            'gateway' => $gateway,
            'headers' => $request->headers->all(),
            'data' => $request->all()
        ]);

        try {
            $gatewayInstance = PaymentGatewayFactory::create($gateway);

            // Validate webhook signature if applicable
            if (!$gatewayInstance->validateWebhookSignature($request)) {
                Log::warning('Invalid webhook signature', ['gateway' => $gateway]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid signature'
                ], 400);
            }

            // Process webhook
            $result = $gatewayInstance->processWebhook($request);

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            Log::error('Payment webhook processing failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed'
            ], 500);
        }
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|exists:payments,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payment = Payment::with('order')->findOrFail($request->payment_id);

            // Check if user can access this payment
            if ($request->user() && $payment->order->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to payment'
                ], 403);
            }

            // Get the gateway used for this payment
            $gateway = $payment->payment_method;
            $gatewayInstance = PaymentGatewayFactory::create($gateway);

            $result = $gatewayInstance->verifyPayment($payment->id);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'payment_id' => $request->payment_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment status for an order
     */
    public function getPaymentStatus(Request $request, $orderId)
    {
        try {
            $order = Order::with(['payments' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }])->findOrFail($orderId);

            // Check if user can access this order
            if ($request->user() && $order->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to order'
                ], 403);
            }

            $latestPayment = $order->payments->first();

            if (!$latestPayment) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_id' => $order->id,
                        'payment_status' => 'no_payment',
                        'message' => 'No payment initiated for this order'
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'payment_id' => $latestPayment->id,
                    'payment_status' => $latestPayment->status,
                    'payment_method' => $latestPayment->payment_method,
                    'amount' => $latestPayment->amount,
                    'currency' => $latestPayment->currency,
                    'created_at' => $latestPayment->created_at,
                    'updated_at' => $latestPayment->updated_at,
                    'payment_history' => $order->payments->map(function ($payment) {
                        return [
                            'id' => $payment->id,
                            'status' => $payment->status,
                            'amount' => $payment->amount,
                            'method' => $payment->payment_method,
                            'created_at' => $payment->created_at
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment status'
            ], 500);
        }
    }

    /**
     * Initiate payment refund
     */
    public function initiateRefund(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|exists:payments,id',
            'amount' => 'sometimes|numeric|min:0.01',
            'reason' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $payment = Payment::with('order')->findOrFail($request->payment_id);

            // Check if user can access this payment
            if ($request->user() && $payment->order->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to payment'
                ], 403);
            }

            // Check if payment can be refunded
            if (!in_array($payment->status, ['completed', 'partially_refunded'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment cannot be refunded in current status'
                ], 400);
            }

            $refundAmount = $request->amount ?? $payment->amount;

            // Check if refund amount is valid
            if ($refundAmount > $payment->amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refund amount cannot exceed payment amount'
                ], 400);
            }

            // Get the gateway and process refund
            $gateway = $payment->payment_method;
            $gatewayInstance = PaymentGatewayFactory::create($gateway);

            $result = $gatewayInstance->refundPayment(
                $payment->id,
                $refundAmount,
                ['reason' => $request->reason ?? 'Customer requested']
            );

            if ($result['success']) {
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Refund initiated successfully',
                    'data' => $result['data'] ?? $result
                ]);
            } else {
                DB::rollback();

                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Refund initiation failed'
                ], 400);
            }

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Refund initiation failed', [
                'payment_id' => $request->payment_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Refund initiation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear payment gateway cache (admin only)
     */
    public function clearCache(Request $request)
    {
        try {
            // Check if user is admin
            if (!$request->user() || !$request->user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            PaymentGatewayFactory::clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Payment gateway cache cleared successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache'
            ], 500);
        }
    }
}