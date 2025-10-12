<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Allow ALL origins (disables CORS restrictions)
    'allowed_origins' => ['*'],

    // Leave empty since we're allowing all origins
    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*', 'x-session-id', 'X-Session-Id'],

    'exposed_headers' => [],

    'max_age' => 86400,

    // ✅ Must be true if using cookies / Sanctum / sessions
    'supports_credentials' => true,
];

