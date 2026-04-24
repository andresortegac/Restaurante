<?php

return [
    'sandbox_base_url' => env('FACTUS_SANDBOX_BASE_URL', 'https://api-sandbox.factus.com.co'),
    'production_base_url' => env('FACTUS_PRODUCTION_BASE_URL', 'https://api.factus.com.co'),
    'timeout' => (int) env('FACTUS_TIMEOUT', 15),
    'connect_timeout' => (int) env('FACTUS_CONNECT_TIMEOUT', 10),
    'token_cache_ttl' => (int) env('FACTUS_TOKEN_CACHE_TTL', 540),
];
