<?php

namespace App\Services\Payment\Gateways;

use App\Services\Payment\BasePaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayGateway extends BasePaymentGateway
{
    protected $api;
    protected $gatewayName = 'Razorpay';

    public function __construct()
    {
        parent::__construct();
        $this->initializeApi();
    }

    protected function getGatewayKeyword(): string
    {
        return 'razorpay';
    }

    protected function initializeApi()
    {
        $key = $this->getConfig('key');
        $secret = $this->getConfig('secret');

        if ($key && $secret) {
            $this->api = new Api($key, $secret);
        }
    }

    protected function hasRequiredConfiguration(): bool
    {
        return !empty($this->getConfig('key')) && !empty($this->getConfig('secret'));
    }

    public function initiatePayment(Order $order, array $options = []): array
    {
        try {
            $this->validateOrderAmount($order);

            if (!$this->isCurrencySupported($order->currency ?? 'INR')) {
                throw new \Exception('Currency not supported');
            }

            // Create Razorpay order
            $razorpayOrder = $this->api->order->create([
                'receipt' => $order->order_number,
                'amount' => $order->payable_amount * 100, // Amount in paise
                'currency' => $order->currency ?? 'INR',
                'payment_capture' => 1,
                'notes' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'user_id' => $order->user_id
                ]
            ]);

            // Create payment record
            $payment = $this->createPaymentRecord($order, 'pending', [
                'razorpay_order_id' => $razorpayOrder['id'],
                'amount' => $razorpayOrder['amount'] / 100
            ]);

            // Update order with payment info
            $order->update([
                'payment_metadata' => array_merge($order->payment_metadata ?? [], [
                    'razorpay_order_id' => $razorpayOrder['id'],
                    'payment_id' => $payment->id
                ])
            ]);

            $billingInfo = $order->billing_address ?? $order->shipping_address;

            return $this->formatResponse(true, [
                'payment_id' => $payment->id,
                'razorpay_order_id' => $razorpayOrder['id'],
                'key' => $this->getConfig('key'),
                'amount' => $razorpayOrder['amount'],
                'currency' => $razorpayOrder['currency'],
                'name' => config('app.name'),
                'description' => 'Order #' . $order->order_number,
                'prefill' => [
                    'name' => $billingInfo['first_name'] . ' ' . $billingInfo['last_name'],
                    'email' => $billingInfo['email'] ?? $order->user->email,
                    'contact' => $billingInfo['phone'] ?? ''
                ],
                'callback_url' => $this->getCallbackUrl(),
                'theme' => [
                    'color' => '#3B82F6'
                ]
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

            $razorpayOrderId = $payment->payment_data['razorpay_order_id'] ?? null;
            if (!$razorpayOrderId) {
                throw new \Exception('Razorpay order ID not found');
            }

            $razorpayOrder = $this->api->order->fetch($razorpayOrderId);
            $payments = $this->api->order->fetch($razorpayOrderId)->payments();

            $status = 'pending';
            $paidAmount = 0;

            if ($razorpayOrder->status === 'paid') {
                $status = 'completed';
                $paidAmount = $razorpayOrder->amount_paid / 100;
            } else if (count($payments->items) > 0) {
                foreach ($payments->items as $rzpPayment) {
                    if ($rzpPayment->status === 'captured') {
                        $status = 'completed';
                        $paidAmount = $rzpPayment->amount / 100;
                        break;
                    }
                }
            }

            if ($status === 'completed' && $payment->status !== 'completed') {
                $this->updatePaymentRecord($payment, 'completed', [
                    'verification_response' => $razorpayOrder->toArray(),
                    'paid_amount' => $paidAmount
                ]);
            }

            return $this->formatResponse(true, [
                'payment_status' => $status,
                'paid_amount' => $paidAmount,
                'razorpay_order' => $razorpayOrder->toArray()
            ], 'Payment verified');

        } catch (\Exception $e) {
            return $this->handleException($e, 'verifyPayment');
        }
    }

    public function processCallback(Request $request): array
    {
        try {
            $this->logActivity('processCallback', $request->all());

            if (empty($request->razorpay_payment_id)) {
                throw new \Exception('Payment ID not received');
            }

            // Verify signature
            $attributes = [
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature
            ];

            try {
                $this->api->utility->verifyPaymentSignature($attributes);
            } catch (SignatureVerificationError $e) {
                throw new \Exception('Payment verification failed: ' . $e->getMessage());
            }

            // Find order by Razorpay order ID
            $order = Order::where('payment_metadata->razorpay_order_id', $request->razorpay_order_id)->first();
            if (!$order) {
                throw new \Exception('Order not found');
            }

            // Find payment record
            $payment = Payment::where('payment_data->razorpay_order_id', $request->razorpay_order_id)
                ->where('order_id', $order->id)
                ->first();

            if (!$payment) {
                $payment = $this->createPaymentRecord($order, 'pending', [
                    'razorpay_order_id' => $request->razorpay_order_id
                ]);
            }

            // Fetch payment details from Razorpay
            $razorpayPayment = $this->api->payment->fetch($request->razorpay_payment_id);

            // Update payment record
            $this->updatePaymentRecord($payment, 'completed', [
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
                'payment_details' => $razorpayPayment->toArray(),
                'payment_method_details' => $razorpayPayment->method ?? null,
                'paid_amount' => $razorpayPayment->amount / 100
            ]);

            // Update order
            $order->update([
                'payment_status' => 'paid',
                'payment_method' => 'razorpay',
                'transaction_id' => $request->razorpay_payment_id
            ]);

            return $this->formatResponse(true, [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'transaction_id' => $request->razorpay_payment_id
            ], 'Payment successful');

        } catch (\Exception $e) {
            return $this->handleException($e, 'processCallback');
        }
    }

    public function processWebhook(Request $request): array
    {
        try {
            $this->logActivity('processWebhook', $request->all());

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
                    $this->logActivity('Unhandled webhook event', ['event' => $event]);
                    return $this->formatResponse(true, [], 'Event ignored');
            }

        } catch (\Exception $e) {
            return $this->handleException($e, 'processWebhook');
        }
    }

    protected function handlePaymentCaptured($payload): array
    {
        $paymentEntity = $payload['payment']['entity'];
        $orderId = $paymentEntity['notes']['order_id'] ?? null;

        if (!$orderId) {
            return $this->formatResponse(false, [], 'Order ID not found in payment notes');
        }

        $order = Order::find($orderId);
        if (!$order) {
            return $this->formatResponse(false, [], 'Order not found');
        }

        $payment = Payment::where('order_id', $orderId)
            ->where('payment_data->razorpay_order_id', $paymentEntity['order_id'])
            ->first();

        if ($payment && $payment->status !== 'completed') {
            $this->updatePaymentRecord($payment, 'completed', [
                'webhook_payment_captured' => $paymentEntity,
                'captured_amount' => $paymentEntity['amount'] / 100
            ]);

            $order->update([
                'payment_status' => 'paid',
                'transaction_id' => $paymentEntity['id']
            ]);
        }

        return $this->formatResponse(true, [], 'Payment captured');
    }

    protected function handlePaymentFailed($payload): array
    {
        $paymentEntity = $payload['payment']['entity'];
        $orderId = $paymentEntity['notes']['order_id'] ?? null;

        if ($orderId) {
            $payment = Payment::where('order_id', $orderId)
                ->where('payment_data->razorpay_order_id', $paymentEntity['order_id'])
                ->first();

            if ($payment && $payment->status !== 'failed') {
                $this->updatePaymentRecord($payment, 'failed', [
                    'failure_reason' => $paymentEntity['error_description'] ?? 'Payment failed',
                    'failure_code' => $paymentEntity['error_code'] ?? null
                ]);

                Order::find($orderId)->update(['payment_status' => 'failed']);
            }
        }

        return $this->formatResponse(true, [], 'Payment failed processed');
    }

    protected function handleOrderPaid($payload): array
    {
        $orderEntity = $payload['order']['entity'];
        $order = Order::where('payment_metadata->razorpay_order_id', $orderEntity['id'])->first();

        if ($order) {
            $payment = Payment::where('order_id', $order->id)
                ->where('payment_data->razorpay_order_id', $orderEntity['id'])
                ->first();

            if ($payment && $payment->status !== 'completed') {
                $this->updatePaymentRecord($payment, 'completed', [
                    'order_paid' => $orderEntity,
                    'paid_amount' => $orderEntity['amount_paid'] / 100
                ]);

                $order->update(['payment_status' => 'paid']);
            }
        }

        return $this->formatResponse(true, [], 'Order paid processed');
    }

    public function refundPayment(string $paymentId, float $amount, array $options = []): array
    {
        try {
            $payment = Payment::find($paymentId);
            if (!$payment) {
                throw new \Exception('Payment not found');
            }

            $razorpayPaymentId = $payment->gateway_response['razorpay_payment_id'] ?? null;
            if (!$razorpayPaymentId) {
                throw new \Exception('Razorpay payment ID not found');
            }

            $refund = $this->api->refund->create([
                'payment_id' => $razorpayPaymentId,
                'amount' => $amount * 100, // Amount in paise
                'notes' => [
                    'reason' => $options['reason'] ?? 'Customer requested',
                    'payment_id' => $paymentId
                ]
            ]);

            // Update payment status
            if ($refund->amount === $payment->amount * 100) {
                $this->updatePaymentRecord($payment, 'refunded', [
                    'refund_id' => $refund->id,
                    'refunded_amount' => $refund->amount / 100
                ]);
            } else {
                $this->updatePaymentRecord($payment, 'partially_refunded', [
                    'refund_id' => $refund->id,
                    'refunded_amount' => $refund->amount / 100
                ]);
            }

            return $this->formatResponse(true, [
                'refund_id' => $refund->id,
                'amount' => $refund->amount / 100,
                'status' => $refund->status
            ], 'Refund initiated successfully');

        } catch (\Exception $e) {
            return $this->handleException($e, 'refundPayment');
        }
    }

    public function validateWebhookSignature(Request $request): bool
    {
        try {
            $webhookSignature = $request->header('X-Razorpay-Signature');
            $webhookSecret = $this->getConfig('webhook_secret');

            if (!$webhookSecret) {
                $this->logActivity('Webhook secret not configured', [], 'warning');
                return true; // Skip validation if secret not configured
            }

            $expectedSignature = hash_hmac('sha256', $request->getContent(), $webhookSecret);

            return hash_equals($expectedSignature, $webhookSignature);

        } catch (\Exception $e) {
            $this->logActivity('Webhook signature validation failed', ['error' => $e->getMessage()], 'error');
            return false;
        }
    }
}