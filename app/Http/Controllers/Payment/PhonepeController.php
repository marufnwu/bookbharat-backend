<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Cart;
use App\Models\PaymentSetting;
use App\Services\OrderService;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class PhonepeController extends Controller
{
    private $merchantId;
    private $saltKey;
    private $saltIndex;
    private $isProduction;
    private $apiUrl;
    private $endPoint = "/pg/v1/pay";

    public function __construct()
    {
        try {
            $this->initializePhonepeConfig();
        } catch (\Exception $e) {
            Log::error('PhonePe Configuration Error: ' . $e->getMessage());
        }
    }

    /**
     * Initialize PhonePe configuration from database
     */
    private function initializePhonepeConfig(): void
    {
        try {
            $data = PaymentSetting::whereUniqueKeyword('phonepe')->first();

            if (!$data) {
                throw new \RuntimeException('PhonePe payment gateway not configured');
            }

            $paydata = $data->convertJsonData();

            $this->merchantId = $paydata['merchant_id'] ?? '';
            $this->saltKey = $paydata['salt_key'] ?? '';
            $this->saltIndex = $paydata['salt_index'] ?? 1;
            $this->isProduction = (bool) ($paydata['production'] ?? false);

            // Set API URL based on environment
            $this->apiUrl = $this->isProduction
                ? ($paydata['production_url'] ?? 'https://api.phonepe.com/apis/hermes')
                : ($paydata['sandbox_url'] ?? 'https://api-preprod.phonepe.com/apis/pg-sandbox');

        } catch (\Exception $e) {
            Log::error('PhonePe Configuration Error: ' . $e->getMessage());
            throw new \RuntimeException('Failed to initialize PhonePe payment gateway configuration');
        }
    }

    /**
     * Create PhonePe order
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
                    'message' => 'Currency not supported by PhonePe'
                ], 400);
            }

            $user = $order->user;
            $merchantTransactionId = 'TXN' . $order->id . '_' . time();

            // Prepare payload
            $payload = [
                "merchantId" => $this->merchantId,
                "merchantTransactionId" => $merchantTransactionId,
                "merchantUserId" => (string)($user ? $user->id : 'guest_' . $order->id),
                "amount" => (int)($order->is_cod ? $order->prepaid_amount * 100 : $order->payable_amount * 100), // Amount in Paise
                "redirectUrl" => route('api.payment.phonepe.callback'),
                "redirectMode" => "POST",
                "callbackUrl" => route('api.payment.phonepe.webhook'),
                "paymentInstrument" => [
                    "type" => "PAY_PAGE"
                ]
            ];

            // Add mobile number if available
            $billingInfo = $order->billing_address ?? $order->shipping_address;
            if ($billingInfo && isset($billingInfo['phone'])) {
                $payload["mobileNumber"] = $billingInfo['phone'];
            }

            $base64Payload = base64_encode(json_encode($payload));

            // Generate X-VERIFY header
            $hashString = $base64Payload . $this->endPoint . $this->saltKey;
            $xVerify = hash('sha256', $hashString) . "###" . $this->saltIndex;

            // Make API request
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-VERIFY' => $xVerify,
            ])->post($this->apiUrl . $this->endPoint, [
                'request' => $base64Payload
            ]);

            if ($response->successful()) {
                $responseData = json_decode($response->body());

                if ($responseData->success) {
                    // Store transaction ID in order
                    $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                        'phonepe_transaction_id' => $merchantTransactionId,
                        'phonepe_merchant_transaction_id' => $merchantTransactionId
                    ]);
                    $order->save();

                    return response()->json([
                        'success' => true,
                        'payment_url' => $responseData->data->instrumentResponse->redirectInfo->url,
                        'transaction_id' => $merchantTransactionId
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => $responseData->message ?? 'Payment initiation failed'
                    ], 400);
                }
            } else {
                Log::error('PhonePe API Error: ' . $response->body());
                return response()->json([
                    'success' => false,
                    'message' => 'PhonePe gateway error'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('PhonePe Order Creation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle PhonePe callback (redirect after payment)
     */
    public function callback(Request $request)
    {
        Log::info('PhonePe Callback:', $request->all());

        try {
            $transactionId = $request->input('transactionId');
            $code = $request->input('code');

            // Extract order ID from transaction ID
            preg_match('/TXN(\d+)_/', $transactionId, $matches);
            $orderId = $matches[1] ?? null;

            if (!$orderId) {
                throw new \Exception('Invalid transaction ID format');
            }

            $order = Order::findOrFail($orderId);

            if ($code === 'PAYMENT_SUCCESS') {
                $providerReferenceId = $request->input('providerReferenceId');

                $order->payment_status = 'paid';
                $order->payment_method = 'PhonePe';
                $order->transaction_id = $providerReferenceId;
                $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                    'phonepe_response' => $request->all(),
                    'phonepe_provider_reference_id' => $providerReferenceId
                ]);
                $order->save();

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

            } else if ($code === 'PAYMENT_FAILURE' || $code === 'PAYMENT_DECLINED') {
                $order->payment_status = 'failed';
                $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                    'phonepe_response' => $request->all(),
                    'failure_code' => $code
                ]);
                $order->save();

                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed',
                    'redirect_url' => config('app.frontend_url') . '/checkout/failed?order=' . $order->order_number
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment processing',
                    'redirect_url' => config('app.frontend_url') . '/checkout/pending?order=' . $order->order_number
                ]);
            }

        } catch (\Exception $e) {
            Log::error('PhonePe Callback Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment processing error'
            ], 500);
        }
    }

    /**
     * Handle PhonePe webhook (S2S callback)
     */
    public function webhook(Request $request)
    {
        Log::info('PhonePe Webhook Raw:', $request->all());

        try {
            $xVerify = $request->header("X-Verify", null);
            $response = $request->input("response");

            if (!$response || !$xVerify) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid webhook data'
                ], 400);
            }

            // Verify the webhook signature
            $expectedHash = hash('sha256', $response . $this->saltKey) . "###" . $this->saltIndex;
            if ($xVerify !== $expectedHash) {
                Log::error('PhonePe Webhook Signature Mismatch', [
                    'expected' => $expectedHash,
                    'received' => $xVerify
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid signature'
                ], 400);
            }

            // Decode response
            $json = base64_decode($response);
            Log::info('PhonePe Webhook Decoded:', ['data' => $json]);

            $resData = json_decode($json);

            // Extract order ID from merchant transaction ID
            $merchantTransactionId = $resData->data->merchantTransactionId;
            preg_match('/TXN(\d+)_/', $merchantTransactionId, $matches);
            $orderId = $matches[1] ?? null;

            if (!$orderId) {
                throw new \Exception('Invalid merchant transaction ID format');
            }

            $order = Order::findOrFail($orderId);
            $orderService = new OrderService();

            if ($resData->code === 'PAYMENT_SUCCESS') {
                $transactionId = $resData->data->transactionId;
                $paidAmount = $resData->data->amount / 100; // Convert from paise to rupees

                $order->payment_status = 'paid';
                $order->payment_method = 'PhonePe';
                $order->transaction_id = $transactionId;
                $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                    'phonepe_webhook_response' => $resData,
                    'paid_amount' => $paidAmount
                ]);
                $order->save();

                // Process successful payment
                $orderService->processSuccessfulPayment($order);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully'
                ]);

            } else if ($resData->code === 'PAYMENT_FAILURE' || $resData->code === 'PAYMENT_DECLINED') {
                $order->payment_status = 'failed';
                $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                    'phonepe_webhook_response' => $resData,
                    'failure_reason' => $resData->message ?? 'Payment failed'
                ]);
                $order->save();

                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed'
                ]);
            } else {
                Log::info('PhonePe Payment Pending', ['code' => $resData->code]);
                return response()->json([
                    'success' => true,
                    'message' => 'Payment status updated'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('PhonePe Webhook Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check payment status
     */
    public function checkStatus(Request $request)
    {
        try {
            $request->validate([
                'transaction_id' => 'required|string'
            ]);

            $merchantTransactionId = $request->transaction_id;

            // Prepare status check URL
            $statusUrl = $this->apiUrl . "/pg/v1/status/" . $this->merchantId . "/" . $merchantTransactionId;

            // Generate X-VERIFY header for status check
            $hashString = "/pg/v1/status/" . $this->merchantId . "/" . $merchantTransactionId . $this->saltKey;
            $xVerify = hash('sha256', $hashString) . "###" . $this->saltIndex;

            // Make status check request
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-VERIFY' => $xVerify,
                'X-MERCHANT-ID' => $this->merchantId
            ])->get($statusUrl);

            if ($response->successful()) {
                $responseData = json_decode($response->body());

                return response()->json([
                    'success' => $responseData->success ?? false,
                    'code' => $responseData->code ?? null,
                    'message' => $responseData->message ?? '',
                    'data' => $responseData->data ?? null
                ]);
            } else {
                Log::error('PhonePe Status Check Error: ' . $response->body());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to check payment status'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('PhonePe Status Check Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}