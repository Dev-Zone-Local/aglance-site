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
    | Subscription plans (labels). How many licences a user on a plan may hold is the
    | plan's "Licence limit" in Admin → License Policy. New users get `free`.
    */
    'default_plan' => 'free',

    'plans' => [
        // Licence limits, validity and features come from Admin → License Policy (per plan).
        'free' => ['label' => 'Free'],
        'enterprise' => ['label' => 'Enterprise'],
    ],

    /*
    | Offline licence files (.atgl) for the Management Console. Format: docs/LICENSING.md.
    | The keys come only from the environment; generate them with `php artisan license:generate-keys`.
    */
    'license_file' => [
        'key_id' => env('LICENSE_KEY_ID'),
        // Ed25519 private key: PKCS#8 PEM, or the base64 of its DER body on one line.
        'signing_key' => env('LICENSE_SIGNING_PRIVATE_KEY'),
        // AES-256 key, 64 hex characters.
        'enc_key' => env('LICENSE_ENC_KEY'),
        // Lowest console version that understands licence files.
        'min_version' => env('LICENSE_MIN_CONSOLE_VERSION', '2.3.0'),
        // Files one account may generate per day: Admin → License Policy → Global default.
    ],

];
