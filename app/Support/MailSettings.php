<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Outgoing mail (SMTP) settings saved in the admin panel (settings row "mail").
 * When saved, they override the MAIL_* env values. The SMTP password is stored encrypted.
 */
class MailSettings
{
    public const ENCRYPTION = ['tls' => 'TLS / STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None'];

    public static function saved(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Setting::get(Setting::MAIL);
        } catch (Throwable) {
            return []; // DB not reachable yet (install/migrate time): fall back to env
        }
    }

    /** Push the saved SMTP settings into config before the first mailer is built. */
    public static function apply(): void
    {
        $s = self::saved();

        if (empty($s['enabled']) || empty($s['host'])) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => ($s['encryption'] ?? 'tls') === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.host' => $s['host'],
            'mail.mailers.smtp.port' => (int) ($s['port'] ?? 587),
            'mail.mailers.smtp.username' => $s['username'] ?? null,
            'mail.mailers.smtp.password' => self::password($s),
            'mail.from.address' => $s['from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $s['from_name'] ?? config('mail.from.name'),
        ]);
    }

    public static function password(array $saved): ?string
    {
        if (empty($saved['password'])) {
            return null;
        }

        try {
            return Crypt::decryptString($saved['password']);
        } catch (DecryptException) {
            return null; // APP_KEY changed; password must be re-entered
        }
    }
}
