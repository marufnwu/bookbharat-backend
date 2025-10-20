<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Laravel CORS Options
    |--------------------------------------------------------------------------
    |
    | The allowed_methods and allowed_headers options are case-insensitive.
    |
    | You don't need to provide both allowed_origins and allowed_origins_patterns.
    | If one of the strings passed matches, it is considered a valid origin.
    |
    | If array('*') is provided to allowed_methods, allowed_origins or allowed_headers
    | all methods / origins / headers are allowed.
    |
    */

    /*
     * You can enable CORS for 1 or multiple paths.
     * Example: ['api/*']
     */
    'paths' => [],  // Disable built-in CORS, using custom middleware instead

    /*
    * Matches the request method. `[*]` allows all methods.
    */
    'allowed_methods' => ['*'],

    /*
     * Matches the request origin. `[*]` allows all origins.
     * Note: When using credentials, specify exact origins instead of '*'
     */
    'allowed_origins' => array_filter(array_merge(
        // Generate all ports in 3000 range for localhost
        array_map(fn($port) => "http://localhost:$port", range(3000, 3099)),
        // Generate all ports in 3000 range for 127.0.0.1
        array_map(fn($port) => "http://127.0.0.1:$port", range(3000, 3099)),
        [
            // Additional common development ports
            'http://localhost:4000',
            'http://localhost:5000',
            'http://localhost:8080',
            'http://localhost:8081',
            'http://127.0.0.1:4000',
            'http://127.0.0.1:5000',
            'http://127.0.0.1:8080',
            'http://127.0.0.1:8081',

            // Production URLs
            env('FRONTEND_URL'),      // Production frontend
            env('ADMIN_URL'),         // Production admin panel
            'http://v2a.bookbharat.com',
            'https://v2a.bookbharat.com',
            'http://v2.bookbharat.com',
            'https://v2.bookbharat.com',
        ]
    )),

    /*
     * Matches the request origin with, similar to `Request::is()`
     */
    'allowed_origins_patterns' => [],

    /*
     * Sets the Access-Control-Allow-Headers response header. `[*]` allows all headers.
     */
    'allowed_headers' => ['*'],

    /*
     * Sets the Access-Control-Expose-Headers response header.
     */
    'exposed_headers' => false,

    /*
     * Sets the Access-Control-Max-Age response header.
     */
    'max_age' => false,

    /*
     * Sets the Access-Control-Allow-Credentials header.
     */
    'supports_credentials' => true,
];
