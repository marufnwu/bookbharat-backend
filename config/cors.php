<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Leave empty since we're using patterns
    'allowed_origins' => [],

    // ✅ Allow any localhost or 127.0.0.1 on ports 3000–3010 (or adjust range)
    'allowed_origins_patterns' => [
        '#^http://localhost:300\d$#',   // localhost:3000–3009
        '#^http://127\.0\.0\.1:300\d$#', // 127.0.0.1:3000–3009
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    // ✅ Must be true if using cookies / Sanctum / sessions
    'supports_credentials' => true,
];

