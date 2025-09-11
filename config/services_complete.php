<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Services
    |--------------------------------------------------------------------------
    */

    'razorpay' => [
        'key' => env('RAZORPAY_KEY_ID'),
        'secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'logo' => env('RAZORPAY_LOGO_URL'),
        'theme_color' => env('RAZORPAY_THEME_COLOR', '#2563eb'),
    ],

    'cashfree' => [
        'client_id' => env('CASHFREE_CLIENT_ID'),
        'client_secret' => env('CASHFREE_CLIENT_SECRET'),
        'environment' => env('CASHFREE_ENVIRONMENT', 'TEST'), // TEST or PROD
        'base_url' => env('CASHFREE_ENVIRONMENT', 'TEST') === 'PROD' 
            ? 'https://api.cashfree.com/pg' 
            : 'https://sandbox.cashfree.com/pg',
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Service Configuration
    |--------------------------------------------------------------------------
    */

    'email_templates' => [
        'enabled' => env('EMAIL_TEMPLATES_ENABLED', true),
        'from_name' => env('MAIL_FROM_NAME', 'BookBharat'),
        'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@bookbharat.com'),
        'support_email' => env('SUPPORT_EMAIL', 'support@bookbharat.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Service Configuration (for OTP and notifications)
    |--------------------------------------------------------------------------
    */

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'twilio'), // twilio, msg91, textlocal
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
        'msg91' => [
            'auth_key' => env('MSG91_AUTH_KEY'),
            'template_id' => env('MSG91_TEMPLATE_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping Service Configuration
    |--------------------------------------------------------------------------
    */

    'shipping' => [
        'default_carrier' => env('DEFAULT_SHIPPING_CARRIER', 'bluedart'),
        'carriers' => [
            'bluedart' => [
                'api_key' => env('BLUEDART_API_KEY'),
                'license_key' => env('BLUEDART_LICENSE_KEY'),
                'environment' => env('BLUEDART_ENVIRONMENT', 'test'), // test or live
            ],
            'dtdc' => [
                'api_key' => env('DTDC_API_KEY'),
                'customer_code' => env('DTDC_CUSTOMER_CODE'),
            ],
            'delhivery' => [
                'api_key' => env('DELHIVERY_API_KEY'),
                'environment' => env('DELHIVERY_ENVIRONMENT', 'test'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Storage Configuration
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'invoices_disk' => env('INVOICES_DISK', 'public'),
        'returns_disk' => env('RETURNS_DISK', 'public'),
        'products_disk' => env('PRODUCTS_DISK', 'public'),
        'max_file_size' => env('MAX_FILE_SIZE', 2048), // KB
        'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics and Tracking
    |--------------------------------------------------------------------------
    */

    'analytics' => [
        'google_analytics_id' => env('GOOGLE_ANALYTICS_ID'),
        'facebook_pixel_id' => env('FACEBOOK_PIXEL_ID'),
        'google_tag_manager_id' => env('GOOGLE_TAG_MANAGER_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Media Integration
    |--------------------------------------------------------------------------
    */

    'social' => [
        'facebook' => [
            'app_id' => env('FACEBOOK_APP_ID'),
            'app_secret' => env('FACEBOOK_APP_SECRET'),
            'redirect' => env('FACEBOOK_REDIRECT_URI'),
        ],
        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect' => env('GOOGLE_REDIRECT_URI'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Service Configuration
    |--------------------------------------------------------------------------
    */

    'search' => [
        'provider' => env('SEARCH_PROVIDER', 'elasticsearch'), // elasticsearch, algolia
        'elasticsearch' => [
            'hosts' => [
                env('ELASTICSEARCH_HOST', 'localhost:9200')
            ],
            'index_prefix' => env('ELASTICSEARCH_INDEX_PREFIX', 'bookbharat_'),
        ],
        'algolia' => [
            'app_id' => env('ALGOLIA_APP_ID'),
            'secret' => env('ALGOLIA_SECRET'),
            'index_name' => env('ALGOLIA_INDEX_NAME', 'products'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'default_ttl' => env('CACHE_DEFAULT_TTL', 3600), // seconds
        'tags_enabled' => env('CACHE_TAGS_ENABLED', true),
        'prefixes' => [
            'products' => 'products:',
            'users' => 'users:',
            'orders' => 'orders:',
            'categories' => 'categories:',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'email_queue' => env('EMAIL_QUEUE', 'emails'),
        'payment_queue' => env('PAYMENT_QUEUE', 'payments'),
        'default_queue' => env('QUEUE_DEFAULT', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Logic Configuration
    |--------------------------------------------------------------------------
    */

    'business' => [
        'return_window_days' => env('RETURN_WINDOW_DAYS', 30),
        'free_shipping_threshold' => env('FREE_SHIPPING_THRESHOLD', 500),
        'max_cart_items' => env('MAX_CART_ITEMS', 50),
        'max_wishlist_items' => env('MAX_WISHLIST_ITEMS', 100),
        'default_currency' => env('DEFAULT_CURRENCY', 'INR'),
        'supported_currencies' => ['INR', 'USD'],
        'tax_rate' => env('DEFAULT_TAX_RATE', 18), // GST rate in percentage
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */

    'security' => [
        'password_reset_expire_minutes' => env('PASSWORD_RESET_EXPIRE_MINUTES', 60),
        'max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', 5),
        'lockout_duration_minutes' => env('LOCKOUT_DURATION_MINUTES', 15),
        'enable_2fa' => env('ENABLE_2FA', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */

    'api' => [
        'rate_limit' => env('API_RATE_LIMIT', '60,1'), // 60 requests per minute
        'pagination' => [
            'default_per_page' => env('API_DEFAULT_PER_PAGE', 15),
            'max_per_page' => env('API_MAX_PER_PAGE', 100),
        ],
        'version' => env('API_VERSION', 'v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend Application Configuration
    |--------------------------------------------------------------------------
    */

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:3000'),
        'api_timeout' => env('FRONTEND_API_TIMEOUT', 30), // seconds
        'supported_locales' => ['en', 'hi'],
        'default_locale' => env('DEFAULT_LOCALE', 'en'),
    ],

];