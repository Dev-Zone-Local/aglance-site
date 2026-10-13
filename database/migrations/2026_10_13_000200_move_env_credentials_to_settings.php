<?php

use Dotenv\Dotenv;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * GitHub sign-in and the CAPTCHA used to read GITHUB_* / RECAPTCHA_* from .env; they are now
 * set in the admin panel only. Copy any values still in .env into the settings table once, so
 * an install that relied on .env keeps working after the upgrade. Values already saved in the
 * admin panel win. The .env file is parsed directly because a config cache hides env().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return; // tests must not pick up the developer's own .env keys
        }

        $path = base_path('.env');
        $env = is_file($path) ? Dotenv::parse((string) file_get_contents($path)) : [];

        $github = $this->setting('github');
        if (empty($github['client_id']) && filled($env['GITHUB_CLIENT_ID'] ?? null) && filled($env['GITHUB_CLIENT_SECRET'] ?? null)) {
            $this->put('github', [
                'enabled' => $github['enabled'] ?? true,
                'client_id' => $env['GITHUB_CLIENT_ID'],
                'client_secret' => Crypt::encryptString($env['GITHUB_CLIENT_SECRET']),
                'redirect' => ($github['redirect'] ?? null) ?: (($env['GITHUB_REDIRECT_URI'] ?? null) ?: null),
            ]);
        }

        if ($this->setting('captcha') === [] && filled($env['RECAPTCHA_SITE_KEY'] ?? null) && filled($env['RECAPTCHA_SECRET_KEY'] ?? null)) {
            $this->put('captcha', [
                'enabled' => true,
                'site_key' => $env['RECAPTCHA_SITE_KEY'],
                'secret_key' => Crypt::encryptString($env['RECAPTCHA_SECRET_KEY']),
                'min_score' => (float) ($env['RECAPTCHA_MIN_SCORE'] ?? 0.5),
            ]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the settings rows stay valid.
    }

    private function setting(string $key): array
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        return $value ? (array) json_decode($value, true) : [];
    }

    private function put(string $key, array $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
        );
    }
};
