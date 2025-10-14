<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shipping Carriers Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for all shipping carriers.
    | Each carrier has its own specific structure and requirements.
    | Credentials and sensitive data should be stored in .env file.
    |
    */

    'default' => env('DEFAULT_SHIPPING_CARRIER', 'delhivery'),

    'carriers' => [
        'delhivery' => [
            'enabled' => env('DELHIVERY_ENABLED', false),
            'code' => 'DELHIVERY',
            'name' => 'Delhivery',
            'display_name' => 'Delhivery Express',
            'logo_url' => 'https://www.delhivery.com/img/logo/logo.png',
            'api_mode' => env('DELHIVERY_MODE', 'test'), // test or live
            'test' => [
                'api_endpoint' => 'https://staging-express.delhivery.com',
                'api_key' => env('DELHIVERY_TEST_API_KEY', ''),
                'client_name' => env('DELHIVERY_TEST_CLIENT_NAME', ''),
            ],
            'live' => [
                'api_endpoint' => 'https://track.delhivery.com',
                'api_key' => env('DELHIVERY_LIVE_API_KEY', ''),
                'client_name' => env('DELHIVERY_LIVE_CLIENT_NAME', ''),
            ],
            'features' => ['tracking', 'cod', 'reverse_pickup', 'insurance', 'multi_piece'],
            'services' => [
                'EXPRESS' => 'Express Delivery',
                'SURFACE' => 'Surface Delivery',
            ],
            'webhook_url' => env('DELHIVERY_WEBHOOK_URL', '/api/v1/shipping/webhook/delhivery'),
            'weight_unit' => 'kg',
            'dimension_unit' => 'cm',
            'max_weight' => 50,
            'max_insurance_value' => 50000,
            'cutoff_time' => '17:00',
            'pickup_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
        ],

        'bluedart' => [
            'enabled' => env('BLUEDART_ENABLED', false),
            'code' => 'BLUEDART',
            'name' => 'BlueDart',
            'display_name' => 'BlueDart Express',
            'logo_url' => 'https://www.bluedart.com/img/bluedart-logo.png',
            'api_mode' => env('BLUEDART_MODE', 'test'),
            'test' => [
                'api_endpoint' => 'http://netconnect.bluedart.com/Demo/',
                'license_key' => env('BLUEDART_TEST_LICENSE_KEY', ''),
                'login_id' => env('BLUEDART_TEST_LOGIN_ID', ''),
            ],
            'live' => [
                'api_endpoint' => 'https://netconnect.bluedart.com/Ver1.10/',
                'license_key' => env('BLUEDART_LIVE_LICENSE_KEY', ''),
                'login_id' => env('BLUEDART_LIVE_LOGIN_ID', ''),
            ],
            'features' => [
                'tracking',
                'cod',
                'reverse_pickup',
                'insurance',
                'priority',
            ],
            'services' => [
                'DOMESTIC_PRIORITY' => 'Domestic Priority',
                'SURFACE' => 'Ground Express',
                'APEX' => 'Apex',
            ],
            'product_codes' => [
                'cod' => 'C',
                'prepaid' => 'P',
            ],
            'webhook_url' => env('BLUEDART_WEBHOOK_URL', '/api/v1/shipping/webhook/bluedart'),
            'weight_unit' => 'kg',
            'dimension_unit' => 'cm',
            'max_weight' => 30,
            'max_insurance_value' => 50000,
            'cutoff_time' => '18:00',
            'pickup_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
        ],

        'xpressbees' => [
            'enabled' => env('XPRESSBEES_ENABLED', false),
            'code' => 'XPRESSBEES',
            'name' => 'Xpressbees',
            'display_name' => 'Xpressbees Logistics',
            'logo_url' => 'https://www.xpressbees.com/assets/img/xpressbees-logo.png',
            'api_mode' => env('XPRESSBEES_MODE', 'test'),
            'test' => [
                'api_endpoint' => 'https://shipment.xpressbees.com/api',
                'email' => env('XPRESSBEES_TEST_EMAIL', ''),
                'password' => env('XPRESSBEES_TEST_PASSWORD', ''),
                'account_id' => env('XPRESSBEES_TEST_ACCOUNT_ID', ''),
            ],
            'live' => [
                'api_endpoint' => 'https://shipment.xpressbees.com/api',
                'email' => env('XPRESSBEES_LIVE_EMAIL', ''),
                'password' => env('XPRESSBEES_LIVE_PASSWORD', ''),
                'account_id' => env('XPRESSBEES_LIVE_ACCOUNT_ID', ''),
            ],
            'features' => ['tracking', 'cod', 'reverse_pickup', 'multi_vendor'],
            'services' => [
                'STANDARD' => 'Standard Delivery',
                'EXPRESS' => 'Express Delivery',
            ],
            'webhook_url' => env('XPRESSBEES_WEBHOOK_URL', '/api/v1/shipping/webhook/xpressbees'),
            'weight_unit' => 'kg',
            'dimension_unit' => 'cm',
            'max_weight' => 40,
            'max_insurance_value' => 0,
            'cutoff_time' => '16:00',
            'pickup_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
        ],

        'dtdc' => [
            'enabled' => env('DTDC_ENABLED', false),
            'code' => 'DTDC',
            'name' => 'DTDC',
            'display_name' => 'DTDC Courier',
            'logo_url' => 'https://www.dtdc.in/img/dtdc-logo.png',
            'api_mode' => env('DTDC_MODE', 'test'),
            'test' => [
                'api_endpoint' => 'https://app-staging.dtdc.com/api',
                'access_token' => env('DTDC_TEST_ACCESS_TOKEN', ''),
                'customer_code' => env('DTDC_TEST_CUSTOMER_CODE', ''),
            ],
            'live' => [
                'api_endpoint' => 'https://app.dtdc.com/api',
                'access_token' => env('DTDC_LIVE_ACCESS_TOKEN', ''),
                'customer_code' => env('DTDC_LIVE_CUSTOMER_CODE', ''),
            ],
            'features' => [
                'tracking',
                'cod',
                'reverse_pickup',
                'insurance',
            ],
            'services' => [
                'PREMIUM' => 'Premium Express',
                'GROUND' => 'Ground Express',
                'B2C' => 'B2C Express',
            ],
            'webhook_url' => env('DTDC_WEBHOOK_URL', '/api/v1/shipping/webhook/dtdc'),
            'weight_unit' => 'kg',
            'dimension_unit' => 'cm',
            'max_weight' => 25,
            'max_insurance_value' => 50000,
            'cutoff_time' => '17:00',
            'pickup_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ],

        'ecom_express' => [
            'enabled' => env('ECOM_EXPRESS_ENABLED', false),
            'code' => 'ECOM_EXPRESS',
            'name' => 'Ecom Express',
            'display_name' => 'Ecom Express',
            'logo_url' => 'https://ecomexpress.in/img/logo.png',
            'api_mode' => env('ECOM_EXPRESS_MODE', 'test'),
            'test' => [
                'api_endpoint' => 'https://plapi-staging.ecomexpress.in/api/v2',
                'username' => env('ECOM_EXPRESS_TEST_USERNAME', ''),
                'password' => env('ECOM_EXPRESS_TEST_PASSWORD', ''),
            ],
            'live' => [
                'api_endpoint' => 'https://plapi.ecomexpress.in/api/v2',
                'username' => env('ECOM_EXPRESS_LIVE_USERNAME', ''),
                'password' => env('ECOM_EXPRESS_LIVE_PASSWORD', ''),
            ],
            'features' => ['tracking', 'cod', 'reverse_pickup', 'insurance', 'qc_check'],
            'services' => [
                'REGULAR' => 'Regular Service',
                'EXPRESS' => 'Express Service',
                'ROS' => 'Return to Origin',
            ],
            'webhook_url' => env('ECOM_EXPRESS_WEBHOOK_URL', '/api/v1/shipping/webhook/ecom_express'),
            'weight_unit' => 'kg',
            'dimension_unit' => 'cm',
            'max_weight' => 50,
            'max_insurance_value' => 100000,
            'cutoff_time' => '17:00',
            'pickup_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
        ],

        'shadowfax' => [
            'enabled' => env('SHADOWFAX_ENABLED', false),
            'code' => 'SHADOWFAX',
            'name' => 'Shadowfax',
            'display_name' => 'Shadowfax',
            'logo_url' => 'https://www.shadowfax.in/img/logo.png',
            'api_mode' => env('SHADOWFAX_MODE', 'test'),
            'test' => [
                'api_endpoint' => 'https://sandbox-api.shadowfax.in/v3',
                'api_token' => env('SHADOWFAX_TEST_API_TOKEN', ''),
            ],
            'live' => [
                'api_endpoint' => 'https://api.shadowfax.in/v3',
                'api_token' => env('SHADOWFAX_LIVE_API_TOKEN', ''),
            ],
            'features' => ['tracking', 'cod', 'same_day', 'next_day', 'instant'],
            'services' => [
                'INSTANT' => 'Instant Delivery (2-4 hours)',
                'SAME_DAY' => 'Same Day Delivery',
                'NEXT_DAY' => 'Next Day Delivery',
                'STANDARD' => 'Standard Delivery',
            ],
            'webhook_url' => env('SHADOWFAX_WEBHOOK_URL', '/api/v1/shipping/webhook/shadowfax'),
            'weight_unit' => 'kg',
            'dimension_unit' => 'cm',
            'max_weight' => 20,
            'max_insurance_value' => 25000,
            'cutoff_time' => '20:00',
            'pickup_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ],

        'shiprocket' => [
            'enabled' => env('SHIPROCKET_ENABLED', false),
            'code' => 'SHIPROCKET',
            'name' => 'Shiprocket',
            'display_name' => 'Shiprocket',
            'logo_url' => 'https://www.shiprocket.in/img/logo.png',
            'api_mode' => env('SHIPROCKET_MODE', 'live'), // Shiprocket doesn't have test mode
            'live' => [
                'api_endpoint' => 'https://apiv2.shiprocket.in/v1/external',
                'email' => env('SHIPROCKET_EMAIL', ''),
                'password' => env('SHIPROCKET_PASSWORD', ''),
            ],
            'features' => ['tracking', 'cod', 'reverse_pickup', 'insurance', 'multi_channel', 'ndr_management'],
            'services' => [
                'LITE' => 'Shiprocket Lite',
                'SURFACE' => 'Surface',
                'EXPRESS' => 'Express',
            ],
            'webhook_url' => env('SHIPROCKET_WEBHOOK_URL', '/api/v1/shipping/webhook/shiprocket'),
            'weight_unit' => 'kg',
            'dimension_unit' => 'cm',
            'max_weight' => 50,
            'max_insurance_value' => 500000,
            'cutoff_time' => '17:00',
            'pickup_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
        ],

        'ekart' => [
            'enabled' => env('EKART_ENABLED', false),
            'code' => 'EKART',
            'name' => 'Ekart',
            'display_name' => 'Ekart Logistics',
            'logo_url' => 'https://storage.googleapis.com/fs.goswift.in/site/ekart_logo.png',
            'api_mode' => env('EKART_MODE', 'live'), // Ekart only has live mode
            'live' => [
                'api_endpoint' => 'https://app.elite.ekartlogistics.in',
                'client_id' => env('EKART_CLIENT_ID', ''),
                'username' => env('EKART_USERNAME', ''),
                'password' => env('EKART_PASSWORD', ''),
            ],
            'features' => ['tracking', 'cod', 'reverse_pickup', 'insurance', 'serviceability_check'],
            'services' => [
                'SURFACE' => 'Surface Delivery',
                'EXPRESS' => 'Express Delivery',
            ],
            'webhook_url' => env('EKART_WEBHOOK_URL', '/api/v1/shipping/webhook/ekart'),
            'weight_unit' => 'kg',
            'dimension_unit' => 'cm',
            'max_weight' => 50,
            'max_insurance_value' => 100000,
            'cutoff_time' => '17:00',
            'pickup_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
            'supported_payment_modes' => ['prepaid', 'cod'],
        ],

        'bigship' => [
            'enabled' => env('BIGSHIP_ENABLED', true),
            'code' => 'BIGSHIP',
            'name' => 'BigShip',
            'display_name' => 'BigShip Logistics',
            'logo_url' => 'https://www.bigship.in/img/logo.png',
            'api_mode' => env('BIGSHIP_MODE', 'live'), // BigShip only has live mode
            'live' => [
                'api_endpoint' => 'https://api.bigship.in/api',
                'username' => env('BIGSHIP_USERNAME', ''),
                'password' => env('BIGSHIP_PASSWORD', ''),
                'access_key' => env('BIGSHIP_ACCESS_KEY', ''),
            ],
            'features' => ['tracking', 'cod', 'reverse_pickup', 'insurance', 'warehouse_management', 'b2b_support'],
            'services' => [
                'STANDARD' => 'Standard Delivery',
                'EXPRESS' => 'Express Delivery',
                'SURFACE' => 'Surface Delivery',
            ],
            'webhook_url' => env('BIGSHIP_WEBHOOK_URL', '/api/v1/shipping/webhook/bigship'),
            'weight_unit' => 'kg',
            'dimension_unit' => 'cm',
            'max_weight' => 50,
            'max_insurance_value' => 50000,
            'cutoff_time' => '17:00',
            'pickup_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
            'supported_payment_modes' => ['prepaid', 'cod'],
            'supported_shipment_categories' => ['b2c', 'b2b'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Pickup Location
    |--------------------------------------------------------------------------
    */
    'pickup_location' => [
        'name' => env('PICKUP_LOCATION_NAME', 'BookBharat Warehouse'),
        'address' => env('PICKUP_ADDRESS', '123, Industrial Area'),
        'city' => env('PICKUP_CITY', 'Delhi'),
        'state' => env('PICKUP_STATE', 'Delhi'),
        'country' => env('PICKUP_COUNTRY', 'India'),
        'pincode' => env('PICKUP_PINCODE', '110001'),
        'phone' => env('PICKUP_PHONE', '9999999999'),
        'email' => env('PICKUP_EMAIL', 'warehouse@bookbharat.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Return Address (if different from pickup)
    |--------------------------------------------------------------------------
    */
    'return_address' => [
        'name' => env('RETURN_LOCATION_NAME', 'BookBharat Returns'),
        'address' => env('RETURN_ADDRESS', '456, Return Processing Center'),
        'city' => env('RETURN_CITY', 'Delhi'),
        'state' => env('RETURN_STATE', 'Delhi'),
        'country' => env('RETURN_COUNTRY', 'India'),
        'pincode' => env('RETURN_PINCODE', '110002'),
        'phone' => env('RETURN_PHONE', '9999999998'),
        'email' => env('RETURN_EMAIL', 'returns@bookbharat.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping Rules
    |--------------------------------------------------------------------------
    */
    'rules' => [
        'auto_select_carrier' => env('AUTO_SELECT_CARRIER', true),
        'selection_priority' => env('CARRIER_SELECTION_PRIORITY', 'cost'), // cost, speed, rating
        'max_retry_attempts' => env('MAX_SHIPPING_RETRY', 3),
        'enable_insurance' => env('ENABLE_SHIPPING_INSURANCE', true),
        'insurance_threshold' => env('INSURANCE_THRESHOLD', 5000),
        'enable_real_time_tracking' => env('ENABLE_REAL_TIME_TRACKING', true),
    ],
];
