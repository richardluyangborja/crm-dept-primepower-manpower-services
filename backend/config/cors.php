<?php

return [
    /*
    | CORS for the decoupled SPA (specs/01 + 14): Laravel is JSON-only,
    | auth is JWT bearer (no cookies), so credentials stay false and the
    | SPA origin(s) are allowlisted via FRONTEND_URL (comma-separated).
    */
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173'))
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['Authorization'],
    'max_age' => 86400,
    'supports_credentials' => false,
];
