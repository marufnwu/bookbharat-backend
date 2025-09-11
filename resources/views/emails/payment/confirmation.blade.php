@extends('emails.layout.app')

@section('title', 'Payment Confirmation - Order #' . $order->order_number)

@section('header_subtitle', 'Payment Confirmation')

@section('content')
    <h2>Hi {{ $user->name }},</h2>
    
    <p>We've successfully received your payment for Order #{{ $order->order_number }}. Thank you!</p>
    
    <div class="highlight">
        <h3>Payment Details</h3>
        <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
        <p><strong>Payment Amount:</strong> ₹{{ number_format($payment->amount, 2) }}</p>
        <p><strong>Payment Method:</strong> {{ ucfirst($payment->payment_method) }}</p>
        <p><strong>Payment Date:</strong> {{ $payment->updated_at->format('F j, Y \a\t g:i A') }}</p>
        @if($payment->payment_data && isset($payment->payment_data['gateway_payment_id']))
            <p><strong>Transaction ID:</strong> {{ $payment->payment_data['gateway_payment_id'] }}</p>
        @endif
    </div>

    <h3>Order Summary</h3>
    <div class="order-summary">
        <div class="item-row">
            <div><strong>Order Total</strong></div>
            <div class="text-right"><strong>₹{{ number_format($order->total_amount, 2) }}</strong></div>
        </div>
        <div class="item-row">
            <div>Payment Status</div>
            <div class="text-right">
                <span class="status-badge status-confirmed">Paid</span>
            </div>
        </div>
    </div>

    <p>Your order is now being processed and will be shipped soon. We'll send you tracking information once your order ships.</p>

    <div class="text-center">
        <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}" class="button">
            View Order Status
        </a>
    </div>

    <p><strong>Need a receipt?</strong> You can download your invoice from your account dashboard or by clicking the button above.</p>

    <p>Thank you for your purchase! We appreciate your business.</p>
@endsection