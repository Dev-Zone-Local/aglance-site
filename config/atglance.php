<?php

return [

    /*
    | Admin account seeded on deploy. The seeder keeps its password in sync
    | with ADMIN_PASSWORD, so rotating the env value rotates the password.
    */
    'admin_email' => strtolower((string) env('ADMIN_EMAIL', 'admin@atglance.io')),
    'admin_password' => env('ADMIN_PASSWORD'),

    /*
    | Public address of the website (e.g. https://atglance.live). Used in emails, the
    | sitemap and the GitHub callback URL. Defaults to APP_URL.
    */
    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),

    /*
    | Subscription plans. `licenses` is the max number of Management Console
    | licences a user on the plan may hold (null = unlimited). New users get `free`.
    */
    'default_plan' => 'free',

    'plans' => [
        'free' => ['label' => 'Free', 'licenses' => 1],
        'enterprise' => ['label' => 'Enterprise', 'licenses' => null],
    ],

];
