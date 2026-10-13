<?php

return [

    /*
    | The admin account is not configured here: create it with `php artisan atglance:make-admin`.
    | GitHub / Google sign-in and the CAPTCHA are set in the admin panel (Settings).
    */

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
