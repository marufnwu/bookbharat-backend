<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentSetting;
use App\Services\OrderService;
use App\Services\CartService;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RazorpayController extends Controller
{
    private $keyId;
    private $keySecret;
    private $api;
    private $displayCurrency = 'INR';

    public function __construct()
    {
        try {
            $this->initializeRazorpayConfig();
        } catch (\Exception $e) {
            Log::error('Razorpay Configuration Error: ' . $e->getMessage());
        }
    }

    /**
     * Initialize Razorpay configuration from database
     */
    private function initializeRazorpayConfig(): void
    {
        try {
            $data = PaymentSetting::whereUniqueKeyword('razorpay')->first();

            if (!$data) {
                throw new \RuntimeException('Razorpay payment gateway not configured');
            }

            $paydata = $data->convertJsonData();
            $this->keyId = $paydata['key'] ?? '';
            $this->keySecret = $paydata['secret'] ?? '';

            if (!$this->keyId || !$this->keySecret) {
                throw new \RuntimeException('Razorpay credentials not configured');
            }

            $this->api = new Api($this->keyId, $this->keySecret);

        } catch (\Exception $e) {
            Log::error('Razorpay Configuration Error: ' . $e->getMessage());
            throw new \RuntimeException('Failed to initialize Razorpay payment gateway configuration');
        }
    }

    /**
     * Create Razorpay order
     */
    public function createOrder(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|exists:orders,id',
            ]);

            $order = Order::with(['user', 'items.product'])->findOrFail($request->order_id);

            // Validate currency support
            $currency = $order->currency ?? 'INR';
            if (!in_array($currency, ['INR'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Currency not supported by Razorpay'
                ], 400);
            }

            $amount = $order->is_cod ? $order->prepaid_amount : $order->payable_amount;

            // Create Razorpay order
            $razorpayOrderData = [
                'receipt' => $order->order_number,
                'amount' => $amount * 100, // Amount in paise
                'currency' => 'INR',
                'payment_capture' => 1, // Auto capture
                'notes' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'user_id' => $order->user_id ?? 0
                ]
            ];

            $razorpayOrder = $this->api->order->create($razorpayOrderData);
            $razorpayOrderId = $razorpayOrder['id'];

            // Store Razorpay order ID in order metadata
            $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_amount' => $amount
            ]);
            $order->save();

            // Prepare checkout data
            $billingInfo = $order->billing_address ?? $order->shipping_address;
            $user = $order->user;

            $checkoutData = [
                "key" => $this->keyId,
                "amount" => $amount * 100,
                "currency" => "INR",
                "name" => config('app.name', 'BookBharat'),
                "description" => "Order #" . $order->order_number,
                "order_id" => $razorpayOrderId,
                "prefill" => [
                    "name" => ($billingInfo['first_name'] ?? '') . ' ' . ($billingInfo['last_name'] ?? ''),
                    "email" => $billingInfo['email'] ?? $user->email ?? '',
                    "contact" => $billingInfo['phone'] ?? ''
                ],
                "notes" => [
                    "order_id" => $order->id,
                    "order_number" => $order->order_number
                ],
                "theme" => [
                    "color" => "#3B82F6" // Primary color
                ],
                "callback_url" => route('api.payment.razorpay.callback'),
                "redirect" => true
            ];

            return response()->json([
                'success' => true,
                'razorpay_order_id' => $razorpayOrderId,
                'checkout_data' => $checkoutData,
                'amount' => $amount
            ]);

        } catch (\Exception $e) {
            Log::error('Razorpay Order Creation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle Razorpay payment callback
     */
    public function callback(Request $request)
    {
        Log::info('Razorpay Callback:', $request->all());

        try {
            if (empty($request->razorpay_payment_id)) {
                throw new \Exception('Payment ID not received');
            }

            // Verify payment signature
            $attributes = [
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature
            ];

            try {
                $this->api->utility->verifyPaymentSignature($attributes);
            } catch (SignatureVerificationError $e) {
                Log::error('Razorpay Signature Verification Failed: ' . $e->getMessage());
                throw new \Exception('Payment verification failed');
            }

            // Get order from Razorpay order ID
            $order = Order::where('payment_metadata->razorpay_order_id', $request->razorpay_order_id)->first();

            if (!$order) {
                throw new \Exception('Order not found for Razorpay order ID');
            }

            // Fetch payment details from Razorpay
            $payment = $this->api->payment->fetch($request->razorpay_payment_id);

            // Update order with payment details
            $order->payment_status = 'paid';
            $order->payment_method = 'Razorpay';
            $order->transaction_id = $request->razorpay_payment_id;
            $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
                'razorpay_payment_details' => $payment->toArray(),
                'payment_method_details' => $payment->method ?? null,
                'payment_amount' => $payment->amount / 100
            ]);
            $order->save();

            // Process successful payment
            $orderService = new OrderService();
            $orderService->processSuccessfulPayment($order);

            // Clear cart
            if ($order->cart_id) {
                $cartService = new CartService();
                $cartService->clearCart($order->cart_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment successful',
                'order_id' => $order->id,
                'redirect_url' => config('app.frontend_url') . '/checkout/success?order=' . $order->order_number
            ]);

        } catch (\Exception $e) {
            Log::error('Razorpay Callback Error: ' . $e->getMessage());

            // Update order as failed if found
            if (isset($order)) {
                $order->payment_status = 'failed';
                $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                    'failure_reason' => $e->getMessage(),
                    'failed_at' => now()
                ]);
                $order->save();
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment failed: ' . $e->getMessage(),
                'redirect_url' => config('app.frontend_url') . '/checkout/failed'
            ], 400);
        }
    }

    /**
     * Handle Razorpay webhook
     */
    public function webhook(Request $request)
    {
        Log::info('Razorpay Webhook:', $request->all());

        try {
            // Verify webhook signature
            $webhookSignature = $request->header('X-Razorpay-Signature');
            $webhookSecret = PaymentSetting::whereUniqueKeyword('razorpay')
                ->first()
                ->getConfig('webhook_secret');

            if ($webhookSecret) {
                $expectedSignature = hash_hmac('sha256', $request->getContent(), $webhookSecret);

                if (!hash_equals($expectedSignature, $webhookSignature)) {
                    Log::error('Razorpay Webhook Signature Mismatch');
                    return response()->json(['error' => 'Invalid signature'], 400);
                }
            }

            $event = $request->input('event');
            $payload = $request->input('payload');

            switch ($event) {
                case 'payment.captured':
                    return $this->handlePaymentCaptured($payload);

                case 'payment.failed':
                    return $this->handlePaymentFailed($payload);

                case 'order.paid':
                    return $this->handleOrderPaid($payload);

                default:
                    Log::info('Unhandled Razorpay webhook event: ' . $event);
                    return response()->json(['status' => 'ignored']);
            }

        } catch (\Exception $e) {
            Log::error('Razorpay Webhook Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle payment captured webhook
     */
    private function handlePaymentCaptured($payload)
    {
        $payment = $payload['payment']['entity'];
        $orderId = $payment['notes']['order_id'] ?? null;

        if (!$orderId) {
            Log::warning('Razorpay webhook: Order ID not found in payment notes');
            return response()->json(['status' => 'ignored']);
        }

        $order = Order::find($orderId);
        if (!$order) {
            Log::warning('Razorpay webhook: Order not found', ['order_id' => $orderId]);
            return response()->json(['status' => 'ignored']);
        }

        if ($order->payment_status !== 'paid') {
            $order->payment_status = 'paid';
            $order->payment_method = 'Razorpay';
            $order->transaction_id = $payment['id'];
            $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                'razorpay_payment_id' => $payment['id'],
                'razorpay_webhook_payment' => $payment,
                'captured_amount' => $payment['amount'] / 100
            ]);
            $order->save();

            // Process successful payment
            $orderService = new OrderService();
            $orderService->processSuccessfulPayment($order);
        }

        return response()->json(['status' => 'processed']);
    }

    /**
     * Handle payment failed webhook
     */
    private function handlePaymentFailed($payload)
    {
        $payment = $payload['payment']['entity'];
        $orderId = $payment['notes']['order_id'] ?? null;

        if (!$orderId) {
            return response()->json(['status' => 'ignored']);
        }

        $order = Order::find($orderId);
        if ($order && $order->payment_status !== 'paid') {
            $order->payment_status = 'failed';
            $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                'razorpay_payment_id' => $payment['id'],
                'failure_reason' => $payment['error_description'] ?? 'Payment failed',
                'failure_code' => $payment['error_code'] ?? null,
                'failed_at' => now()
            ]);
            $order->save();
        }

        return response()->json(['status' => 'processed']);
    }

    /**
     * Handle order paid webhook
     */
    private function handleOrderPaid($payload)
    {
        $razorpayOrder = $payload['order']['entity'];
        $order = Order::where('payment_metadata->razorpay_order_id', $razorpayOrder['id'])->first();

        if (!$order) {
            Log::warning('Razorpay webhook: Order not found for Razorpay order', ['razorpay_order_id' => $razorpayOrder['id']]);
            return response()->json(['status' => 'ignored']);
        }

        if ($order->payment_status !== 'paid') {
            $order->payment_status = 'paid';
            $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                'razorpay_order_paid' => $razorpayOrder,
                'paid_amount' => $razorpayOrder['amount_paid'] / 100
            ]);
            $order->save();

            // Process successful payment
            $orderService = new OrderService();
            $orderService->processSuccessfulPayment($order);
        }

        return response()->json(['status' => 'processed']);
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        try {
            $order = Order::findOrFail($request->input('order_id'));
            $razorpayOrderId = $order->payment_metadata['razorpay_order_id'] ?? null;

            if (!$razorpayOrderId) {
                throw new \Exception('Razorpay order ID not found');
            }

            // Fetch order from Razorpay
            $razorpayOrder = $this->api->order->fetch($razorpayOrderId);
            $payments = $this->api->order->fetch($razorpayOrderId)->payments();

            $status = 'pending';
            $paidAmount = 0;

            if ($razorpayOrder->status === 'paid') {
                $status = 'paid';
                $paidAmount = $razorpayOrder->amount_paid / 100;
            } else if (count($payments->items) > 0) {
                foreach ($payments->items as $payment) {
                    if ($payment->status === 'captured') {
                        $status = 'paid';
                        $paidAmount = $payment->amount / 100;
                        break;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'status' => $status,
                'paid_amount' => $paidAmount,
                'razorpay_order' => $razorpayOrder->toArray(),
                'payments' => $payments->toArray()
            ]);

        } catch (\Exception $e) {
            Log::error('Razorpay Verification Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create payment link
     */
    public function createPaymentLink(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        try {
            $order = Order::with(['user'])->findOrFail($request->order_id);
            $amount = $order->is_cod ? $order->prepaid_amount : $order->payable_amount;

            $paymentLink = $this->api->paymentLink->create([
                'amount' => $amount * 100,
                'currency' => 'INR',
                'accept_partial' => false,
                'reference_id' => $order->order_number,
                'description' => 'Payment for Order #' . $order->order_number,
                'customer' => [
                    'name' => $order->user->name ?? 'Customer',
                    'email' => $order->user->email ?? '',
                    'contact' => $order->user->phone ?? ''
                ],
                'notify' => [
                    'sms' => true,
                    'email' => true
                ],
                'callback_url' => route('api.payment.razorpay.callback'),
                'callback_method' => 'get',
                'notes' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number
                ]
            ]);

            // Store payment link details
            $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                'razorpay_payment_link_id' => $paymentLink->id,
                'razorpay_payment_link_url' => $paymentLink->short_url
            ]);
            $order->save();

            return response()->json([
                'success' => true,
                'payment_link' => $paymentLink->short_url,
                'payment_link_id' => $paymentLink->id
            ]);

        } catch (\Exception $e) {
            Log::error('Razorpay Payment Link Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}