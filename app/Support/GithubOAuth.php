<?php

namespace App\Support;

use App\Models\Setting;

/** "Sign in with GitHub" credentials (Settings → GitHub login). */
class GithubOAuth extends SsoProvider
{
    protected static function driver(): string
    {
        return 'github';
    }

    protected static function settingKey(): string
    {
        return Setting::GITHUB;
    }
}
