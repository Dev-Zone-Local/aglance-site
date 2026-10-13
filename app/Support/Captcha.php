<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Google reCAPTCHA v3 settings (Settings → CAPTCHA in the admin panel), stored in the `settings`
 * table with the secret key encrypted. Used by App\Rules\Recaptcha and the recaptcha views.
 * Turn it off from the shell with `php artisan atglance:captcha --off`.
 */
class Captcha
{
    public const DEFAULT_MIN_SCORE = 0.5;

    /** @return array{enabled: bool, site_key: ?string, secret_key: ?string, min_score: float} */
    public static function settings(): array
    {
        $saved = Setting::get(Setting::CAPTCHA);

        $secret = null;
        if (! empty($saved['secret_key'])) {
            try {
                $secret = Crypt::decryptString($saved['secret_key']);
            } catch (DecryptException) {
                $secret = null; // APP_KEY changed; secret must be re-entered
            }
        }

        $settings = [
            'site_key' => ($saved['site_key'] ?? null) ?: null,
            'secret_key' => $secret ?: null,
            'min_score' => (float) ($saved['min_score'] ?? self::DEFAULT_MIN_SCORE),
        ];
        $settings['enabled'] = (bool) (($saved['enabled'] ?? false) && $settings['site_key'] && $settings['secret_key']);

        return $settings;
    }

    public static function enabled(): bool
    {
        return self::settings()['enabled'];
    }

    public static function siteKey(): ?string
    {
        return self::settings()['site_key'];
    }

    public static function secretKey(): ?string
    {
        return self::settings()['secret_key'];
    }

    public static function minScore(): float
    {
        return self::settings()['min_score'];
    }

    /** Save from the admin page or a test; a null secret keeps the stored one. */
    public static function save(bool $enabled, ?string $siteKey, ?string $secretKey, float $minScore): void
    {
        $saved = Setting::get(Setting::CAPTCHA);

        Setting::put(Setting::CAPTCHA, [
            'enabled' => $enabled,
            'site_key' => $siteKey ?: null,
            'secret_key' => filled($secretKey) ? Crypt::encryptString($secretKey) : ($saved['secret_key'] ?? null),
            'min_score' => $minScore,
        ]);
    }

    public static function disable(): void
    {
        Setting::put(Setting::CAPTCHA, ['enabled' => false] + Setting::get(Setting::CAPTCHA));
    }
}
