<?php

namespace App\Support;

use App\Models\Setting;

/** "Sign in with Google" credentials (Settings → Google login). */
class GoogleOAuth extends SsoProvider
{
    protected static function driver(): string
    {
        return 'google';
    }

    protected static function settingKey(): string
    {
        return Setting::GOOGLE;
    }
}
