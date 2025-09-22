<?php

namespace App\Services\Payment\Gateways;

use App\Services\Payment\BasePaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayuGateway extends BasePaymentGateway
{
    protected $gatewayName = 'PayU';
    protected $paymentUrl;
    protected $verifyUrl;

    public function __construct()
    {
        parent::__construct();
        $this->initializeUrls();
    }

    protected function getGatewayKeyword(): string
    {
        return 'payu';
    }

    protected function initializeUrls()
    {
        $this->paymentUrl = $this->isProduction
            ? "https://secure.payu.in/_payment"
            : "https://test.payu.in/_payment";

        $this->verifyUrl = $this->isProduction
            ? "https://info.payu.in/merchant/postservice.php?form=2"
            : "https://test.payu.in/merchant/postservice.php?form=2";
    }

    protected function hasRequiredConfiguration(): bool
    {
        return !empty($this->getConfig('merchant_key')) &&
               !empty($this->getConfig('salt'));
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        try {
            $this->validateOrderAmount($order);

            if (!$this->isCurrencySupported($order->currency ?? 'INR')) {
                throw new \Exception('Currency not supported');
            }

            $txnId = $this->generatePayuTransactionId($order);
            $paymentData = $this->preparePaymentData($order, $txnId);

            // Create payment record
            $payment = $this->createPaymentRecord($order, 'pending', [
                'payu_txnid' => $txnId,
                'payment_data' => $paymentData
            ]);

            // Update order with payment info
            $order->update([
                'payment_metadata' => array_merge($order->payment_metadata ?? [], [
                    'payu_txnid' => $txnId,
                    'payment_id' => $payment->id
                ])
            ]);

            $this->logActivity('Payment initiated', [
                'order_id' => $order->id,
                'txnid' => $txnId,
                'amount' => $paymentData['amount']
            ]);

            // Log payment data for debugging
            \Log::info('PayU Payment Data:', [
                'payment_url' => $this->paymentUrl,
                'data' => $paymentData
            ]);

            return $this->formatResponse(true, array_merge($paymentData, [
                'payment_id' => $payment->id,
                'payment_url' => $this->paymentUrl,
                'method' => 'POST'
            ]), 'Payment initiated successfully');

        } catch (\Exception $e) {
            return $this->handleException($e, 'initiatePayment');
        }
    }

    protected function preparePaymentData(Order $order, string $txnId): array
    {
        // PayU expects amount in rupees with 2 decimal places as string
        $amount = number_format((float)$order->total_amount, 2, '.', '');

        // Get customer information
        $shippingInfo = $order->shipping_address;
        $billingInfo = $order->billing_address ?? $order->shipping_address;

        // Extract names and contact
        $firstName = $order->customer_name ? explode(' ', $order->customer_name)[0] :
                    ($shippingInfo['first_name'] ?? $billingInfo['first_name'] ?? 'Customer');
        $lastName = $shippingInfo['last_name'] ?? $billingInfo['last_name'] ?? '';
        $email = $order->customer_email ?? $order->user->email ?? '';
        $phone = $order->customer_phone ?? $shippingInfo['phone'] ?? $billingInfo['phone'] ?? '';

        // Prepare required fields as per PayU documentation
        $data = [
            'key' => $this->getConfig('merchant_key'),
            'txnid' => $txnId,
            'amount' => $amount, // Amount in rupees with 2 decimal places
            'productinfo' => 'Order #' . $order->order_number,
            'firstname' => $firstName,
            'email' => $email,
            'phone' => $phone,
            'surl' => route('payment.callback', ['gateway' => 'payu']),
            'furl' => route('payment.callback', ['gateway' => 'payu']),
            // User defined fields (important for tracking)
            'udf1' => (string)$order->id,
            'udf2' => $order->order_number,
            'udf3' => (string)($order->user_id ?? ''),
            'udf4' => '',
            'udf5' => '',
        ];

        // Add optional fields
        if ($lastName) {
            $data['lastname'] = $lastName;
        }

        if ($billingInfo) {
            $data['address1'] = $billingInfo['address_line_1'] ?? '';
            $data['address2'] = $billingInfo['address_line_2'] ?? '';
            $data['city'] = $billingInfo['city'] ?? '';
            $data['state'] = $billingInfo['state'] ?? '';
            $data['country'] = $billingInfo['country'] ?? 'India';
            $data['zipcode'] = $billingInfo['postal_code'] ?? '';
        }

        // Generate hash as per PayU specification
        $data['hash'] = $this->generateRequestHash($data);

        return $data;
    }

    protected function generatePayuTransactionId(Order $order): string
    {
        // Generate unique transaction ID
        // Format: ORDER_ID_TIMESTAMP or custom format
        return 'ORD' . $order->id . 'T' . time();
    }

    protected function generateRequestHash(array $data): string
    {
        $salt = $this->getConfig('salt');

        // PayU hash format: sha512(key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||SALT)
        $hashString = $data['key'] . '|' .
                      $data['txnid'] . '|' .
                      $data['amount'] . '|' .
                      $data['productinfo'] . '|' .
                      $data['firstname'] . '|' .
                      $data['email'] . '|' .
                      $data['udf1'] . '|' .
                      $data['udf2'] . '|' .
                      $data['udf3'] . '|' .
                      $data['udf4'] . '|' .
                      $data['udf5'] . '||||||' .
                      $salt;

        $hash = hash('sha512', $hashString);

        $this->logActivity('Hash generated', [
            'txnid' => $data['txnid'],
            'format' => 'key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||SALT'
        ]);

        return $hash;
    }

    public function verifyPayment(string $paymentId): array
    {
        try {
            $payment = Payment::find($paymentId);
            if (!$payment) {
                throw new \Exception('Payment record not found');
            }

            $txnId = $payment->payment_data['payu_txnid'] ?? null;
            if (!$txnId) {
                throw new \Exception('Transaction ID not found');
            }

            // Call PayU verification API
            $response = $this->callPayuVerificationApi($txnId);

            // Update payment status based on verification
            if ($response['status'] === 'success') {
                if ($payment->status !== 'completed') {
                    $this->updatePaymentRecord($payment, 'completed', [
                        'verification_response' => $response['data'],
                        'verified_at' => now()
                    ]);

                    $payment->order->update(['payment_status' => 'paid']);
                }

                return $this->formatResponse(true, [
                    'payment_status' => 'completed',
                    'verification_data' => $response['data']
                ], 'Payment verified successfully');
            } else {
                return $this->formatResponse(false, [
                    'payment_status' => $payment->status,
                    'error' => $response['message'] ?? 'Verification failed'
                ], 'Payment verification failed');
            }

        } catch (\Exception $e) {
            return $this->handleException($e, 'verifyPayment');
        }
    }

    protected function callPayuVerificationApi(string $transactionId): array
    {
        $merchantKey = $this->getConfig('merchant_key');
        $salt = $this->getConfig('salt');

        // Generate verification hash as per PayU documentation
        $hashString = $merchantKey . '|verify_payment|' . $transactionId . '|' . $salt;
        $hash = hash('sha512', $hashString);

        $response = Http::asForm()->post($this->verifyUrl, [
            'key' => $merchantKey,
            'command' => 'verify_payment',
            'var1' => $transactionId,
            'hash' => $hash
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to connect to PayU verification service');
        }

        $result = $response->json();

        // Check verification response
        if (isset($result['status']) && $result['status'] == 1) {
            $txnDetails = $result['transaction_details'][$transactionId] ?? null;

            if ($txnDetails && $txnDetails['status'] === 'success') {
                return [
                    'status' => 'success',
                    'data' => $result,
                    'message' => $result['msg'] ?? 'Payment verified'
                ];
            }
        }

        return [
            'status' => 'failed',
            'message' => $result['msg'] ?? 'Payment verification failed',
            'data' => $result
        ];
    }

    public function processCallback(Request $request): array
    {
        try {
            $this->logActivity('Callback received', $request->all());

            // Validate response hash
            if (!$this->validateResponseHash($request)) {
                throw new \Exception('Invalid payment response signature');
            }

            $status = $request->input('status');
            $orderId = $request->input('udf1');
            $txnId = $request->input('txnid');
            $mihpayid = $request->input('mihpayid');

            // Find order
            $order = Order::find($orderId);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            // Find or create payment record
            $payment = Payment::where('order_id', $orderId)
                ->where('payment_data->payu_txnid', $txnId)
                ->first();

            if (!$payment) {
                $payment = Payment::where('order_id', $orderId)
                    ->where('gateway', 'payu')
                    ->latest()
                    ->first();
            }

            if (!$payment) {
                throw new \Exception('Payment record not found');
            }

            // Update payment based on status
            if ($status === 'success') {
                $this->updatePaymentRecord($payment, 'completed', [
                    'payu_response' => $request->all(),
                    'mihpayid' => $mihpayid,
                    'paid_amount' => $request->input('amount'),
                    'mode' => $request->input('mode'),
                    'bank_ref_num' => $request->input('bank_ref_num'),
                    'completed_at' => now()
                ]);

                $order->update([
                    'payment_status' => 'paid',
                    'payment_metadata' => array_merge($order->payment_metadata ?? [], [
                        'mihpayid' => $mihpayid,
                        'payment_mode' => $request->input('mode')
                    ])
                ]);

                return $this->formatResponse(true, [
                    'order_id' => $order->id,
                    'payment_status' => 'completed',
                    'transaction_id' => $txnId
                ], 'Payment successful');

            } else {
                $this->updatePaymentRecord($payment, 'failed', [
                    'payu_response' => $request->all(),
                    'error' => $request->input('error_Message') ?? $request->input('field9'),
                    'error_code' => $request->input('error_code') ?? $request->input('error'),
                    'failed_at' => now()
                ]);

                return $this->formatResponse(false, [
                    'order_id' => $order->id,
                    'payment_status' => 'failed',
                    'error' => $request->input('error_Message') ?? 'Payment failed'
                ], 'Payment failed');
            }

        } catch (\Exception $e) {
            return $this->handleException($e, 'processCallback');
        }
    }

    public function processWebhook(Request $request): array
    {
        try {
            $this->logActivity('PayU Webhook received', $request->all());

            // Validate webhook hash
            if (!$this->validateWebhookHash($request)) {
                $this->logActivity('Invalid webhook hash', [], 'error');
                // Return 200 OK even for invalid hash to prevent retries
                return $this->formatResponse(false, [], 'Invalid webhook signature');
            }

            $status = $request->input('status');
            $orderId = $request->input('udf1'); // Order ID stored in udf1
            $txnId = $request->input('txnid');
            $mihpayid = $request->input('mihpayid');
            $amount = $request->input('amount');

            // Find order
            $order = Order::find($orderId);
            if (!$order) {
                $this->logActivity('Order not found in webhook', ['order_id' => $orderId], 'warning');
                // Return 200 to prevent retries
                return $this->formatResponse(true, [], 'Order not found');
            }

            // Find payment record
            $payment = Payment::where('order_id', $orderId)
                ->where('gateway', 'payu')
                ->where(function($query) use ($txnId) {
                    $query->where('payment_data->payu_txnid', $txnId)
                          ->orWhere('payment_data->txnid', $txnId);
                })
                ->first();

            if (!$payment) {
                // Create payment record if not exists (webhook might arrive before callback)
                $payment = $this->createPaymentRecord($order, 'pending', [
                    'payu_txnid' => $txnId,
                    'mihpayid' => $mihpayid
                ]);
            }

            // Process based on payment status
            switch (strtolower($status)) {
                case 'success':
                case 'captured':
                    $this->handleSuccessfulWebhook($payment, $order, $request);
                    break;

                case 'failure':
                case 'failed':
                    $this->handleFailedWebhook($payment, $order, $request);
                    break;

                case 'pending':
                    $this->handlePendingWebhook($payment, $order, $request);
                    break;

                default:
                    $this->logActivity('Unknown webhook status', ['status' => $status], 'warning');
            }

            // Always return 200 OK to acknowledge receipt
            return $this->formatResponse(true, [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'status' => $status
            ], 'Webhook processed successfully');

        } catch (\Exception $e) {
            $this->logActivity('Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 'error');

            // Return 200 OK to prevent retries even on error
            return $this->formatResponse(true, [], 'Webhook acknowledged');
        }
    }

    protected function handleSuccessfulWebhook(Payment $payment, Order $order, Request $request)
    {
        // Update payment record with webhook data
        $this->updatePaymentRecord($payment, 'completed', [
            'webhook_response' => $request->all(),
            'mihpayid' => $request->input('mihpayid'),
            'paid_amount' => $request->input('amount'),
            'net_amount_debit' => $request->input('net_amount_debit'),
            'mode' => $request->input('mode'),
            'bank_ref_num' => $request->input('bank_ref_num'),
            'bankcode' => $request->input('bankcode'),
            'card_no' => $request->input('card_no'),
            'name_on_card' => $request->input('name_on_card'),
            'payment_source' => $request->input('payment_source'),
            'pg_type' => $request->input('PG_TYPE'),
            'completed_at' => $request->input('addedon') ?? now()
        ]);

        // Update order status if not already paid
        if ($order->payment_status !== 'paid') {
            $order->update([
                'payment_status' => 'paid',
                'status' => 'processing',
                'payment_metadata' => array_merge($order->payment_metadata ?? [], [
                    'mihpayid' => $request->input('mihpayid'),
                    'payment_mode' => $request->input('mode'),
                    'bank_ref' => $request->input('bank_ref_num'),
                    'webhook_received_at' => now()
                ])
            ]);
        }

        $this->logActivity('Webhook payment successful', [
            'order_id' => $order->id,
            'mihpayid' => $request->input('mihpayid')
        ]);
    }

    protected function handleFailedWebhook(Payment $payment, Order $order, Request $request)
    {
        // Update payment record with failure details
        $this->updatePaymentRecord($payment, 'failed', [
            'webhook_response' => $request->all(),
            'error' => $request->input('error_Message') ?? $request->input('field9'),
            'error_code' => $request->input('error'),
            'unmapped_status' => $request->input('unmappedstatus'),
            'failed_at' => now()
        ]);

        // Update order status if not already failed
        if (!in_array($order->payment_status, ['failed', 'paid'])) {
            $order->update([
                'payment_status' => 'failed',
                'payment_metadata' => array_merge($order->payment_metadata ?? [], [
                    'error_message' => $request->input('error_Message'),
                    'error_code' => $request->input('error'),
                    'webhook_received_at' => now()
                ])
            ]);
        }

        $this->logActivity('Webhook payment failed', [
            'order_id' => $order->id,
            'error' => $request->input('error_Message')
        ], 'warning');
    }

    protected function handlePendingWebhook(Payment $payment, Order $order, Request $request)
    {
        // Update payment record with pending status
        $this->updatePaymentRecord($payment, 'pending', [
            'webhook_response' => $request->all(),
            'unmapped_status' => $request->input('unmappedstatus'),
            'updated_at' => now()
        ]);

        $this->logActivity('Webhook payment pending', [
            'order_id' => $order->id,
            'unmapped_status' => $request->input('unmappedstatus')
        ]);
    }

    protected function validateWebhookHash(Request $request): bool
    {
        $salt = $this->getConfig('salt');
        $status = $request->input('status');
        $receivedHash = $request->input('hash');

        if (!$receivedHash) {
            return false;
        }

        // Webhook hash validation (same as response hash)
        // Format: salt|status||||||udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key
        $hashString = $salt . '|' . $status . '||||||' .
                      ($request->input('udf5') ?? '') . '|' .
                      ($request->input('udf4') ?? '') . '|' .
                      ($request->input('udf3') ?? '') . '|' .
                      ($request->input('udf2') ?? '') . '|' .
                      ($request->input('udf1') ?? '') . '|' .
                      $request->input('email') . '|' .
                      $request->input('firstname') . '|' .
                      $request->input('productinfo') . '|' .
                      $request->input('amount') . '|' .
                      $request->input('txnid') . '|' .
                      $request->input('key');

        $calculatedHash = hash('sha512', $hashString);

        $isValid = hash_equals($calculatedHash, $receivedHash);

        if (!$isValid) {
            $this->logActivity('Webhook hash validation failed', [
                'calculated' => $calculatedHash,
                'received' => $receivedHash
            ], 'warning');
        }

        return $isValid;
    }

    protected function validateResponseHash(Request $request): bool
    {
        $salt = $this->getConfig('salt');
        $status = $request->input('status');

        // Response hash format for PayU
        $hashString = $salt . '|' . $status . '||||||' .
                      ($request->input('udf5') ?? '') . '|' .
                      ($request->input('udf4') ?? '') . '|' .
                      ($request->input('udf3') ?? '') . '|' .
                      ($request->input('udf2') ?? '') . '|' .
                      ($request->input('udf1') ?? '') . '|' .
                      $request->input('email') . '|' .
                      $request->input('firstname') . '|' .
                      $request->input('productinfo') . '|' .
                      $request->input('amount') . '|' .
                      $request->input('txnid') . '|' .
                      $request->input('key');

        $calculatedHash = hash('sha512', $hashString);
        $receivedHash = $request->input('hash');

        $isValid = $calculatedHash === $receivedHash;

        if (!$isValid) {
            $this->logActivity('Hash validation failed', [
                'calculated' => $calculatedHash,
                'received' => $receivedHash
            ], 'warning');
        }

        return $isValid;
    }

    public function refundPayment(string $paymentId, float $amount, array $options = []): array
    {
        try {
            $payment = Payment::find($paymentId);
            if (!$payment) {
                throw new \Exception('Payment record not found');
            }

            $mihpayid = $payment->payment_data['mihpayid'] ?? null;
            if (!$mihpayid) {
                throw new \Exception('PayU payment ID not found');
            }

            // PayU refund API implementation would go here
            // This is a placeholder as PayU refund requires additional API setup

            return $this->formatResponse(false, [], 'Refund API not implemented');

        } catch (\Exception $e) {
            return $this->handleException($e, 'refundPayment');
        }
    }

    public function validateWebhookSignature(Request $request): bool
    {
        // PayU uses the same hash validation for webhooks as callbacks
        return $this->validateWebhookHash($request);
    }

    public function getPaymentStatus(string $paymentId): string
    {
        $payment = Payment::find($paymentId);

        if (!$payment) {
            return 'unknown';
        }

        // Optionally verify with PayU API for real-time status
        $verifyResult = $this->verifyPayment($paymentId);

        if ($verifyResult['success']) {
            return $verifyResult['data']['payment_status'] ?? $payment->status;
        }

        return $payment->status;
    }
}