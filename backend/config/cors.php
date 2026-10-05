<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:8080',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'http://127.0.0.1:8080',
        'http://192.168.18.30:8080',
        env('FRONTEND_URL'),
        env('APP_URL'),
        env('PUBLIC_BASE_URL'),
    ],

    'allowed_origins_patterns' => [
        '#^https?://.*\.ngrok-free\.app$#',
        '#^https?://.*\.ngrok-free\.dev$#',
        '#^https?://.*\.ngrok\.io$#',
        '#^https?://.*\.ngrok\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
