@extends('emails.layout.app')

@section('title', 'Welcome to ' . config('app.name'))

@section('header_subtitle', 'Welcome to our community!')

@section('content')
    <h2>Hi {{ $user->name }},</h2>

    <p>Welcome to {{ config('app.name') }}! We're excited to have you join our community of book lovers.</p>

    <div class="highlight">
        <h3>🎉 Your account is ready!</h3>
        <p><strong>Email:</strong> {{ $user->email }}</p>
        <p><strong>Account Created:</strong> {{ $user->created_at->format('F j, Y') }}</p>
    </div>

    <h3>What's next?</h3>
    <ul>
        <li><strong>Explore our collection:</strong> Browse thousands of books across all genres</li>
        <li><strong>Create your wishlist:</strong> Save books you want to read for later</li>
        <li><strong>Set up your profile:</strong> Add your reading preferences and shipping addresses</li>
        <li><strong>Join our community:</strong> Write reviews and discover new favorites</li>
    </ul>

    <div class="text-center">
        <a href="{{ config('app.frontend_url') }}/books" class="button">
            Start Browsing Books
        </a>
    </div>

    <h3>Special Welcome Offer! 🎁</h3>
    <div class="highlight">
        <p><strong>Get 15% off your first order</strong></p>
        <p>Use code: <strong>WELCOME15</strong></p>
        <p><small>Valid for orders over ₹500. Expires in 30 days.</small></p>
    </div>

    <h3>Need Help?</h3>
    <p>Our customer service team is here to help you get started:</p>
    <ul>
        <li>📧 Email: <a href="mailto:support@bookbharat.com">support@bookbharat.com</a></li>
        <li>📞 Phone: +91-XXXX-XXXXXX (Mon-Fri, 9 AM - 6 PM)</li>
        <li>💬 Live Chat: Available on our website</li>
    </ul>

    <p>Thank you for choosing {{ config('app.name') }}. We look forward to being part of your reading journey!</p>

    <p>Happy Reading!</p>
    <p>The {{ config('app.name') }} Team</p>
@endsection
