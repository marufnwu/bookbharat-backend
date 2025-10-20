<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Define available notification channels and their default configuration.
    |
    */

    'channels' => ['email', 'sms', 'whatsapp', 'push'],
    
    'default_channels' => ['email'],

    /*
    |--------------------------------------------------------------------------
    | Direct SMS Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for direct SMS API integration (no third-party SDKs).
    | Admin can override these settings via the admin panel.
    |
    */

    'sms' => [
        'enabled' => env('SMS_ENABLED', false),
        'api_endpoint' => env('SMS_API_ENDPOINT'),
        'api_key' => env('SMS_API_KEY'),
        'sender_id' => env('SMS_SENDER_ID', 'BKBHRT'),
        'request_format' => env('SMS_REQUEST_FORMAT', 'json'), // json or form
        
        // Retry configuration
        'max_retries' => env('SMS_MAX_RETRIES', 3),
        'retry_delay' => env('SMS_RETRY_DELAY', 2), // seconds (exponential backoff base)
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business API Configuration
    |--------------------------------------------------------------------------
    |
    | Direct integration with Meta's WhatsApp Business API.
    | Requires approved WhatsApp Business Account.
    | Admin can override these settings via the admin panel.
    |
    */

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v18.0'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        
        // Retry configuration
        'max_retries' => env('WHATSAPP_MAX_RETRIES', 3),
        'retry_delay' => env('WHATSAPP_RETRY_DELAY', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Push Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for push notifications (FCM, etc.)
    |
    */

    'push' => [
        'enabled' => env('PUSH_ENABLED', false),
        'provider' => env('PUSH_PROVIDER', 'fcm'), // fcm or onesignal
        
        // FCM Configuration
        'fcm_server_key' => env('FCM_SERVER_KEY'),
        'fcm_sender_id' => env('FCM_SENDER_ID'),
        
        // OneSignal Configuration
        'onesignal_app_id' => env('ONESIGNAL_APP_ID'),
        'onesignal_rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event-Specific Channel Configuration
    |--------------------------------------------------------------------------
    |
    | Define which channels should be used for each event type by default.
    | These can be overridden in the database via NotificationSettings.
    |
    */

    'event_channels' => [
        'order_placed' => ['email', 'sms'],
        'order_confirmed' => ['email'],
        'order_shipped' => ['email', 'sms', 'whatsapp'],
        'order_delivered' => ['email', 'sms', 'whatsapp'],
        'order_cancelled' => ['email', 'sms'],
        'payment_success' => ['email'],
        'payment_failed' => ['email', 'sms'],
        'return_requested' => ['email'],
        'return_approved' => ['email', 'sms'],
        'return_completed' => ['email'],
        'abandoned_cart' => ['email'],
        'review_request' => ['email'],
        'password_reset' => ['email'],
        'welcome_email' => ['email'],
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Template Mappings
    |--------------------------------------------------------------------------
    |
    | Map event types to WhatsApp approved template names.
    | These must be created and approved in WhatsApp Business Manager.
    |
    */

    'whatsapp_template_mappings' => [
        'order_placed' => 'order_placed_notification',
        'order_shipped' => 'order_shipped_notification',
        'order_delivered' => 'order_delivered_notification',
        'order_cancelled' => 'order_cancelled_notification',
        'payment_failed' => 'payment_failed_notification',
        'return_approved' => 'return_approved_notification',
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Template Messages
    |--------------------------------------------------------------------------
    |
    | Simple SMS text templates for each event type.
    | Variables: {name}, {order_number}, {amount}, {tracking_number}, etc.
    |
    */

    'sms_templates' => [
        'order_placed' => 'Hi {name}, your order #{order_number} has been placed successfully. Total: ₹{amount}. Track at: {url}',
        'order_shipped' => 'Hi {name}, your order #{order_number} has been shipped! Tracking: {tracking_number}. Track at: {url}',
        'order_delivered' => 'Hi {name}, your order #{order_number} has been delivered. Enjoy your books! Rate us: {url}',
        'order_cancelled' => 'Hi {name}, your order #{order_number} has been cancelled. Refund will be processed if applicable.',
        'payment_failed' => 'Hi {name}, payment for order #{order_number} failed. Please retry: {url}',
        'abandoned_cart' => 'Hi {name}, you left items in your cart! Complete your order: {url}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Preferences
    |--------------------------------------------------------------------------
    |
    | User-level notification preferences configuration.
    |
    */

    'user_preferences' => [
        'allow_opt_out' => true,
        'default_enabled' => [
            'email' => true,
            'sms' => false, // Opt-in for SMS
            'whatsapp' => false, // Opt-in for WhatsApp
            'push' => true,
        ],
    ],
];

