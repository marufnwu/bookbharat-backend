@extends('emails.layout.app')

@section('content')
<tr>
    <td style="padding: 40px 30px;">
        <h1 style="margin: 0 0 20px 0; font-size: 24px; font-weight: 600; color: #1a1a1a;">
            How was your order?
        </h1>

        <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 24px; color: #4a5568;">
            Hi {{ $user->name }},
        </p>

        <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 24px; color: #4a5568;">
            We hope you're enjoying your recent purchase from BookBharat! We'd love to hear about your experience with order <strong>#{{ $order->order_number }}</strong>.
        </p>

        <!-- Order Items -->
        <div style="background-color: #f7fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h3 style="margin: 0 0 15px 0; font-size: 18px; font-weight: 600; color: #2d3748;">
                Items in your order:
            </h3>
            @foreach($items as $item)
            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0;">
                <div style="font-weight: 600; color: #2d3748; margin-bottom: 5px;">
                    {{ $item->product_name }}
                </div>
                <div style="font-size: 14px; color: #718096;">
                    Quantity: {{ $item->quantity }} | Price: ₹{{ number_format($item->unit_price, 2) }}
                </div>
            </div>
            @endforeach
        </div>

        <p style="margin: 20px 0; font-size: 16px; line-height: 24px; color: #4a5568;">
            Your feedback helps us improve and helps other book lovers make informed decisions.
        </p>

        <!-- CTA Button -->
        <table cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
            <tr>
                <td style="border-radius: 6px; background-color: #3182ce;">
                    <a href="{{ $review_url }}"
                       style="display: inline-block; padding: 14px 40px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600;">
                        Write a Review
                    </a>
                </td>
            </tr>
        </table>

        <p style="margin: 20px 0 0 0; font-size: 14px; line-height: 20px; color: #718096;">
            Thank you for being a valued BookBharat customer!
        </p>
    </td>
</tr>
@endsection

