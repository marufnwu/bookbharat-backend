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
     * Get available payment gateways
     */
    public function getAvailablePaymentMethods(Request $request)
    {
        try {
            $amount = $request->query('amount', 0);
            $currency = $request->query('currency', 'INR');

            $gateways = PaymentGatewayFactory::getGatewaysForOrder($amount, $currency);

            return response()->json([
                'success' => true,
                'gateways' => $gateways
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

            // Process the callback
            $result = $gatewayInstance->processCallback($request);

            if ($result['success']) {
                // Find the order
                $orderId = $result['order_id'] ?? $request->input('order_id');
                $order = Order::find($orderId);

                if ($order && $result['payment_status'] === 'completed') {
                    // Payment successful - clear cart
                    $sessionId = $request->header('X-Session-ID');
                    $this->cartService->clearCart($order->user_id, $sessionId);

                    // Start order workflow
                    $this->orderService->processOrderCreated($order);

                    // Redirect to success page
                    $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
                    return redirect("{$frontendUrl}/payment/success?order_id={$order->order_number}");
                }
            }

            // Payment failed or pending - redirect to appropriate page
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $status = $result['payment_status'] ?? 'failed';
            $orderId = $result['order_id'] ?? '';

            if ($status === 'pending') {
                return redirect("{$frontendUrl}/payment/pending?order_id={$orderId}");
            } else {
                return redirect("{$frontendUrl}/payment/failed?order_id={$orderId}&reason=" . urlencode($result['message'] ?? 'Payment failed'));
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

            // For PayU webhooks, handle order updates directly in the gateway
            // since it already updates order status
            if ($gateway === 'payu') {
                // PayU gateway handles all order updates internally
                // Just return 200 OK to acknowledge receipt
                return response('OK', 200);
            }

            // For other gateways, process normally
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