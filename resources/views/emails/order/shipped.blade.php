@extends('emails.layout.app')

@section('title', 'Your Order Has Shipped - #' . $order->order_number)

@section('header_subtitle', 'Order Shipped')

@section('content')
    <h2>Hi {{ $user->name }},</h2>
    
    <p>Great news! Your order has been shipped and is on its way to you.</p>
    
    <div class="highlight">
        <h3>📦 Shipping Details</h3>
        <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
        <p><strong>Tracking Number:</strong> {{ $tracking_number }}</p>
        @if($shipping_carrier)
            <p><strong>Carrier:</strong> {{ ucfirst($shipping_carrier) }}</p>
        @endif
        @if($order->estimated_delivery_date)
            <p><strong>Estimated Delivery:</strong> {{ $order->estimated_delivery_date->format('F j, Y') }}</p>
        @endif
    </div>

    @if($tracking_url)
        <div class="text-center">
            <a href="{{ $tracking_url }}" class="button">
                Track Your Package
            </a>
        </div>
    @endif

    <h3>Shipping Address</h3>
    <p>
        {{ $order->shipping_name }}<br>
        {{ $order->shipping_address['address_line_1'] }}<br>
        @if($order->shipping_address['address_line_2'])
            {{ $order->shipping_address['address_line_2'] }}<br>
        @endif
        {{ $order->shipping_address['city'] }}, {{ $order->shipping_address['state'] }} {{ $order->shipping_address['postal_code'] }}
    </p>

    <h3>What's Next?</h3>
    <ul>
        <li><strong>Track your package:</strong> Use the tracking number above to monitor your shipment</li>
        <li><strong>Prepare for delivery:</strong> Make sure someone is available to receive the package</li>
        <li><strong>Check your package:</strong> Inspect items upon delivery and contact us if there are any issues</li>
    </ul>

    <div class="highlight">
        <h3>📋 Delivery Instructions</h3>
        <ul>
            <li>Please be available during delivery hours</li>
            <li>Valid ID may be required for high-value orders</li>
            <li>If you're not available, the carrier will attempt redelivery</li>
            <li>Check with neighbors if package shows as delivered but you didn't receive it</li>
        </ul>
    </div>

    <p>We're excited for you to receive your order! If you have any questions about your shipment, please don't hesitate to contact us.</p>
@endsection