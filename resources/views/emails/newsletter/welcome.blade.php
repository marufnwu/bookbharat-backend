@extends('emails.layout.app')

@section('content')
<tr>
    <td style="padding: 40px 30px;">
        <h1 style="margin: 0 0 20px 0; font-size: 24px; font-weight: 600; color: #1a1a1a;">
            Welcome to BookBharat Newsletter! 📚
        </h1>
        
        <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 24px; color: #4a5568;">
            Hi {{ $name ?? 'Book Lover' }},
        </p>
        
        <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 24px; color: #4a5568;">
            Thank you for subscribing to the BookBharat newsletter! You're now part of our community of passionate readers.
        </p>

        <div style="background-color: #edf2f7; border-left: 4px solid #3182ce; padding: 20px; margin: 20px 0;">
            <h3 style="margin: 0 0 10px 0; font-size: 18px; font-weight: 600; color: #2d3748;">
                Here's what you can expect:
            </h3>
            <ul style="margin: 0; padding-left: 20px; color: #4a5568;">
                <li style="margin-bottom: 8px;">📖 New book releases and recommendations</li>
                <li style="margin-bottom: 8px;">🎁 Exclusive deals and early access to sales</li>
                <li style="margin-bottom: 8px;">✍️ Author interviews and literary news</li>
                <li style="margin-bottom: 8px;">📚 Reading lists curated just for you</li>
            </ul>
        </div>

        <p style="margin: 20px 0; font-size: 16px; line-height: 24px; color: #4a5568;">
            We promise to only send you content that book lovers like you will enjoy. No spam, ever!
        </p>

        <!-- CTA Button -->
        <table cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
            <tr>
                <td style="border-radius: 6px; background-color: #3182ce;">
                    <a href="{{ config('app.frontend_url') }}/products" 
                       style="display: inline-block; padding: 14px 40px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600;">
                        Start Shopping
                    </a>
                </td>
            </tr>
        </table>

        <p style="margin: 20px 0 0 0; font-size: 14px; line-height: 20px; color: #718096;">
            Happy reading!<br>
            The BookBharat Team
        </p>

        <p style="margin: 20px 0 0 0; font-size: 12px; line-height: 18px; color: #a0aec0;">
            You can <a href="{{ config('app.frontend_url') }}/newsletter/unsubscribe?email={{ $email ?? '' }}" style="color: #3182ce; text-decoration: underline;">unsubscribe</a> at any time.
        </p>
    </td>
</tr>
@endsection

