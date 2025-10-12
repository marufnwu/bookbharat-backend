<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Leave empty since we're using patterns
    'allowed_origins' => [
        "https://v2.bookbharat.com/",
        "https://v2a.bookbharat.com/",
        "http://216.10.247.145:3000",
        "http://216.10.247.145:3001",

        "http://localhost:3000/",
        "http://localhost:3001/",
        "http://localhost:3002/",
        "http://localhost:3003/",
        "http://localhost:3004/",
        "http://localhost:3005/",
        "http://localhost:3006/",
        "http://localhost:3007/",
        "http://localhost:3008/",
        "http://localhost:3009/",
        "http://localhost:3010/",
        "http://localhost:3011/",
        "http://localhost:3012/",
    ],

    // ✅ Allow any localhost or 127.0.0.1 on ports 3000–3010 (or adjust range)
    'allowed_origins_patterns' => [
        '#^http://localhost:300\d$#',   // localhost:3000–3009
        '#^http://127\.0\.0\.1:300\d$#', // 127.0.0.1:3000–3009
    ],

    'allowed_headers' => ['*', 'x-session-id', 'X-Session-Id'],

    'exposed_headers' => [],

    'max_age' => 86400,

    // ✅ Must be true if using cookies / Sanctum / sessions
    'supports_credentials' => true,
];

