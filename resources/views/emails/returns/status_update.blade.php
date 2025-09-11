@extends('emails.layout.app')

@section('title', 'Return Update - #' . $return->return_number)

@section('header_subtitle', 'Return Status Update')

@section('content')
    <h2>Hi {{ $user->name }},</h2>
    
    <p>We wanted to update you on the status of your return request.</p>
    
    <div class="highlight">
        <strong>Return #{{ $return->return_number }}</strong><br>
        <strong>Order #{{ $order->order_number }}</strong><br>
        Status Updated: <span class="status-badge status-{{ $new_status === 'completed' ? 'confirmed' : ($new_status === 'rejected' ? 'cancelled' : 'pending') }}">{{ ucfirst($new_status) }}</span><br>
        <small>Previous status: {{ ucfirst($old_status) }}</small>
    </div>

    <p><strong>{{ $status_message }}</strong></p>

    @if($new_status === 'approved')
        <div class="highlight">
            <h3>Return Approved!</h3>
            <p>Your return request has been approved. Please follow these steps:</p>
            <ol>
                <li>Package the items in their original condition with all tags and accessories</li>
                <li>Use the return shipping label we'll send you separately</li>
                <li>Drop off the package at any authorized shipping location</li>
            </ol>
            <p><strong>Refund Amount:</strong> ₹{{ number_format($return->refund_amount, 2) }}</p>
        </div>
    @elseif($new_status === 'rejected')
        <div class="highlight">
            <h3>Return Request Rejected</h3>
            <p>Unfortunately, we cannot process your return request at this time.</p>
            @if($return->admin_notes)
                <p><strong>Reason:</strong> {{ $return->admin_notes }}</p>
            @endif
            <p>If you have questions about this decision, please contact our customer service team.</p>
        </div>
    @elseif($new_status === 'received')
        <div class="highlight">
            <h3>Items Received</h3>
            <p>We have received your returned items and are currently inspecting them. We'll process your refund within 3-5 business days once the inspection is complete.</p>
        </div>
    @elseif($new_status === 'processed')
        <div class="highlight">
            <h3>Refund Processed</h3>
            <p>Great news! Your refund has been processed.</p>
            <p><strong>Refund Amount:</strong> ₹{{ number_format($return->refund_amount, 2) }}</p>
            <p><strong>Refund Method:</strong> {{ ucfirst($return->refund_method ?? 'Original Payment Method') }}</p>
            <p>You should see the refund in your account within 3-5 business days.</p>
        </div>
    @elseif($new_status === 'completed')
        <div class="highlight">
            <h3>Return Completed</h3>
            <p>Your return has been completed successfully!</p>
            <p><strong>Refund Amount:</strong> ₹{{ number_format($return->refund_amount, 2) }}</p>
            <p>Thank you for your patience throughout this process.</p>
        </div>
    @endif

    <h3>Return Details</h3>
    <div class="order-summary">
        <div class="item-row">
            <div>Return Number</div>
            <div class="text-right">{{ $return->return_number }}</div>
        </div>
        <div class="item-row">
            <div>Return Type</div>
            <div class="text-right">{{ ucfirst($return->return_type) }}</div>
        </div>
        <div class="item-row">
            <div>Return Reason</div>
            <div class="text-right">{{ $return->reason_label }}</div>
        </div>
        <div class="item-row">
            <div>Requested Date</div>
            <div class="text-right">{{ $return->requested_at->format('F j, Y') }}</div>
        </div>
        @if($return->refund_amount > 0)
            <div class="item-row total-row">
                <div><strong>Refund Amount</strong></div>
                <div class="text-right"><strong>₹{{ number_format($return->refund_amount, 2) }}</strong></div>
            </div>
        @endif
    </div>

    @if($return->return_tracking_number)
        <h3>Tracking Information</h3>
        <p>
            <strong>Tracking Number:</strong> {{ $return->return_tracking_number }}<br>
            @if($return->return_shipping_method)
                <strong>Shipping Method:</strong> {{ $return->return_shipping_method }}
            @endif
        </p>
    @endif

    <div class="text-center">
        <a href="{{ config('app.frontend_url') }}/returns/{{ $return->id }}" class="button">
            View Return Details
        </a>
    </div>

    @if($return->admin_notes && $new_status !== 'rejected')
        <p><strong>Additional Notes:</strong> {{ $return->admin_notes }}</p>
    @endif

    <p>If you have any questions about your return, please contact our customer service team with your return number.</p>
@endsection