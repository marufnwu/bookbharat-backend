@extends('emails.layout.app')

@section('title', 'Invoice - #' . $invoice->formatted_invoice_number)

@section('header_subtitle', 'Tax Invoice')

@section('content')
    <h2>Hi {{ $user->name }},</h2>
    
    <p>Please find your invoice for Order #{{ $order->order_number }} attached to this email.</p>
    
    <div class="highlight">
        <h3>Invoice Details</h3>
        <p><strong>Invoice Number:</strong> {{ $invoice->formatted_invoice_number }}</p>
        <p><strong>Invoice Date:</strong> {{ $invoice->invoice_date->format('F j, Y') }}</p>
        <p><strong>Due Date:</strong> {{ $invoice->due_date->format('F j, Y') }}</p>
        <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
        <p><strong>Invoice Status:</strong> 
            <span class="status-badge status-{{ $invoice->status === 'paid' ? 'confirmed' : 'pending' }}">
                {{ ucfirst($invoice->status) }}
            </span>
        </p>
    </div>

    <h3>Invoice Summary</h3>
    <div class="order-summary">
        @foreach($items as $item)
            <div class="item-row">
                <div>
                    <strong>{{ $item->product_name }}</strong><br>
                    <small>{{ $item->product_sku }} | Qty: {{ $item->quantity }} × ₹{{ number_format($item->unit_price, 2) }}</small>
                </div>
                <div class="text-right">
                    ₹{{ number_format($item->total_price, 2) }}
                </div>
            </div>
        @endforeach

        <div class="item-row">
            <div>Subtotal</div>
            <div class="text-right">₹{{ number_format($invoice->subtotal, 2) }}</div>
        </div>

        @if($invoice->discount_amount > 0)
            <div class="item-row">
                <div>Discount</div>
                <div class="text-right">-₹{{ number_format($invoice->discount_amount, 2) }}</div>
            </div>
        @endif

        @if($invoice->shipping_amount > 0)
            <div class="item-row">
                <div>Shipping</div>
                <div class="text-right">₹{{ number_format($invoice->shipping_amount, 2) }}</div>
            </div>
        @endif

        @if($invoice->tax_amount > 0)
            <div class="item-row">
                <div>Tax</div>
                <div class="text-right">₹{{ number_format($invoice->tax_amount, 2) }}</div>
            </div>
        @endif

        <div class="item-row total-row">
            <div><strong>Total Amount</strong></div>
            <div class="text-right"><strong>₹{{ number_format($invoice->total_amount, 2) }}</strong></div>
        </div>
    </div>

    @if($invoice->status === 'pending')
        <div class="highlight">
            <h3>Payment Due</h3>
            <p><strong>Amount Due:</strong> ₹{{ number_format($invoice->total_amount, 2) }}</p>
            <p><strong>Due Date:</strong> {{ $invoice->due_date->format('F j, Y') }}</p>
            @if($invoice->due_date->isPast())
                <p style="color: #dc2626;"><strong>This invoice is overdue. Please make payment as soon as possible.</strong></p>
            @endif
        </div>
    @else
        <div class="highlight">
            <h3>Payment Received</h3>
            <p>Thank you! This invoice has been paid in full.</p>
        </div>
    @endif

    <div class="text-center">
        <a href="{{ config('app.frontend_url') }}/invoices/{{ $invoice->id }}" class="button">
            View Invoice Online
        </a>
    </div>

    @if($invoice->notes)
        <h3>Notes</h3>
        <p>{{ $invoice->notes }}</p>
    @endif

    <p>This invoice has been generated automatically. If you have any questions regarding this invoice, please contact our billing department.</p>

    <p><small><strong>Important:</strong> This is a computer-generated invoice and does not require a signature.</small></p>
@endsection