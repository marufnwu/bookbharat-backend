@extends('emails.layout.app')

@section('title', 'Order Update - #' . $order->order_number)

@section('header_subtitle', 'Order Status Update')

@section('content')
    <h2>Hi {{ $user->name }},</h2>
    
    <p>We wanted to update you on the status of your recent order.</p>
    
    <div class="highlight">
        <strong>Order #{{ $order->order_number }}</strong><br>
        Status Updated: <span class="status-badge status-{{ $new_status }}">{{ ucfirst($new_status) }}</span><br>
        <small>Previous status: {{ ucfirst($old_status) }}</small>
    </div>

    <p><strong>{{ $status_message }}</strong></p>

    @if($new_status === 'shipped' && $order->tracking_number)
        <div class="highlight">
            <h3>Tracking Information</h3>
            <p><strong>Tracking Number:</strong> {{ $order->tracking_number }}</p>
            @if($order->shipping_carrier)
                <p><strong>Carrier:</strong> {{ ucfirst($order->shipping_carrier) }}</p>
            @endif
            @if($order->estimated_delivery_date)
                <p><strong>Estimated Delivery:</strong> {{ $order->estimated_delivery_date->format('F j, Y') }}</p>
            @endif
        </div>
    @endif

    @if($new_status === 'delivered')
        <div class="highlight">
            <h3>Order Delivered!</h3>
            <p>Your order was delivered on {{ $order->updated_at->format('F j, Y \a\t g:i A') }}.</p>
            <p>We hope you love your purchase! Please consider leaving a review to help other customers.</p>
        </div>
    @endif

    <h3>Order Details</h3>
    <p>
        <strong>Order Date:</strong> {{ $order->created_at->format('F j, Y') }}<br>
        <strong>Total Amount:</strong> ₹{{ number_format($order->total_amount, 2) }}<br>
        <strong>Payment Method:</strong> {{ ucfirst($order->payment_method ?? 'N/A') }}
    </p>

    <div class="text-center">
        <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}" class="button">
            View Order Details
        </a>
    </div>

    @if($new_status === 'delivered')
        <div class="text-center" style="margin-top: 20px;">
            <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}/review" class="button">
                Write a Review
            </a>
        </div>
    @endif

    <p>Thank you for your business! If you have any questions or concerns, please don't hesitate to contact us.</p>
@endsection