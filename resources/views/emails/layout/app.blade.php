<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 0;
            background-color: #f5f5f5;
        }
        .email-container {
            background-color: #ffffff;
            margin: 20px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #2563eb;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: white !important;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
        }
        .button:hover {
            background-color: #1d4ed8;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }
        .order-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .item-row:last-child {
            border-bottom: none;
        }
        .total-row {
            font-weight: bold;
            font-size: 16px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #2563eb;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending { background-color: #fbbf24; color: #92400e; }
        .status-confirmed { background-color: #34d399; color: #065f46; }
        .status-shipped { background-color: #60a5fa; color: #1e3a8a; }
        .status-delivered { background-color: #10b981; color: #064e3b; }
        .status-cancelled { background-color: #f87171; color: #991b1b; }
        .highlight {
            background-color: #fef3c7;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mb-0 { margin-bottom: 0; }
        .mt-0 { margin-top: 0; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>{{ config('app.name', 'BookBharat') }}</h1>
            @hasSection('header_subtitle')
                <p style="margin: 10px 0 0 0; opacity: 0.9;">@yield('header_subtitle')</p>
            @endif
        </div>

        <div class="content">
            @yield('content')
        </div>

        <div class="footer">
            <p class="mb-0">Thank you for choosing {{ config('app.name') }}!</p>
            <p class="mb-0">
                If you have any questions, contact us at 
                <a href="mailto:support@bookbharat.com">support@bookbharat.com</a>
            </p>
            <p class="mb-0" style="margin-top: 15px;">
                <a href="{{ config('app.url') }}">Visit our website</a> | 
                <a href="{{ config('app.url') }}/contact">Contact Support</a>
            </p>
        </div>
    </div>
</body>
</html>