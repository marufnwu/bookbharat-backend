<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentConfiguration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Razorpay\Api\Api as RazorpayApi;
use Cashfree\Cashfree;

class PaymentService
{
    protected $razorpayApi;
    protected $cashfreeApi;
    
    public function __construct()
    {
        // Initialize Razorpay API
        if (config('services.razorpay.key') && config('services.razorpay.secret')) {
            $this->razorpayApi = new RazorpayApi(
                config('services.razorpay.key'),
                config('services.razorpay.secret')
            );
        }
        
        // Initialize Cashfree
        if (config('services.cashfree.client_id') && config('services.cashfree.client_secret')) {
            Cashfree::$XClientId = config('services.cashfree.client_id');
            Cashfree::$XClientSecret = config('services.cashfree.client_secret');
            Cashfree::$XEnvironment = config('services.cashfree.environment', 'TEST');
        }
    }

    public function processPayment(Order $order, string $paymentMethod, array $additionalData = [])
    {
        try {
            // Get payment configuration
            $config = PaymentConfiguration::where('payment_method', $paymentMethod)
                ->where('is_enabled', true)
                ->first();

            if (!$config) {
                throw new \Exception("Payment method '{$paymentMethod}' is not available");
            }

            // Check if payment method is available for this order
            if (!$config->isAvailableForOrder($order->total_amount)) {
                throw new \Exception("Payment method '{$paymentMethod}' is not available for this order amount");
            }

            switch ($paymentMethod) {
                case 'cod':
                case 'cod_with_advance':
                case 'cod_percentage_advance':
                    return $this->processCODVariants($order, $config);
                
                case 'razorpay':
                    return $this->processRazorpay($order, $additionalData);
                
                case 'cashfree':
                    return $this->processCashfree($order, $additionalData);
                
                case 'bank_transfer':
                    return $this->processBankTransfer($order, $config);
                
                default:
                    throw new \Exception('Invalid payment method');
            }
        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    protected function processCODVariants(Order $order, PaymentConfiguration $config)
    {
        $advanceAmount = $config->getAdvancePaymentAmount($order->total_amount);
        $codAmount = $order->total_amount - $advanceAmount;
        
        $result = [
            'status' => $advanceAmount > 0 ? 'advance_required' : 'pending',
            'transaction_id' => 'COD_' . $order->id . '_' . time(),
            'payment_method' => $config->payment_method,
            'message' => $config->requiresAdvancePayment() 
                ? "Pay advance amount of ₹{$advanceAmount} online, remaining ₹{$codAmount} on delivery"
                : 'Cash on delivery order placed successfully',
            'payment_data' => [
                'method' => $config->payment_method,
                'advance_amount' => $advanceAmount,
                'cod_amount' => $codAmount,
                'total_amount' => $order->total_amount,
                'service_charges' => $config->configuration['service_charges'] ?? null,
                'created_at' => now()
            ]
        ];

        // If advance payment required, create Razorpay order for advance amount
        if ($advanceAmount > 0) {
            try {
                if (!$this->razorpayApi) {
                    throw new \Exception('Razorpay API not configured for advance payment');
                }

                $razorpayOrder = $this->razorpayApi->order->create([
                    'receipt' => 'advance_' . $order->id,
                    'amount' => $advanceAmount * 100, // Amount in paise
                    'currency' => $order->currency ?? 'INR',
                    'payment_capture' => 1,
                    'notes' => [
                        'order_id' => $order->id,
                        'payment_type' => 'cod_advance',
                        'advance_amount' => $advanceAmount,
                        'cod_amount' => $codAmount
                    ]
                ]);

                $result['advance_payment'] = [
                    'gateway_order_id' => $razorpayOrder['id'],
                    'amount' => $advanceAmount,
                    'razorpay_key' => config('services.razorpay.key'),
                    'checkout_data' => [
                        'key' => config('services.razorpay.key'),
                        'order_id' => $razorpayOrder['id'],
                        'amount' => $razorpayOrder['amount'],
                        'currency' => $razorpayOrder['currency'],
                        'name' => config('app.name'),
                        'description' => 'Advance payment for Order #' . $order->order_number,
                        'prefill' => [
                            'name' => $order->user->name,
                            'email' => $order->user->email,
                            'contact' => $order->billing_address['phone'] ?? $order->shipping_address['phone'] ?? $order->user->phone
                        ]
                    ]
                ];

            } catch (\Exception $e) {
                Log::error('COD Advance payment setup failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
                throw new \Exception('Unable to setup advance payment: ' . $e->getMessage());
            }
        }

        return $result;
    }

    protected function processBankTransfer(Order $order, PaymentConfiguration $config)
    {
        $bankDetails = $config->configuration['bank_details'] ?? [];
        
        return [
            'status' => 'pending_verification',
            'transaction_id' => 'BANK_' . $order->id . '_' . time(),
            'payment_method' => 'bank_transfer',
            'message' => 'Transfer the amount to our bank account and upload payment proof',
            'payment_data' => [
                'method' => 'bank_transfer',
                'bank_details' => $bankDetails,
                'amount' => $order->total_amount,
                'verification_required' => $config->configuration['verification_required'] ?? true,
                'created_at' => now()
            ],
            'bank_details' => $bankDetails
        ];
    }

    protected function processRazorpay(Order $order, array $additionalData = [])
    {
        try {
            if (!$this->razorpayApi) {
                throw new \Exception('Razorpay API not configured');
            }
            
            // Create Razorpay order
            $razorpayOrder = $this->razorpayApi->order->create([
                'receipt' => 'order_' . $order->id,
                'amount' => $order->total_amount * 100, // Amount in paise
                'currency' => $order->currency ?? 'INR',
                'payment_capture' => 1,
                'notes' => [
                    'order_id' => $order->id,
                    'customer_id' => $order->user_id,
                    'merchant_order_id' => $order->order_number
                ]
            ]);
            
            return [
                'status' => 'pending',
                'transaction_id' => $razorpayOrder['id'],
                'payment_method' => 'razorpay',
                'key_id' => config('services.razorpay.key'),
                'order_id' => $razorpayOrder['id'],
                'amount' => $razorpayOrder['amount'],
                'currency' => $razorpayOrder['currency'],
                'name' => config('app.name'),
                'description' => 'Order #' . $order->order_number,
                'prefill' => [
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'contact' => $order->shipping_phone ?? $order->user->phone
                ],
                'payment_data' => [
                    'method' => 'razorpay',
                    'gateway_order_id' => $razorpayOrder['id'],
                    'receipt' => $razorpayOrder['receipt'],
                    'created_at' => now(),
                    'razorpay_response' => $razorpayOrder->toArray()
                ],
                'message' => 'Razorpay payment initiated successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Razorpay payment creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function processCashfree(Order $order, array $additionalData = [])
    {
        try {
            if (!config('services.cashfree.client_id') || !config('services.cashfree.client_secret')) {
                throw new \Exception('Cashfree API not configured');
            }
            
            $customerDetails = [
                'customer_id' => (string)$order->user_id,
                'customer_name' => $order->user->name,
                'customer_email' => $order->user->email,
                'customer_phone' => $order->shipping_phone ?? $order->user->phone ?? '+919999999999'
            ];
            
            $orderData = [
                'order_id' => 'CF_' . $order->id . '_' . time(),
                'order_amount' => (float)$order->total_amount,
                'order_currency' => $order->currency ?? 'INR',
                'order_note' => 'Order #' . $order->order_number,
                'customer_details' => $customerDetails,
                'order_meta' => [
                    'return_url' => route('payment.callback.cashfree', ['order_id' => $order->id]),
                    'notify_url' => route('webhook.cashfree')
                ]
            ];
            
            // Create Cashfree order using HTTP client
            $response = Http::withHeaders([
                'X-Client-Id' => config('services.cashfree.client_id'),
                'X-Client-Secret' => config('services.cashfree.client_secret'),
                'x-api-version' => '2022-09-01',
                'Content-Type' => 'application/json'
            ])->post(config('services.cashfree.base_url') . '/orders', $orderData);
            
            if (!$response->successful()) {
                throw new \Exception('Cashfree order creation failed: ' . $response->body());
            }
            
            $cashfreeOrder = $response->json();
            
            return [
                'status' => 'pending',
                'transaction_id' => $orderData['order_id'],
                'cf_order_id' => $cashfreeOrder['cf_order_id'] ?? null,
                'payment_method' => 'cashfree',
                'payment_session_id' => $cashfreeOrder['payment_session_id'] ?? null,
                'order_token' => $cashfreeOrder['order_token'] ?? null,
                'checkout_url' => $cashfreeOrder['payment_link'] ?? null,
                'payment_data' => [
                    'method' => 'cashfree',
                    'gateway_order_id' => $orderData['order_id'],
                    'cf_order_id' => $cashfreeOrder['cf_order_id'] ?? null,
                    'created_at' => now(),
                    'cashfree_response' => $cashfreeOrder
                ],
                'message' => 'Cashfree payment initiated successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Cashfree payment creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updatePaymentStatus($transactionId, $status, $gatewayData = [])
    {
        $payment = Payment::where('payment_data->gateway_order_id', $transactionId)->first();
        
        if (!$payment) {
            throw new \Exception('Payment not found for transaction ID: ' . $transactionId);
        }

        $payment->update([
            'status' => $status,
            'gateway_response' => $gatewayData,
            'payment_data' => array_merge($payment->payment_data ?? [], [
                'gateway_payment_id' => $gatewayData['razorpay_payment_id'] ?? $gatewayData['cf_payment_id'] ?? null,
                'gateway_signature' => $gatewayData['razorpay_signature'] ?? null,
                'updated_at' => now()
            ]),
            'updated_at' => now()
        ]);

        // Update order payment status
        $order = $payment->order;
        $order->update([
            'payment_status' => $status,
            'updated_at' => now()
        ]);
        
        // Log payment status update
        Log::info('Payment status updated', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'old_status' => $payment->getOriginal('status'),
            'new_status' => $status,
            'transaction_id' => $transactionId
        ]);

        return $payment;
    }

    public function refundPayment($paymentId, $amount = null, $reason = 'order_cancellation')
    {
        $payment = Payment::findOrFail($paymentId);
        
        if ($payment->status !== 'completed') {
            throw new \Exception('Cannot refund incomplete payment');
        }

        $refundAmount = $amount ?? $payment->amount;
        
        // Create refund record
        $refund = PaymentRefund::create([
            'payment_id' => $payment->id,
            'amount' => $refundAmount,
            'status' => 'processing',
            'refund_data' => [
                'initiated_at' => now(),
                'reason' => $reason,
                'requested_amount' => $refundAmount
            ]
        ]);

        // Process actual refund based on payment method
        try {
            switch ($payment->payment_method) {
                case 'cod':
                    // COD refunds are handled manually
                    $refund->update(['status' => 'manual']);
                    break;
                    
                case 'razorpay':
                    $this->processRazorpayRefund($payment, $refund, $refundAmount);
                    break;
                    
                case 'cashfree':
                    $this->processCashfreeRefund($payment, $refund, $refundAmount);
                    break;
            }
        } catch (\Exception $e) {
            $refund->update([
                'status' => 'failed',
                'refund_data' => array_merge($refund->refund_data ?? [], [
                    'error' => $e->getMessage(),
                    'failed_at' => now()
                ])
            ]);
            throw $e;
        }

        return $refund;
    }
    
    protected function processRazorpayRefund($payment, $refund, $refundAmount)
    {
        if (!$this->razorpayApi) {
            throw new \Exception('Razorpay API not configured');
        }
        
        $gatewayPaymentId = $payment->payment_data['gateway_payment_id'] ?? null;
        if (!$gatewayPaymentId) {
            throw new \Exception('Gateway payment ID not found');
        }
        
        try {
            $razorpayRefund = $this->razorpayApi->refund->create([
                'payment_id' => $gatewayPaymentId,
                'amount' => $refundAmount * 100, // Amount in paise
                'speed' => 'normal',
                'notes' => [
                    'refund_id' => $refund->id,
                    'reason' => $refund->refund_data['reason'] ?? 'order_cancellation'
                ]
            ]);
            
            $refund->update([
                'status' => 'completed',
                'refund_data' => array_merge($refund->refund_data ?? [], [
                    'gateway_refund_id' => $razorpayRefund['id'],
                    'gateway_response' => $razorpayRefund->toArray(),
                    'completed_at' => now()
                ])
            ]);
            
        } catch (\Exception $e) {
            Log::error('Razorpay refund failed', [
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    protected function processCashfreeRefund($payment, $refund, $refundAmount)
    {
        $gatewayOrderId = $payment->payment_data['gateway_order_id'] ?? null;
        if (!$gatewayOrderId) {
            throw new \Exception('Gateway order ID not found');
        }
        
        try {
            $refundData = [
                'refund_amount' => (float)$refundAmount,
                'refund_id' => 'refund_' . $refund->id . '_' . time(),
                'refund_note' => $refund->refund_data['reason'] ?? 'order_cancellation'
            ];
            
            $response = Http::withHeaders([
                'X-Client-Id' => config('services.cashfree.client_id'),
                'X-Client-Secret' => config('services.cashfree.client_secret'),
                'x-api-version' => '2022-09-01',
                'Content-Type' => 'application/json'
            ])->post(config('services.cashfree.base_url') . '/orders/' . $gatewayOrderId . '/refunds', $refundData);
            
            if (!$response->successful()) {
                throw new \Exception('Cashfree refund failed: ' . $response->body());
            }
            
            $cashfreeRefund = $response->json();
            
            $refund->update([
                'status' => 'completed',
                'refund_data' => array_merge($refund->refund_data ?? [], [
                    'gateway_refund_id' => $refundData['refund_id'],
                    'cf_refund_id' => $cashfreeRefund['cf_refund_id'] ?? null,
                    'gateway_response' => $cashfreeRefund,
                    'completed_at' => now()
                ])
            ]);
            
        } catch (\Exception $e) {
            Log::error('Cashfree refund failed', [
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    public function verifyRazorpayPayment($razorpayOrderId, $razorpayPaymentId, $razorpaySignature)
    {
        if (!$this->razorpayApi) {
            throw new \Exception('Razorpay API not configured');
        }
        
        try {
            $attributes = [
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'razorpay_signature' => $razorpaySignature
            ];
            
            $this->razorpayApi->utility->verifyPaymentSignature($attributes);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Razorpay signature verification failed', [
                'order_id' => $razorpayOrderId,
                'payment_id' => $razorpayPaymentId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    public function verifyCashfreePayment($orderId)
    {
        try {
            $response = Http::withHeaders([
                'X-Client-Id' => config('services.cashfree.client_id'),
                'X-Client-Secret' => config('services.cashfree.client_secret'),
                'x-api-version' => '2022-09-01'
            ])->get(config('services.cashfree.base_url') . '/orders/' . $orderId . '/payments');
            
            if (!$response->successful()) {
                throw new \Exception('Cashfree payment verification failed: ' . $response->body());
            }
            
            return $response->json();
            
        } catch (\Exception $e) {
            Log::error('Cashfree payment verification failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}