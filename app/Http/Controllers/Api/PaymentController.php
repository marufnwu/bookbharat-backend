<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\CartService;
use App\Services\OrderService;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    protected $cartService;
    protected $orderService;

    public function __construct(CartService $cartService, OrderService $orderService)
    {
        $this->cartService = $cartService;
        $this->orderService = $orderService;
    }

    /**
     * Get available payment methods - CLEAN SINGLE TABLE
     *
     * SINGLE SOURCE OF TRUTH: PaymentMethod.is_enabled
     * - No hierarchies
     * - No foreign keys
     * - Simple query: WHERE is_enabled = true
     */
    public function getAvailablePaymentMethods(Request $request)
    {
        try {
            $amount = $request->query('amount', 0);
            $orderItems = $request->query('items', []);

            // CLEAN & SIMPLE: Get enabled methods
            $paymentMethods = \App\Models\PaymentMethod::getEnabledMethods($amount, $orderItems);

            // Transform to frontend-friendly format
            $gateways = $paymentMethods->map(function ($method) {
                return [
                    'id' => $method->id,
                    'gateway' => $method->payment_method, // Frontend expects 'gateway' field
                    'payment_method' => $method->payment_method,
                    'display_name' => $method->display_name,
                    'description' => $method->description,
                    'priority' => $method->priority,
                    'configuration' => $method->configuration,
                    'restrictions' => $method->restrictions,
                    'advance_payment' => $method->configuration['advance_payment'] ?? null,
                    'service_charges' => $method->configuration['service_charges'] ?? null,
                    'is_active' => true, // Already filtered by is_enabled
                    'is_cod' => $method->isCod(),
                    'is_online' => $method->isOnline(),
                    'gateway_type' => $method->gateway_type,
                    'is_production' => $method->is_production,
                ];
            })->values();

            // Get payment flow settings for UI behavior only
            $paymentFlowType = \App\Models\AdminSetting::get('payment_flow_type', 'two_tier');
            $defaultPaymentType = \App\Models\AdminSetting::get('payment_default_type', 'none');

            // Check if COD and online payment methods are enabled
            $codEnabled = \App\Models\PaymentMethod::cod()->where('is_enabled', true)->exists();
            $onlinePaymentEnabled = \App\Models\PaymentMethod::online()->where('is_enabled', true)->exists();

            return response()->json([
                'success' => true,
                'gateways' => $gateways, // Frontend expects 'gateways' key
                'payment_methods' => $gateways, // Keep for backward compatibility
                'payment_flow' => [
                    'type' => $paymentFlowType,
                    'default_payment_type' => $defaultPaymentType,
                    'cod_enabled' => $codEnabled,
                    'online_payment_enabled' => $onlinePaymentEnabled,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch payment methods', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment methods: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initiate payment for an order
     */
    public function initiatePayment(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|exists:orders,id',
                'gateway' => 'required|string',
                'return_url' => 'nullable|url',
                'cancel_url' => 'nullable|url'
            ]);

            $order = Order::findOrFail($request->order_id);

            // Check if order is eligible for payment
            if ($order->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order is already paid'
                ], 400);
            }

            // Create gateway instance
            $gatewayInstance = PaymentGatewayFactory::create($request->gateway);
            if (!$gatewayInstance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment gateway not found'
                ], 404);
            }

            // Initiate payment
            $result = $gatewayInstance->initiatePayment($order, [
                'return_url' => $request->return_url,
                'cancel_url' => $request->cancel_url
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['data'],
                    'message' => $result['message'] ?? 'Payment initiated successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to initiate payment'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Payment initiation failed', [
                'error' => $e->getMessage(),
                'order_id' => $request->order_id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle payment gateway callback (frontend redirect)
     * This is called when user is redirected back from payment gateway
     */
    public function callback(Request $request, $gateway)
    {
        try {
            Log::info("Payment callback received for {$gateway}", $request->all());

            $gatewayInstance = PaymentGatewayFactory::create($gateway);
            if (!$gatewayInstance) {
                throw new \Exception("Payment gateway {$gateway} not found");
            }

            // Process the callback - ONLY validates signature, doesn't update DB
            $result = $gatewayInstance->processCallback($request);

            // Extract data from formatted response
            $data = $result['data'] ?? [];
            $gatewayStatus = $data['gateway_status'] ?? 'unknown'; // PayU/Razorpay status
            $orderId = $data['order_id'] ?? $request->input('order_id');
            $orderNumber = $data['order_number'] ?? null;

            if (!$result['success']) {
                // Signature validation failed
                $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
                return redirect("{$frontendUrl}/payment/failed?order_id={$orderNumber}&reason=" . urlencode($result['message'] ?? 'Invalid payment response'));
            }

            // Find the order and check ACTUAL database status
            // Don't trust callback data - only trust webhook-updated DB status
            $order = Order::find($orderId);
            if (!$order) {
                $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
                return redirect("{$frontendUrl}/payment/failed?error=Order+not+found");
            }

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            // Check actual payment status from database (updated by webhook)
            if ($order->payment_status === 'paid') {
                // Payment confirmed by webhook - clear cart and redirect to success
                $sessionId = $request->header('X-Session-ID');
                $this->cartService->clearCart($order->user_id, $sessionId);

                return redirect("{$frontendUrl}/payment/success?order_id={$order->order_number}");

            } elseif ($gatewayStatus === 'success' && $order->payment_status === 'pending') {
                // Gateway says success but webhook hasn't processed yet
                // Redirect to processing/pending page that will poll for confirmation
                return redirect("{$frontendUrl}/payment/pending?order_id={$order->order_number}&status=processing");

            } elseif ($gatewayStatus === 'failure' || $gatewayStatus === 'failed') {
                // Gateway explicitly says failed
                return redirect("{$frontendUrl}/payment/failed?order_id={$order->order_number}&reason=" . urlencode($data['message'] ?? 'Payment failed'));

            } else {
                // Unknown status - redirect to pending
                return redirect("{$frontendUrl}/payment/pending?order_id={$order->order_number}&status=verifying");
            }

        } catch (\Exception $e) {
            Log::error("Payment callback error for {$gateway}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect("{$frontendUrl}/payment/failed?error=" . urlencode($e->getMessage()));
        }
    }

    /**
     * Handle payment gateway webhook (backend notification)
     * This is called by payment gateway to notify payment status
     */
    public function webhook(Request $request, $gateway)
    {
        try {
            Log::info("Payment webhook received for {$gateway}", $request->all());

            // For PayU, always return 200 OK to prevent retries
            $alwaysReturn200 = in_array($gateway, ['payu']);

            $gatewayInstance = PaymentGatewayFactory::create($gateway);
            if (!$gatewayInstance) {
                Log::error("Payment gateway {$gateway} not found");
                return $alwaysReturn200 ?
                    response('OK', 200) :
                    response()->json(['error' => 'Gateway not found'], 404);
            }

            // Validate webhook signature
            if (!$gatewayInstance->validateWebhookSignature($request)) {
                Log::warning("Invalid webhook signature for {$gateway}");
                // For PayU, return 200 OK even for invalid signature to prevent retries
                return $alwaysReturn200 ?
                    response('OK', 200) :
                    response()->json(['error' => 'Invalid signature'], 401);
            }

            // Process the webhook
            $result = $gatewayInstance->processWebhook($request);

            // Process order workflow for all gateways
            if ($result['success']) {
                // Find the order and update its status
                if (isset($result['order_id'])) {
                    $order = Order::find($result['order_id']);
                    if ($order) {
                        DB::beginTransaction();
                        try {
                            // Update order payment status based on webhook
                            if (isset($result['payment_status'])) {
                                if ($result['payment_status'] === 'completed') {
                                    $order->update([
                                        'payment_status' => 'paid',
                                        'status' => 'processing'
                                    ]);

                                    // Clear cart if payment successful
                                    if ($order->user_id) {
                                        // Get session from order metadata if stored
                                        $sessionId = $order->metadata['session_id'] ?? null;
                                        $this->cartService->clearCart($order->user_id, $sessionId);
                                    }

                                    // Start order workflow
                                    $this->orderService->processOrderCreated($order);

                                } elseif ($result['payment_status'] === 'failed') {
                                    $order->update([
                                        'payment_status' => 'failed',
                                        'status' => 'cancelled'
                                    ]);
                                }
                            }

                            DB::commit();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Log::error("Error updating order from webhook", [
                                'order_id' => $order->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }

                return response()->json(['success' => true], 200);
            }

            // Even on failure, return appropriate status
            return $alwaysReturn200 ?
                response('OK', 200) :
                response()->json(['success' => false, 'message' => $result['message'] ?? 'Webhook processing failed'], 400);

        } catch (\Exception $e) {
            Log::error("Payment webhook error for {$gateway}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // For PayU, always return 200 to prevent retries
            if (isset($gateway) && $gateway === 'payu') {
                return response('OK', 200);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get payment status for an order
     */
    public function getPaymentStatus($orderId)
    {
        try {
            $order = Order::where('id', $orderId)
                ->orWhere('order_number', $orderId)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Get latest payment for this order
            $payment = Payment::where('order_id', $order->id)
                ->latest()
                ->first();

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'payment_details' => $payment ? [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'gateway' => $payment->gateway,
                    'amount' => $payment->amount,
                    'created_at' => $payment->created_at
                ] : null
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get payment status', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment status'
            ], 500);
        }
    }
}