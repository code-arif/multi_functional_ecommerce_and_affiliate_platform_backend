<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS Paths
    |--------------------------------------------------------------------------
    | Only API routes are CORS-enabled. Web routes are not exposed.
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    */
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    | In production these are loaded from env. Never use ['*'] in production.
    */
    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:3000'),
        env('ADMIN_URL',    'http://localhost:3001'),
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:3002',
        'http://localhost:3003',
        'http://localhost:3004',
        'http://localhost:3005',
        'http://localhost:3006',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:3001',
        'http://127.0.0.1:5173',
    ]),

    'allowed_origins_patterns' => [
        '#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    */
    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    */
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Age (preflight cache)
    |--------------------------------------------------------------------------
    | 2 hours = 7200 seconds
    */
    'max_age' => 7200,

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    | Required for Sanctum cookie-based SPA authentication.
    */
    'supports_credentials' => true,
];
