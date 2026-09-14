<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shopify App Configuration
    |--------------------------------------------------------------------------
    */
    'client_id' => env('SHOPIFY_API_KEY', env('SHOPIFY_CLIENT_ID', '')),
    'client_secret' => env('SHOPIFY_API_SECRET', env('SHOPIFY_CLIENT_SECRET', '')),
    'app_url' => env('SHOPIFY_APP_URL', env('APP_URL', 'http://localhost:8000')),
    'scopes' => env('SHOPIFY_SCOPES', 'read_products'),
    'api_version' => env('SHOPIFY_API_VERSION', '2024-04'),
    'redirect_uri' => env('SHOPIFY_REDIRECT_URI'),
];
