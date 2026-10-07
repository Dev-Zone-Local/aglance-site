<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * GitHub OAuth credentials. Values saved in the admin panel (settings row "github")
 * take precedence over GITHUB_* env values. The client secret is stored encrypted.
 */
class GithubOAuth
{
    /**
     * @return array{enabled: bool, client_id: ?string, client_secret: ?string, redirect: ?string}
     */
    public static function credentials(): array
    {
        $saved = Setting::get(Setting::GITHUB);

        $secret = null;
        if (! empty($saved['client_secret'])) {
            try {
                $secret = Crypt::decryptString($saved['client_secret']);
            } catch (DecryptException) {
                $secret = null; // APP_KEY changed; secret must be re-entered
            }
        }

        $creds = [
            'client_id' => ($saved['client_id'] ?? null) ?: config('services.github.client_id'),
            'client_secret' => $secret ?: config('services.github.client_secret'),
            'redirect' => ($saved['redirect'] ?? null) ?: config('services.github.redirect') ?: self::defaultRedirect(),
        ];

        $creds['enabled'] = ($saved['enabled'] ?? true) && $creds['client_id'] && $creds['client_secret'];

        return $creds;
    }

    public static function enabled(): bool
    {
        return (bool) self::credentials()['enabled'];
    }

    /** Push the effective credentials into config so Socialite picks them up. */
    public static function apply(): void
    {
        $creds = self::credentials();

        config(['services.github' => [
            'client_id' => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'redirect' => $creds['redirect'],
        ]]);
    }

    /** The page that receives GitHub's redirect (Auth\GithubController::callback). */
    public static function defaultRedirect(): string
    {
        return rtrim(config('atglance.frontend_url'), '/').'/auth/sso/github/callback';
    }

    public static function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }
}
