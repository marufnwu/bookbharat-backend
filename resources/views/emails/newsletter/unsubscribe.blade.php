@extends('emails.layout.app')

@section('content')
<tr>
    <td style="padding: 40px 30px;">
        <h1 style="margin: 0 0 20px 0; font-size: 24px; font-weight: 600; color: #1a1a1a;">
            You've Been Unsubscribed
        </h1>
        
        <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 24px; color: #4a5568;">
            Hi {{ $name ?? 'Book Lover' }},
        </p>
        
        <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 24px; color: #4a5568;">
            You have been successfully unsubscribed from the BookBharat newsletter. We're sorry to see you go!
        </p>

        <div style="background-color: #fef5e7; border-left: 4px solid: #f59e0b; padding: 20px; margin: 20px 0;">
            <p style="margin: 0; font-size: 14px; line-height: 20px; color: #92400e;">
                ℹ️ You will no longer receive promotional emails from us, but you'll still receive important transactional emails about your orders.
            </p>
        </div>

        <p style="margin: 20px 0; font-size: 16px; line-height: 24px; color: #4a5568;">
            If you unsubscribed by mistake or change your mind, you can always resubscribe from our website.
        </p>

        <!-- CTA Button -->
        <table cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
            <tr>
                <td style="border-radius: 6px; background-color: #3182ce;">
                    <a href="{{ config('app.frontend_url') }}?resubscribe=1" 
                       style="display: inline-block; padding: 14px 40px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600;">
                        Resubscribe to Newsletter
                    </a>
                </td>
            </tr>
        </table>

        <p style="margin: 20px 0 0 0; font-size: 14px; line-height: 20px; color: #718096;">
            Thank you for being part of our community!<br>
            The BookBharat Team
        </p>
    </td>
</tr>
@endsection

