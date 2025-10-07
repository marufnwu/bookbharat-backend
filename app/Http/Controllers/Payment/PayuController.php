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
use Illuminate\Support\Str;

class PayuController extends Controller
{
    private $merchantKey;
    private $salt;
    private $isProduction;
    private $paymentUrl;
    private $verifyUrl;

    public function __construct()
    {
        try {
            $this->initializePayuConfig();
        } catch (\Exception $e) {
            Log::error('PayU Configuration Error: ' . $e->getMessage());
        }
    }

    /**
     * Initialize PayU configuration from database settings
     */
    private function initializePayuConfig(): void
    {
        try {
            $data = PaymentMethod::where('payment_method',('payu')->first();

            if (!$data) {
                throw new \RuntimeException('PayU payment gateway not configured');
            }

            $paydata = $data->convertJsonData();

            $this->merchantKey = $paydata['merchant_key'] ?? "";
            $this->salt = $paydata['salt_32_bit'] ?? $paydata['salt'] ?? "";
            $this->isProduction = (bool) ($paydata['production'] ?? false);

            // Set endpoints based on environment
            $this->paymentUrl = $this->isProduction
                ? "https://secure.payu.in/_payment"
                : "https://test.payu.in/_payment";

            $this->verifyUrl = $this->isProduction
                ? "https://info.payu.in/merchant/postservice.php?form=2"
                : "https://test.payu.in/merchant/postservice.php?form=2";
        } catch (\Exception $e) {
            Log::error('PayU Configuration Error: ' . $e->getMessage());
            throw new \RuntimeException('Failed to initialize PayU payment gateway configuration');
        }
    }

    /**
     * Create PayU order
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
                    'message' => 'Currency not supported by PayU'
                ], 400);
            }

            // Prepare payment data
            $paymentData = $this->preparePaymentData($order);

            // Store payment data in order metadata
            $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                'payu_txnid' => $paymentData['txnid'],
                'payu_data' => $paymentData
            ]);
            $order->save();

            return response()->json([
                'success' => true,
                'payment_url' => $this->paymentUrl,
                'payment_data' => $paymentData
            ]);

        } catch (\Exception $e) {
            Log::error('PayU Order Creation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Prepare payment data for PayU
     */
    private function preparePaymentData(Order $order): array
    {
        $amount = $order->is_cod ? $order->prepaid_amount : $order->payable_amount;

        $billingInfo = $order->billing_address ?? $order->shipping_address;

        $data = [
            'key' => $this->merchantKey,
            'txnid' => $this->generateTransactionId(),
            'amount' => number_format($amount, 2, '.', ''),
            'productinfo' => 'Order #' . $order->order_number,
            'firstname' => $billingInfo['first_name'] ?? '',
            'email' => $billingInfo['email'] ?? $order->user->email ?? '',
            'phone' => $billingInfo['phone'] ?? '',
            'surl' => route('api.payment.payu.callback'),
            'furl' => route('api.payment.payu.callback'),
            'udf1' => $order->id,
            'udf2' => $order->order_number,
        ];

        // Add hash to payment data
        $data['hash'] = $this->generateRequestHash($data);

        // Optional billing details
        if ($billingInfo) {
            $data['lastname'] = $billingInfo['last_name'] ?? '';
            $data['address1'] = $billingInfo['address_line1'] ?? '';
            $data['address2'] = $billingInfo['address_line2'] ?? '';
            $data['city'] = $billingInfo['city'] ?? '';
            $data['state'] = $billingInfo['state'] ?? '';
            $data['country'] = $billingInfo['country'] ?? 'India';
            $data['zipcode'] = $billingInfo['pincode'] ?? '';
        }

        return $data;
    }

    /**
     * Generate transaction ID
     */
    private function generateTransactionId(): string
    {
        return substr(hash('sha256', Str::random(40) . microtime()), 0, 20);
    }

    /**
     * Generate hash for payment request
     */
    private function generateRequestHash(array $data): string
    {
        $hashSequence = "key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|||||";
        $hashVars = explode('|', $hashSequence);

        $hashString = '';
        foreach ($hashVars as $var) {
            $hashString .= $data[$var] ?? '';
            $hashString .= '|';
        }
        $hashString .= $this->salt;

        return strtolower(hash('sha512', $hashString));
    }

    /**
     * Handle PayU callback
     */
    public function callback(Request $request)
    {
        Log::info('PayU Callback:', $request->all());

        try {
            // Validate hash first
            if (!$this->validateResponseHash($request)) {
                Log::error('Invalid PayU hash received', $request->all());
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment signature'
                ], 400);
            }

            $status = $request->input('status');
            $orderId = $request->input('udf1');
            $order = Order::findOrFail($orderId);

            if ($status === 'success') {
                return $this->handleSuccessfulPayment($order, $request);
            }

            return $this->handleFailedPayment($order, $request);

        } catch (\Exception $e) {
            Log::error('PayU Callback Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment processing error'
            ], 500);
        }
    }

    /**
     * Handle PayU webhook (S2S callback)
     */
    public function webhook(Request $request)
    {
        Log::info('PayU Webhook:', $request->all());

        try {
            if (!$this->validateResponseHash($request)) {
                Log::error('Invalid PayU webhook hash', $request->all());
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid hash'
                ], 400);
            }

            $status = $request->input('status');
            $orderId = $request->input('udf1');
            $order = Order::findOrFail($orderId);
            $txnId = $request->get("txnid");
            $amount = $request->get("net_amount_debit", 0);

            $orderService = new OrderService();

            if ($status === 'success') {
                // Update order status
                $order->payment_status = 'paid';
                $order->payment_method = 'PayU';
                $order->transaction_id = $txnId;
                $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                    'payu_response' => $request->all(),
                    'payment_amount' => $amount
                ]);
                $order->save();

                // Process order
                $orderService->processSuccessfulPayment($order);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully'
                ]);
            } else {
                $reason = $request->get("error_Message", "Payment failed");

                $order->payment_status = 'failed';
                $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
                    'payu_response' => $request->all(),
                    'failure_reason' => $reason
                ]);
                $order->save();

                return response()->json([
                    'success' => false,
                    'message' => 'Payment failed: ' . $reason
                ]);
            }

        } catch (\Exception $e) {
            Log::error('PayU Webhook Error: ' . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate PayU response hash
     */
    private function validateResponseHash(Request $request): bool
    {
        $receivedHash = $request->input('hash');
        if (empty($receivedHash)) {
            Log::error('Empty hash received from PayU');
            return false;
        }

        $calculatedHash = $this->generateResponseHash($request);

        if (!hash_equals($receivedHash, $calculatedHash)) {
            Log::error("PayU Hash Mismatch", [
                'received' => $receivedHash,
                'calculated' => $calculatedHash,
                'status' => $request->input('status'),
                'txnid' => $request->input('txnid')
            ]);
            return false;
        }

        return true;
    }

    /**
     * Generate hash for response validation
     */
    private function generateResponseHash(Request $request): string
    {
        $status = $request->input('status');

        $params = [
            'salt' => $this->salt,
            'status' => $status,
            'udf5' => $request->input('udf5', ''),
            'udf4' => $request->input('udf4', ''),
            'udf3' => $request->input('udf3', ''),
            'udf2' => $request->input('udf2', ''),
            'udf1' => $request->input('udf1', ''),
            'email' => $request->input('email', ''),
            'firstname' => $request->input('firstname', ''),
            'productinfo' => $request->input('productinfo', ''),
            'amount' => $request->input('amount', ''),
            'txnid' => $request->input('txnid', ''),
            'key' => $this->merchantKey
        ];

        $hashString = implode('|', [
            $params['salt'],
            $params['status'],
            '',
            '',
            '',
            '',
            '',
            $params['udf5'],
            $params['udf4'],
            $params['udf3'],
            $params['udf2'],
            $params['udf1'],
            $params['email'],
            $params['firstname'],
            $params['productinfo'],
            $params['amount'],
            $params['txnid'],
            $params['key']
        ]);

        return strtolower(hash('sha512', $hashString));
    }

    /**
     * Handle successful payment
     */
    private function handleSuccessfulPayment(Order $order, Request $request)
    {
        $order->payment_status = 'paid';
        $order->payment_method = 'PayU';
        $order->transaction_id = $request->input('txnid');
        $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
            'payu_response' => $request->all()
        ]);
        $order->save();

        // Update cart status if exists
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
    }

    /**
     * Handle failed payment
     */
    private function handleFailedPayment(Order $order, Request $request)
    {
        Log::warning('PayU Payment Failed', [
            'order_id' => $order->id,
            'status' => $request->input('status'),
            'error_message' => $request->input('error_Message', 'No error message')
        ]);

        $order->payment_status = 'failed';
        $order->payment_metadata = array_merge($order->payment_metadata ?? [], [
            'payu_response' => $request->all(),
            'failure_reason' => $request->input('error_Message', 'Payment failed')
        ]);
        $order->save();

        return response()->json([
            'success' => false,
            'message' => 'Payment failed',
            'redirect_url' => config('app.frontend_url') . '/checkout/failed?order=' . $order->order_number
        ]);
    }

    /**
     * Verify payment status with PayU
     */
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        try {
            $order = Order::findOrFail($request->input('order_id'));
            $transactionId = $order->transaction_id;

            if (empty($transactionId)) {
                throw new \Exception('Transaction ID not found for order');
            }

            $response = $this->callPayuVerificationApi($transactionId);

            return response()->json([
                'success' => $response['status'] === 'success',
                'message' => $response['message'] ?? '',
                'data' => $response['data']
            ]);
        } catch (\Exception $e) {
            Log::error('PayU Verification Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Call PayU verification API
     */
    private function callPayuVerificationApi(string $transactionId): array
    {
        $hashString = $this->merchantKey . '|verify_payment|' . $transactionId . '|' . $this->salt;
        $hash = strtolower(hash('sha512', $hashString));

        $response = Http::asForm()->post($this->verifyUrl, [
            'key' => $this->merchantKey,
            'command' => 'verify_payment',
            'var1' => $transactionId,
            'hash' => $hash
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to connect to PayU verification service');
        }

        $result = $response->json();

        if ($result['status'] == 1 && ($result['transaction_details'][$transactionId]['status'] ?? '') == 'success') {
            return [
                'status' => 'success',
                'data' => $result
            ];
        }

        return [
            'status' => 'failed',
            'message' => $result['message'] ?? 'Payment verification failed',
            'data' => $result
        ];
    }
}