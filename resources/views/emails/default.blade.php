@extends('emails.layout.app')

@section('content')
<tr>
    <td style="padding: 40px 30px;">
        <h1 style="margin: 0 0 20px 0; font-size: 24px; font-weight: 600; color: #1a1a1a;">
            {{ $subject ?? 'Notification from BookBharat' }}
        </h1>
        
        @if(isset($name))
        <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 24px; color: #4a5568;">
            Hi {{ $name }},
        </p>
        @endif
        
        @if(isset($message))
        <div style="margin: 0 0 20px 0; font-size: 16px; line-height: 24px; color: #4a5568;">
            {!! nl2br(e($message)) !!}
        </div>
        @endif

        @if(isset($user_message))
        <div style="background-color: #f7fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h3 style="margin: 0 0 10px 0; font-size: 16px; font-weight: 600; color: #2d3748;">
                Message:
            </h3>
            <p style="margin: 0; font-size: 14px; line-height: 20px; color: #4a5568; white-space: pre-wrap;">{{ $user_message }}</p>
        </div>
        @endif

        @if(isset($contact_data))
        <div style="background-color: #f7fafc; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h3 style="margin: 0 0 15px 0; font-size: 16px; font-weight: 600; color: #2d3748;">
                Contact Details:
            </h3>
            @if(isset($contact_data['name']))
            <p style="margin: 0 0 8px 0; font-size: 14px; color: #4a5568;">
                <strong>Name:</strong> {{ $contact_data['name'] }}
            </p>
            @endif
            @if(isset($contact_data['email']) || isset($email))
            <p style="margin: 0 0 8px 0; font-size: 14px; color: #4a5568;">
                <strong>Email:</strong> {{ $contact_data['email'] ?? $email }}
            </p>
            @endif
            @if(isset($contact_data['phone']))
            <p style="margin: 0 0 8px 0; font-size: 14px; color: #4a5568;">
                <strong>Phone:</strong> {{ $contact_data['phone'] }}
            </p>
            @endif
            @if(isset($contact_data['subject']))
            <p style="margin: 0 0 8px 0; font-size: 14px; color: #4a5568;">
                <strong>Subject:</strong> {{ $contact_data['subject'] }}
            </p>
            @endif
        </div>
        @endif

        @if(isset($action_url) && isset($action_text))
        <!-- CTA Button -->
        <table cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
            <tr>
                <td style="border-radius: 6px; background-color: #3182ce;">
                    <a href="{{ $action_url }}" 
                       style="display: inline-block; padding: 14px 40px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600;">
                        {{ $action_text }}
                    </a>
                </td>
            </tr>
        </table>
        @endif

        @if(isset($is_confirmation) && $is_confirmation)
        <p style="margin: 20px 0 0 0; font-size: 14px; line-height: 20px; color: #718096;">
            Best regards,<br>
            The BookBharat Team
        </p>
        @endif
    </td>
</tr>
@endsection

