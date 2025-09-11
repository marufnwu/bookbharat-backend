<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - {{ $order->order_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 28px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .invoice-info div {
            width: 48%;
        }
        .invoice-info h3 {
            color: #007bff;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .order-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .order-details h2 {
            color: #007bff;
            margin-top: 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .items-table th {
            background-color: #007bff;
            color: white;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .total-section {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        .total-row.final {
            font-weight: bold;
            font-size: 18px;
            color: #007bff;
            border-bottom: 2px solid #007bff;
        }
        .footer {
            clear: both;
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .address {
            line-height: 1.4;
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

    <div class="order-details">
        <h2>Invoice</h2>
        <div style="display: flex; justify-content: space-between;">
            <div>
                <strong>Invoice Number:</strong> {{ $order->order_number }}<br>
                <strong>Order Date:</strong> {{ $order->created_at->format('d M Y, H:i A') }}<br>
                <strong>Payment Status:</strong> 
                <span style="color: {{ $order->payment_status === 'paid' ? '#28a745' : '#dc3545' }}">
                    {{ ucfirst($order->payment_status) }}
                </span>
            </div>
            <div>
                <strong>Payment Method:</strong> {{ strtoupper($order->payment_method) }}<br>
                @if($order->payment_transaction_id)
                <strong>Transaction ID:</strong> {{ $order->payment_transaction_id }}<br>
                @endif
                <strong>Status:</strong> {{ ucfirst($order->status) }}
            </div>
        </div>
    </div>

    <div class="invoice-info">
        <div>
            <h3>Bill To:</h3>
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
                        {{ $billing['city'] ?? '' }}, {{ $billing['state'] ?? '' }} - {{ $billing['postal_code'] ?? $billing['pincode'] ?? '' }}<br>
                        {{ $billing['country'] ?? '' }}<br>
                        @if(!empty($billing['phone']))
                            Phone: {{ $billing['phone'] }}
                        @endif
                    @endif
                @else
                    {{ $order->user->name ?? 'N/A' }}<br>
                    {{ $order->user->email ?? 'N/A' }}
                @endif
            </div>
        </div>
        <div>
            <h3>Ship To:</h3>
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
                        {{ $shipping['city'] ?? '' }}, {{ $shipping['state'] ?? '' }} - {{ $shipping['postal_code'] ?? $shipping['pincode'] ?? '' }}<br>
                        {{ $shipping['country'] ?? '' }}<br>
                        @if(!empty($shipping['phone']))
                            Phone: {{ $shipping['phone'] }}
                        @endif
                    @endif
                @else
                    Same as Billing Address
                @endif
            </div>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th>Unit Price</th>
                <th>Quantity</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->orderItems as $item)
            <tr>
                <td>
                    <strong>{{ $item->product_name }}</strong><br>
                    @if($item->product && $item->product->metadata)
                        @php
                            $metadata = is_string($item->product->metadata) 
                                ? json_decode($item->product->metadata, true) 
                                : $item->product->metadata;
                        @endphp
                        @if(isset($metadata['author']))
                            <small>by {{ $metadata['author'] }}</small><br>
                        @endif
                        @if(isset($metadata['isbn']))
                            <small>ISBN: {{ $metadata['isbn'] }}</small>
                        @endif
                    @endif
                </td>
                <td>{{ $item->product_sku }}</td>
                <td>₹{{ number_format($item->unit_price, 2) }}</td>
                <td>{{ $item->quantity }}</td>
                <td>₹{{ number_format($item->total_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>₹{{ number_format($order->subtotal, 2) }}</span>
        </div>
        
        @if($order->discount_amount > 0)
        <div class="total-row">
            <span>Discount:</span>
            <span style="color: #28a745;">-₹{{ number_format($order->discount_amount, 2) }}</span>
        </div>
        @endif
        
        <div class="total-row">
            <span>Shipping:</span>
            <span>
                @if($order->shipping_amount == 0)
                    FREE
                @else
                    ₹{{ number_format($order->shipping_amount, 2) }}
                @endif
            </span>
        </div>
        
        <div class="total-row">
            <span>Tax (GST):</span>
            <span>₹{{ number_format($order->tax_amount, 2) }}</span>
        </div>
        
        <div class="total-row final">
            <span>Total Amount:</span>
            <span>₹{{ number_format($order->total_amount, 2) }}</span>
        </div>
    </div>

    <div class="footer">
        <p>Thank you for shopping with BookBharat!</p>
        <p>This is a computer-generated invoice and does not require a signature.</p>
        <p>For any queries regarding this invoice, please contact us at support@bookbharat.com</p>
    </div>
</body>
</html>