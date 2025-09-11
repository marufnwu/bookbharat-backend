@extends('emails.layout.app')

@section('title', 'Order Confirmation - #' . $order->order_number)

@section('header_subtitle', 'Order Confirmation')

@section('content')
    <h2>Hi {{ $user->name }},</h2>
    
    <p>Thank you for your order! We've received your order and are preparing it for shipment.</p>
    
    <div class="highlight">
        <strong>Order #{{ $order->order_number }}</strong><br>
        Order Date: {{ $order->created_at->format('F j, Y') }}<br>
        Status: <span class="status-badge status-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
    </div>

    <h3>Order Summary</h3>
    <div class="order-summary">
        @foreach($items as $item)
            <div class="item-row">
                <div>
                    <strong>{{ $item->product->name }}</strong><br>
                    <small>Quantity: {{ $item->quantity }} × ₹{{ number_format($item->unit_price, 2) }}</small>
                    @if($item->product_variant_id)
                        <br><small>Variant: {{ $item->productVariant->formatted_attributes ?? '' }}</small>
                    @endif
                </div>
                <div class="text-right">
                    ₹{{ number_format($item->total_price, 2) }}
                </div>
            </div>
        @endforeach

        <div class="item-row">
            <div><strong>Subtotal</strong></div>
            <div class="text-right">₹{{ number_format($order->subtotal, 2) }}</div>
        </div>

        @if($order->discount_amount > 0)
            <div class="item-row">
                <div>Discount</div>
                <div class="text-right">-₹{{ number_format($order->discount_amount, 2) }}</div>
            </div>
        @endif

        @if($order->shipping_amount > 0)
            <div class="item-row">
                <div>Shipping</div>
                <div class="text-right">₹{{ number_format($order->shipping_amount, 2) }}</div>
            </div>
        @endif

        @if($order->tax_amount > 0)
            <div class="item-row">
                <div>Tax</div>
                <div class="text-right">₹{{ number_format($order->tax_amount, 2) }}</div>
            </div>
        @endif

        <div class="item-row total-row">
            <div><strong>Total</strong></div>
            <div class="text-right"><strong>₹{{ number_format($order->total_amount, 2) }}</strong></div>
        </div>
    </div>

    @if($order->shipping_address)
        <h3>Shipping Address</h3>
        <p>
            {{ $order->shipping_name }}<br>
            {{ $order->shipping_address['address_line_1'] }}<br>
            @if($order->shipping_address['address_line_2'])
                {{ $order->shipping_address['address_line_2'] }}<br>
            @endif
            {{ $order->shipping_address['city'] }}, {{ $order->shipping_address['state'] }} {{ $order->shipping_address['postal_code'] }}<br>
            @if($order->shipping_phone)
                Phone: {{ $order->shipping_phone }}
            @endif
        </p>
    @endif

    <div class="text-center">
        <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}" class="button">
            Track Your Order
        </a>
    </div>

    <p>We'll send you another email when your order ships. If you have any questions about your order, please contact our customer service team.</p>
@endsection