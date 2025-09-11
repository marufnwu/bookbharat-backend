@extends('emails.layout.app')

@section('title', 'Order Delivered - #' . $order->order_number)

@section('header_subtitle', 'Order Delivered Successfully')

@section('content')
    <h2>Hi {{ $user->name }},</h2>
    
    <p>🎉 Great news! Your order has been delivered successfully.</p>
    
    <div class="highlight">
        <h3>📦 Delivery Confirmation</h3>
        <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
        <p><strong>Delivered On:</strong> {{ $order->updated_at->format('F j, Y \a\t g:i A') }}</p>
        @if($order->tracking_number)
            <p><strong>Tracking Number:</strong> {{ $order->tracking_number }}</p>
        @endif
    </div>

    <h3>Your Order Items</h3>
    <div class="order-summary">
        @foreach($items as $item)
            <div class="item-row">
                <div>
                    <strong>{{ $item->product->name }}</strong><br>
                    <small>Quantity: {{ $item->quantity }}</small>
                    @if($item->product_variant_id)
                        <br><small>Variant: {{ $item->productVariant->formatted_attributes ?? '' }}</small>
                    @endif
                </div>
                <div class="text-right">
                    ₹{{ number_format($item->total_price, 2) }}
                </div>
            </div>
        @endforeach
    </div>

    <h3>What's Next?</h3>
    <div class="highlight">
        <h4>📝 Share Your Experience</h4>
        <p>We'd love to hear about your experience! Your reviews help other customers make informed decisions.</p>
        
        <div class="text-center">
            <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}/review" class="button">
                Write a Review
            </a>
        </div>
    </div>

    <h3>Need Help?</h3>
    <ul>
        <li><strong>Missing items or damage?</strong> Contact us within 48 hours of delivery</li>
        <li><strong>Need to return something?</strong> You have 30 days to initiate a return</li>
        <li><strong>Questions about your order?</strong> Our support team is here to help</li>
    </ul>

    <div class="text-center">
        <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}" class="button">
            View Order Details
        </a>
    </div>

    <h3>🏷️ Special Offers for You</h3>
    <div class="highlight">
        <p><strong>Thank you for your purchase!</strong></p>
        <p>As a valued customer, enjoy <strong>10% off</strong> your next order with code: <strong>THANKYOU10</strong></p>
        <p><small>Valid for 30 days on orders over ₹500</small></p>
    </div>

    <p>Thank you for choosing {{ config('app.name') }}! We hope you love your purchase and look forward to serving you again.</p>

    <p>Happy Reading!<br>
    The {{ config('app.name') }} Team</p>
@endsection