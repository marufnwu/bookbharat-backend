@extends('emails.layout.app')

@section('content')
<tr>
    <td style="padding: 40px 30px;">
        @if($email_type === 'first_reminder')
        <h1 style="margin: 0 0 20px 0; font-size: 24px; font-weight: 600; color: #1a1a1a;">
            You left something in your cart 🛒
        </h1>
        @elseif($email_type === 'second_reminder')
        <h1 style="margin: 0 0 20px 0; font-size: 24px; font-weight: 600; color: #1a1a1a;">
            Complete your order & save 5%! 🎉
        </h1>
        @else
        <h1 style="margin: 0 0 20px 0; font-size: 24px; font-weight: 600; color: #1a1a1a;">
            Last chance to save 10%! ⏰
        </h1>
        @endif
        
        <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 24px; color: #4a5568;">
            Hi {{ $user->name }},
        </p>
        
        <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 24px; color: #4a5568;">
            @if($email_type === 'first_reminder')
            We noticed you left some great books in your cart. Don't let them get away!
            @elseif($email_type === 'second_reminder')
            Your cart is still waiting! Complete your order now and get <strong>5% off</strong> as a special thank you.
            @else
            This is your final reminder! Complete your order in the next 24 hours and get <strong>10% off</strong> your entire cart!
            @endif
        </p>

        @if($has_discount)
        <div style="background-color: #d4edda; border: 2px dashed #28a745; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;">
            <div style="font-size: 28px; font-weight: 700; color: #155724; margin-bottom: 8px;">
                SAVE {{ $discount['value'] }}%
            </div>
            <div style="font-size: 14px; color: #155724;">
                Use this exclusive discount when you complete your order!
            </div>
        </div>
        @endif

        <!-- Cart Items -->
        <div style="background-color: #f7fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h3 style="margin: 0 0 15px 0; font-size: 18px; font-weight: 600; color: #2d3748;">
                Items in your cart:
            </h3>
            @if(is_array($cart_items))
            @foreach($cart_items as $item)
            <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; color: #2d3748; margin-bottom: 5px;">
                        {{ $item['product_name'] ?? $item['name'] ?? 'Product' }}
                    </div>
                    <div style="font-size: 14px; color: #718096;">
                        Quantity: {{ $item['quantity'] ?? 1 }}
                        @if(isset($item['price']))
                        | ₹{{ number_format($item['price'], 2) }}
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
            @endif
        </div>

        <!-- CTA Button -->
        <table cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
            <tr>
                <td style="border-radius: 6px; background-color: #f59e0b;">
                    <a href="{{ $recovery_url }}" 
                       style="display: inline-block; padding: 16px 50px; color: #ffffff; text-decoration: none; font-size: 18px; font-weight: 700;">
                        @if($has_discount)
                        Complete Order & Save {{ $discount['value'] }}%
                        @else
                        Complete Your Order
                        @endif
                    </a>
                </td>
            </tr>
        </table>

        @if($email_type === 'final_reminder')
        <div style="background-color: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;">
            <p style="margin: 0; font-size: 14px; color: #991b1b; font-weight: 600;">
                ⚠️ This is your last reminder! The 10% discount expires in 24 hours.
            </p>
        </div>
        @endif

        <p style="margin: 20px 0 0 0; font-size: 14px; line-height: 20px; color: #718096;">
            Need help? Contact us at {{ config('mail.from.address') }}<br>
            The BookBharat Team
        </p>
    </td>
</tr>
@endsection

