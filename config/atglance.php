<?php

return [

    /*
    | Admin account seeded on deploy. The seeder keeps its password in sync
    | with ADMIN_PASSWORD, so rotating the env value rotates the password.
    */
    'admin_email' => strtolower((string) env('ADMIN_EMAIL', 'admin@atglance.io')),
    'admin_password' => env('ADMIN_PASSWORD'),

    /*
    | Origin of the React SPA. Used for CORS and as the post-login redirect.
    */
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

];
