<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configured for Docker development: frontend on :8080, backend on :8000
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:8080', 'http://127.0.0.1:8080', 'https://hoxton-mini-wallet-2.onrender.com'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
