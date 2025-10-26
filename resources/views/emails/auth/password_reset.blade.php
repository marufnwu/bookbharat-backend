@extends('emails.layout.app')

@section('title', 'Reset Password - ' . config('app.name'))

@section('header_subtitle', 'Password Reset Request')

@section('content')
    <h2>Hi {{ $user->name }},</h2>

    <p>We received a request to reset your password for your {{ config('app.name') }} account.</p>

    <div class="highlight">
        <h3>🔐 Reset Your Password</h3>
        <p>Click the button below to reset your password. This link will expire in 60 minutes for security reasons.</p>
    </div>

    <div class="text-center">
        <a href="{{ $reset_url }}" class="button">
            Reset My Password
        </a>
    </div>

    <p><strong>If the button above doesn't work, copy and paste this link into your browser:</strong></p>
    <p style="word-break: break-all; background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace;">
        {{ $reset_url }}
    </p>

    <div class="highlight" style="background-color: #fef2f2; border: 1px solid #fecaca;">
        <h3>⚠️ Important Security Information</h3>
        <ul>
            <li>This password reset link expires in <strong>60 minutes</strong></li>
            <li>If you didn't request this reset, please ignore this email</li>
            <li>Your current password remains unchanged until you complete the reset process</li>
            <li>For security, this link can only be used once</li>
        </ul>
    </div>

    <h3>Didn't request this reset?</h3>
    <p>If you didn't request a password reset, your account is still secure. You can safely ignore this email, and your password won't be changed.</p>

    <p>However, if you're concerned about your account security, please:</p>
    <ul>
        <li>Contact our support team immediately</li>
        <li>Review your recent account activity</li>
        <li>Consider updating your password regularly</li>
    </ul>

    <h3>Need Help?</h3>
    <p>If you're having trouble resetting your password or have any security concerns, please contact our support team:</p>
    <ul>
        <li>📧 Email: <a href="mailto:support@bookbharat.com">support@bookbharat.com</a></li>
        <li>📞 Phone: +91-XXXX-XXXXXX</li>
    </ul>

    <p>Thanks,<br>
    The {{ config('app.name') }} Security Team</p>
@endsection
