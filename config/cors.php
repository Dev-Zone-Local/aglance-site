<?php

// Only the SPA origin(s) may make credentialed cross-origin calls. Never use "*" here:
// with supports_credentials it would let any site act as the logged-in user.
$origins = array_filter(array_map('trim', array_merge(
    [env('FRONTEND_URL', 'http://localhost:3000')],
    explode(',', (string) env('CORS_ORIGINS', '')),
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique($origins)),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
