<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * OAuth ("Sign in with ...") credentials for one Socialite driver, set in the admin panel
 * (Settings → GitHub login / Google login) and stored in the `settings` table. The client
 * secret is stored encrypted. There is no .env fallback.
 */
abstract class SsoProvider
{
    /** Socialite driver and services.* config key, e.g. "github". */
    abstract protected static function driver(): string;

    /** The `settings.key` holding this provider's credentials. */
    abstract protected static function settingKey(): string;

    /**
     * @return array{enabled: bool, client_id: ?string, client_secret: ?string, redirect: string}
     */
    public static function credentials(): array
    {
        $saved = Setting::get(static::settingKey());

        $secret = null;
        if (! empty($saved['client_secret'])) {
            try {
                $secret = Crypt::decryptString($saved['client_secret']);
            } catch (DecryptException) {
                $secret = null; // APP_KEY changed; secret must be re-entered
            }
        }

        $creds = [
            'client_id' => ($saved['client_id'] ?? null) ?: null,
            'client_secret' => $secret ?: null,
            'redirect' => ($saved['redirect'] ?? null) ?: static::defaultRedirect(),
        ];

        $creds['enabled'] = (bool) (($saved['enabled'] ?? false) && $creds['client_id'] && $creds['client_secret']);

        return $creds;
    }

    public static function enabled(): bool
    {
        return static::credentials()['enabled'];
    }

    /** Push the saved credentials into config so Socialite picks them up. */
    public static function apply(): void
    {
        $creds = static::credentials();

        config(['services.'.static::driver() => [
            'client_id' => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'redirect' => $creds['redirect'],
        ]]);
    }

    /** The page that receives the provider's redirect, e.g. /auth/sso/github/callback. */
    public static function defaultRedirect(): string
    {
        return rtrim(config('atglance.frontend_url'), '/').'/auth/sso/'.static::driver().'/callback';
    }

    public static function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }
}
