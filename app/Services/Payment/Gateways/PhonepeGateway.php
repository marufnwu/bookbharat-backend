<?php

namespace App\Services\Payment\Gateways;

use App\Services\Payment\BasePaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PhonepeGateway extends BasePaymentGateway
{
    protected $gatewayName = 'PhonePe';
    protected $apiUrl;
    protected $endPoint = "/pg/v1/pay";

    public function __construct()
    {
        parent::__construct();
        $this->initializeApiUrl();
    }

    protected function getGatewayKeyword(): string
    {
        return 'phonepe';
    }

    protected function initializeApiUrl()
    {
        $this->apiUrl = $this->isProduction
            ? ($this->getConfig('production_url') ?? 'https://api.phonepe.com/apis/hermes')
            : ($this->getConfig('sandbox_url') ?? 'https://api-preprod.phonepe.com/apis/pg-sandbox');
    }

    protected function hasRequiredConfiguration(): bool
    {
        return !empty($this->getConfig('merchant_id')) &&
               !empty($this->getConfig('salt_key')) &&
               !empty($this->getConfig('salt_index'));
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        try {
            $this->validateOrderAmount($order);

            if (!$this->isCurrencySupported($order->currency ?? 'INR')) {
                throw new \Exception('Currency not supported');
            }

            $merchantTransactionId = 'TXN' . $order->id . '_' . time();

            // Prepare payload
            $payload = [
                "merchantId" => $this->getConfig('merchant_id'),
                "merchantTransactionId" => $merchantTransactionId,
                "merchantUserId" => (string)($order->user_id ?? 'guest_' . $order->id),
                "amount" => (int)(($order->is_cod ? $order->prepaid_amount : $order->payable_amount) * 100),
                "redirectUrl" => $this->getCallbackUrl(),
                "redirectMode" => "POST",
                "callbackUrl" => $this->getWebhookUrl(),
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
            $hashString = $base64Payload . $this->endPoint . $this->getConfig('salt_key');
            $xVerify = hash('sha256', $hashString) . "###" . $this->getConfig('salt_index');

            // Make API request
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-VERIFY' => $xVerify,
            ])->post($this->apiUrl . $this->endPoint, [
                'request' => $base64Payload
            ]);

            if (!$response->successful()) {
                $this->logActivity('PhonePe API Error', ['response' => $response->body()], 'error');
                throw new \Exception('PhonePe gateway error');
            }

            $responseData = json_decode($response->body());

            if (!$responseData->success) {
                throw new \Exception($responseData->message ?? 'Payment initiation failed');
            }

            // Create payment record
            $payment = $this->createPaymentRecord($order, 'pending', [
                'phonepe_transaction_id' => $merchantTransactionId,
                'phonepe_merchant_transaction_id' => $merchantTransactionId
            ]);

            // Update order with payment info
            $order->update([
                'payment_metadata' => array_merge($order->payment_metadata ?? [], [
                    'phonepe_transaction_id' => $merchantTransactionId,
                    'payment_id' => $payment->id
                ])
            ]);

            return $this->formatResponse(true, [
                'payment_id' => $payment->id,
                'payment_url' => $responseData->data->instrumentResponse->redirectInfo->url,
                'transaction_id' => $merchantTransactionId
            ], 'Payment initiated successfully');

        } catch (\Exception $e) {
            return $this->handleException($e, 'initiatePayment');
        }
    }

    public function verifyPayment(string $paymentId): array
    {
        try {
            $payment = Payment::find($paymentId);
            if (!$payment) {
                throw new \Exception('Payment record not found');
            }

            $merchantTransactionId = $payment->payment_data['phonepe_transaction_id'] ?? null;
            if (!$merchantTransactionId) {
                throw new \Exception('Transaction ID not found');
            }

            $statusResponse = $this->checkPaymentStatus($merchantTransactionId);

            if ($statusResponse['success'] && $statusResponse['code'] === 'PAYMENT_SUCCESS') {
                if ($payment->status !== 'completed') {
                    $this->updatePaymentRecord($payment, 'completed', [
                        'status_check_response' => $statusResponse['data']
                    ]);

                    $payment->order->update(['payment_status' => 'paid']);
                }

                return $this->formatResponse(true, [
                    'payment_status' => 'completed',
                    'transaction_id' => $merchantTransactionId,
                    'response' => $statusResponse['data']
                ], 'Payment verified successfully');
            }

            return $this->formatResponse(false, [
                'payment_status' => 'pending',
                'response' => $statusResponse
            ], 'Payment not completed');

        } catch (\Exception $e) {
            return $this->handleException($e, 'verifyPayment');
        }
    }

    protected function checkPaymentStatus(string $merchantTransactionId): array
    {
        $merchantId = $this->getConfig('merchant_id');
        $statusUrl = $this->apiUrl . "/pg/v1/status/" . $merchantId . "/" . $merchantTransactionId;

        // Generate X-VERIFY header for status check
        $hashString = "/pg/v1/status/" . $merchantId . "/" . $merchantTransactionId . $this->getConfig('salt_key');
        $xVerify = hash('sha256', $hashString) . "###" . $this->getConfig('salt_index');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-VERIFY' => $xVerify,
            'X-MERCHANT-ID' => $merchantId
        ])->get($statusUrl);

        if (!$response->successful()) {
            throw new \Exception('Failed to check payment status');
        }

        $responseData = json_decode($response->body(), true);
        return $responseData;
    }

    public function processCallback(Request $request): array
    {
        try {
            $this->logActivity('processCallback', $request->all());

            $transactionId = $request->input('transactionId');
            $code = $request->input('code');

            // Extract order ID from transaction ID
            preg_match('/TXN(\d+)_/', $transactionId, $matches);
            $orderId = $matches[1] ?? null;

            if (!$orderId) {
                throw new \Exception('Invalid transaction ID format');
            }

            $order = Order::find($orderId);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            // Find or create payment record
            $payment = Payment::where('payment_data->phonepe_transaction_id', $transactionId)
                ->where('order_id', $orderId)
                ->first();

            if (!$payment) {
                $payment = $this->createPaymentRecord($order, 'pending', [
                    'phonepe_transaction_id' => $transactionId
                ]);
            }

            if ($code === 'PAYMENT_SUCCESS') {
                $providerReferenceId = $request->input('providerReferenceId');

                $this->updatePaymentRecord($payment, 'completed', [
                    'phonepe_response' => $request->all(),
                    'phonepe_provider_reference_id' => $providerReferenceId
                ]);

                $order->update([
                    'payment_status' => 'paid',
                    'payment_method' => 'phonepe',
                    'transaction_id' => $providerReferenceId
                ]);

                return $this->formatResponse(true, [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'transaction_id' => $providerReferenceId
                ], 'Payment successful');

            } else if ($code === 'PAYMENT_FAILURE' || $code === 'PAYMENT_DECLINED') {
                $this->updatePaymentRecord($payment, 'failed', [
                    'phonepe_response' => $request->all(),
                    'failure_code' => $code
                ]);

                $order->update(['payment_status' => 'failed']);

                return $this->formatResponse(false, [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id
                ], 'Payment failed');
            }

            return $this->formatResponse(true, [
                'payment_id' => $payment->id,
                'order_id' => $order->id
            ], 'Payment processing');

        } catch (\Exception $e) {
            return $this->handleException($e, 'processCallback');
        }
    }

    public function processWebhook(Request $request): array
    {
        try {
            $this->logActivity('processWebhook', $request->all());

            $xVerify = $request->header("X-Verify", null);
            $response = $request->input("response");

            if (!$response || !$xVerify) {
                return $this->formatResponse(false, [], 'Invalid webhook data');
            }

            // Verify the webhook signature
            $expectedHash = hash('sha256', $response . $this->getConfig('salt_key')) . "###" . $this->getConfig('salt_index');
            if ($xVerify !== $expectedHash) {
                $this->logActivity('Webhook Signature Mismatch', [
                    'expected' => $expectedHash,
                    'received' => $xVerify
                ], 'error');
                return $this->formatResponse(false, [], 'Invalid signature');
            }

            // Decode response
            $json = base64_decode($response);
            $resData = json_decode($json);

            // Extract order ID from merchant transaction ID
            $merchantTransactionId = $resData->data->merchantTransactionId;
            preg_match('/TXN(\d+)_/', $merchantTransactionId, $matches);
            $orderId = $matches[1] ?? null;

            if (!$orderId) {
                return $this->formatResponse(false, [], 'Invalid transaction ID format');
            }

            $order = Order::find($orderId);
            if (!$order) {
                return $this->formatResponse(false, [], 'Order not found');
            }

            $payment = Payment::where('payment_data->phonepe_transaction_id', $merchantTransactionId)
                ->where('order_id', $orderId)
                ->first();

            if (!$payment) {
                $payment = $this->createPaymentRecord($order, 'pending', [
                    'phonepe_transaction_id' => $merchantTransactionId
                ]);
            }

            if ($resData->code === 'PAYMENT_SUCCESS') {
                $transactionId = $resData->data->transactionId;
                $paidAmount = $resData->data->amount / 100;

                $this->updatePaymentRecord($payment, 'completed', [
                    'phonepe_webhook_response' => $resData,
                    'paid_amount' => $paidAmount,
                    'transaction_id' => $transactionId
                ]);

                $order->update([
                    'payment_status' => 'paid',
                    'payment_method' => 'phonepe',
                    'transaction_id' => $transactionId
                ]);

                return $this->formatResponse(true, [], 'Payment processed successfully');

            } else if ($resData->code === 'PAYMENT_FAILURE' || $resData->code === 'PAYMENT_DECLINED') {
                $this->updatePaymentRecord($payment, 'failed', [
                    'phonepe_webhook_response' => $resData,
                    'failure_reason' => $resData->message ?? 'Payment failed'
                ]);

                $order->update(['payment_status' => 'failed']);

                return $this->formatResponse(true, [], 'Payment failure processed');
            }

            return $this->formatResponse(true, [], 'Webhook processed');

        } catch (\Exception $e) {
            return $this->handleException($e, 'processWebhook');
        }
    }

    public function refundPayment(string $paymentId, float $amount, array $options = []): array
    {
        try {
            // PhonePe refund implementation would go here
            // This would require PhonePe's refund API integration

            throw new \Exception('PhonePe refund not implemented yet');

        } catch (\Exception $e) {
            return $this->handleException($e, 'refundPayment');
        }
    }

    public function validateWebhookSignature(Request $request): bool
    {
        try {
            $xVerify = $request->header("X-Verify", null);
            $response = $request->input("response");

            if (!$response || !$xVerify) {
                return false;
            }

            $expectedHash = hash('sha256', $response . $this->getConfig('salt_key')) . "###" . $this->getConfig('salt_index');
            return $xVerify === $expectedHash;

        } catch (\Exception $e) {
            $this->logActivity('Webhook signature validation failed', ['error' => $e->getMessage()], 'error');
            return false;
        }
    }
}