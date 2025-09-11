<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $order->order_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            background-color: #fff;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #28a745;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #28a745;
            margin: 0;
            font-size: 28px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .receipt-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #28a745;
        }
        .receipt-info h2 {
            color: #28a745;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        .info-value {
            color: #212529;
        }
        .payment-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .payment-status.paid {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .payment-status.pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .payment-status.failed {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .items-section {
            margin-bottom: 30px;
        }
        .items-section h3 {
            color: #28a745;
            border-bottom: 2px solid #28a745;
            padding-bottom: 8px;
            margin-bottom: 20px;
        }
        .item-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #ffffff;
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        .item-title {
            font-size: 16px;
            font-weight: bold;
            color: #212529;
            flex: 1;
        }
        .item-price {
            font-size: 16px;
            font-weight: bold;
            color: #28a745;
        }
        .item-details {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.4;
        }
        .item-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 14px;
        }
        .summary-box {
            background-color: #e8f5e8;
            border: 1px solid #28a745;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #c3e6cb;
        }
        .summary-row:last-child {
            border-bottom: none;
        }
        .summary-row.total {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
            border-top: 2px solid #28a745;
            padding-top: 15px;
            margin-top: 10px;
        }
        .customer-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 30px 0;
        }
        .customer-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        .customer-section h4 {
            color: #28a745;
            margin-top: 0;
            margin-bottom: 10px;
        }
        .address {
            line-height: 1.6;
            color: #495057;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
        }
        .thank-you {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>BookBharat</h1>
        <p>Your Premier Online Bookstore</p>
        <p>Email: info@bookbharat.com | Phone: +91-XXXX-XXXXXX</p>
        <p>Website: www.bookbharat.com</p>
    </div>

    <div class="receipt-info">
        <h2>Payment Receipt</h2>
        <div class="info-grid">
            <div>
                <div class="info-item">
                    <span class="info-label">Receipt Number:</span>
                    <span class="info-value">{{ $order->order_number }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Order Date:</span>
                    <span class="info-value">{{ $order->created_at->format('d M Y, H:i A') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Payment Method:</span>
                    <span class="info-value">{{ strtoupper($order->payment_method) }}</span>
                </div>
            </div>
            <div>
                <div class="info-item">
                    <span class="info-label">Payment Status:</span>
                    <span class="info-value">
                        <span class="payment-status {{ $order->payment_status }}">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                    </span>
                </div>
                @if($order->payment_transaction_id)
                <div class="info-item">
                    <span class="info-label">Transaction ID:</span>
                    <span class="info-value">{{ $order->payment_transaction_id }}</span>
                </div>
                @endif
                <div class="info-item">
                    <span class="info-label">Order Status:</span>
                    <span class="info-value">{{ ucfirst($order->status) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="thank-you">
        <h3 style="margin: 0; color: #155724;">Thank you for your payment!</h3>
        <p style="margin: 5px 0 0 0;">Your order has been confirmed and is being processed.</p>
    </div>

    <div class="items-section">
        <h3>Items Purchased</h3>
        @foreach($order->orderItems as $item)
        <div class="item-card">
            <div class="item-header">
                <div class="item-title">{{ $item->product_name }}</div>
                <div class="item-price">₹{{ number_format($item->total_price, 2) }}</div>
            </div>
            
            @if($item->product && $item->product->metadata)
                @php
                    $metadata = is_string($item->product->metadata) 
                        ? json_decode($item->product->metadata, true) 
                        : $item->product->metadata;
                @endphp
                <div class="item-details">
                    @if(isset($metadata['author']))
                        <strong>Author:</strong> {{ $metadata['author'] }}<br>
                    @endif
                    @if(isset($metadata['isbn']))
                        <strong>ISBN:</strong> {{ $metadata['isbn'] }}<br>
                    @endif
                    @if(isset($metadata['publisher']))
                        <strong>Publisher:</strong> {{ $metadata['publisher'] }}
                    @endif
                </div>
            @endif
            
            <div class="item-meta">
                <span><strong>SKU:</strong> {{ $item->product_sku }}</span>
                <span><strong>Qty:</strong> {{ $item->quantity }} × ₹{{ number_format($item->unit_price, 2) }}</span>
            </div>
        </div>
        @endforeach
    </div>

    <div class="customer-info">
        <div class="customer-section">
            <h4>Billing Information</h4>
            <div class="address">
                @if($order->billing_address)
                    @php
                        $billing = is_string($order->billing_address) ? json_decode($order->billing_address, true) : $order->billing_address;
                    @endphp
                    @if(is_array($billing))
                        {{ $billing['first_name'] ?? '' }} {{ $billing['last_name'] ?? '' }}<br>
                        {{ $billing['address_line_1'] ?? '' }}<br>
                        @if(!empty($billing['address_line_2']))
                            {{ $billing['address_line_2'] }}<br>
                        @endif
                        {{ $billing['city'] ?? '' }}, {{ $billing['state'] ?? '' }}<br>
                        {{ $billing['postal_code'] ?? $billing['pincode'] ?? '' }}, {{ $billing['country'] ?? '' }}<br>
                        @if(!empty($billing['phone']))
                            <strong>Phone:</strong> {{ $billing['phone'] }}
                        @endif
                    @endif
                @else
                    {{ $order->user->name ?? 'N/A' }}<br>
                    {{ $order->user->email ?? 'N/A' }}
                @endif
            </div>
        </div>

        <div class="customer-section">
            <h4>Shipping Information</h4>
            <div class="address">
                @if($order->shipping_address)
                    @php
                        $shipping = is_string($order->shipping_address) ? json_decode($order->shipping_address, true) : $order->shipping_address;
                    @endphp
                    @if(is_array($shipping))
                        {{ $shipping['first_name'] ?? '' }} {{ $shipping['last_name'] ?? '' }}<br>
                        {{ $shipping['address_line_1'] ?? '' }}<br>
                        @if(!empty($shipping['address_line_2']))
                            {{ $shipping['address_line_2'] }}<br>
                        @endif
                        {{ $shipping['city'] ?? '' }}, {{ $shipping['state'] ?? '' }}<br>
                        {{ $shipping['postal_code'] ?? $shipping['pincode'] ?? '' }}, {{ $shipping['country'] ?? '' }}<br>
                        @if(!empty($shipping['phone']))
                            <strong>Phone:</strong> {{ $shipping['phone'] }}
                        @endif
                    @endif
                @else
                    Same as Billing Address
                @endif
            </div>
        </div>
    </div>

    <div class="summary-box">
        <div class="summary-row">
            <span>Subtotal:</span>
            <span>₹{{ number_format($order->subtotal, 2) }}</span>
        </div>
        
        @if($order->discount_amount > 0)
        <div class="summary-row">
            <span>Discount Applied:</span>
            <span style="color: #28a745;">-₹{{ number_format($order->discount_amount, 2) }}</span>
        </div>
        @endif
        
        <div class="summary-row">
            <span>Shipping Charges:</span>
            <span>
                @if($order->shipping_amount == 0)
                    <span style="color: #28a745; font-weight: bold;">FREE</span>
                @else
                    ₹{{ number_format($order->shipping_amount, 2) }}
                @endif
            </span>
        </div>
        
        <div class="summary-row">
            <span>Tax (GST):</span>
            <span>₹{{ number_format($order->tax_amount, 2) }}</span>
        </div>
        
        <div class="summary-row total">
            <span>Total Amount Paid:</span>
            <span>₹{{ number_format($order->total_amount, 2) }}</span>
        </div>
    </div>

    <div class="footer">
        <p><strong>Thank you for choosing BookBharat!</strong></p>
        <p>This is a computer-generated receipt for your records.</p>
        <p>For any queries regarding this receipt or your order, please contact us at support@bookbharat.com</p>
        <p>Keep reading, keep growing! 📚</p>
    </div>
</body>
</html>