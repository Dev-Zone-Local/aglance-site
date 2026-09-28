<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * One-time 5-digit email code that gates licence actions ("create" and "revoke").
 * A code is bound to its purpose and subject (licence name, or licence id for revoke).
 * Only an HMAC of the code is cached; a code is valid for TTL_MINUTES and
 * MAX_ATTEMPTS wrong guesses, and can be used once.
 */
class LicenseCode
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const CREATE = 'create';

    public const REVOKE = 'revoke';

    public static function issue(User $user, string $subject, string $purpose = self::CREATE): string
    {
        $code = (string) random_int(10000, 99999);

        Cache::put(self::key($user, $purpose), [
            'hash' => self::hash($user, $purpose, $subject, $code),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $code;
    }

    /**
     * @return 'ok'|'missing'|'invalid'|'locked'
     */
    public static function check(User $user, string $subject, string $code, string $purpose = self::CREATE): string
    {
        $key = self::key($user, $purpose);
        $entry = Cache::get($key);

        if (! $entry) {
            return 'missing';
        }

        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return 'locked';
        }

        if (! hash_equals($entry['hash'], self::hash($user, $purpose, $subject, $code))) {
            $entry['attempts']++;
            Cache::put($key, $entry, now()->addMinutes(self::TTL_MINUTES));

            return $entry['attempts'] >= self::MAX_ATTEMPTS ? 'locked' : 'invalid';
        }

        Cache::forget($key); // single use

        return 'ok';
    }

    /** User-facing message for a failed check() result. */
    public static function errorMessage(string $result): string
    {
        return match ($result) {
            'missing' => 'The code expired or was not requested. Request a new code.',
            'locked' => 'Too many wrong attempts. Request a new code.',
            default => 'The code is incorrect.',
        };
    }

    private static function key(User $user, string $purpose): string
    {
        return "license-code:{$purpose}:{$user->id}";
    }

    private static function hash(User $user, string $purpose, string $subject, string $code): string
    {
        return hash_hmac('sha256', implode('|', [$user->id, $purpose, $subject, $code]), (string) config('app.key'));
    }
}
