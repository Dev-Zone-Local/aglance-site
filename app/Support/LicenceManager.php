<?php

namespace App\Support;

use App\Models\License;
use App\Models\User;
use App\Notifications\LicenseCodeNotification;
use App\Support\LicensePolicy\PolicyResolver;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Management Console licences for one user: create, confirm with the emailed code, show the
 * key (for 30 minutes), revoke. Used by the dashboard (Livewire) and the JSON API.
 *
 * A user's plan limits how many licences they hold (config atglance.plans). Creating a
 * licence emails a 5-digit code; the licence becomes usable once the user enters it. Users
 * flagged with `license_requires_approval` additionally need an admin approval.
 *
 * Failures throw ValidationException (field errors) or HttpException via abort().
 */
class LicenceManager
{
    public function __construct(private readonly User $user) {}

    public static function for(User $user): self
    {
        return new self($user);
    }

    public function find(int $id): License
    {
        $licence = $this->user->licenses()->whereKey($id)->first();
        abort_unless($licence, 404, 'Licence not found');

        return $licence;
    }

    /**
     * @return array{licence: License, key: string, code_sent: bool}
     */
    public function create(string $name): array
    {
        $this->ensureWithinLimit();
        $this->ensureDeliverable();

        // Valid for the policy's validity (custom license, plan or global default), or until revoked.
        $policy = PolicyResolver::for($this->user);
        $expires = $policy['expires_on'] ?? now()->addMonthsNoOverflow($policy['validity_months']);
        $new = $this->user->createToken(trim($name), [License::ABILITY], $expires);
        $licence = License::findOrFail($new->accessToken->id);
        // Hide Sanctum's "<id>|" prefix: users only see "atg_...". Sanctum finds such keys by hash.
        $key = Str::after($new->plainTextToken, '|');
        $licence->rememberPlainKey($key);

        $sent = $this->emailCode($licence, LicenseCode::CONFIRM, failHard: false);

        return ['licence' => $licence, 'key' => $key, 'code_sent' => $sent];
    }

    public function sendConfirmCode(License $licence): void
    {
        abort_if($licence->isConfirmed(), 422, 'This licence is already confirmed.');
        $this->emailCode($licence, LicenseCode::CONFIRM);
    }

    /** Enter the emailed code: activates the licence and verifies the email address. */
    public function confirm(License $licence, string $code): License
    {
        $result = LicenseCode::check($this->user, (string) $licence->id, $code, LicenseCode::CONFIRM);
        if ($result !== 'ok') {
            throw ValidationException::withMessages(['code' => LicenseCode::errorMessage($result)]);
        }

        // The code reached the user's inbox, which proves they own the email address.
        if (! $this->user->hasVerifiedEmail()) {
            $this->user->markEmailAsVerified();
        }

        $licence->confirm();

        return $licence->fresh(['activation', 'tokenable']);
    }

    public function sendRevokeCode(License $licence): void
    {
        $until = self::lockedUntil($licence);
        if ($until !== null) {
            abort(409, 'This licence is in use and cannot be revoked until it expires ('.$until->format('M j, Y').').');
        }
        $this->emailCode($licence, LicenseCode::REVOKE);
    }

    /**
     * A licence in use (key activated by a console, or a licence file downloaded) works offline
     * until it expires, so the user cannot revoke it before then: that would free a slot while
     * the console keeps running. AtGlance Support can still remove it.
     */
    public static function lockedUntil(License $licence): ?\Illuminate\Support\Carbon
    {
        if ($licence->isExpired() || ($licence->activation === null && ! $licence->files()->exists())) {
            return null;
        }
        // The console now runs a newer licence's file: the old licence no longer holds it.
        if (self::replacedBy($licence) !== null) {
            return null;
        }

        return $licence->expires_at ?? now()->addYears(100);
    }

    /**
     * The newer licence of the same user whose licence file was issued for the same Installation
     * ID after this licence's latest file. A console holds one licence file at a time, so the
     * newer file replaced this one on that console and this licence may be revoked. Files
     * without an Installation ID never replace anything: they could be on any console.
     */
    public static function replacedBy(License $licence): ?License
    {
        $file = $licence->files()->whereNotNull('install_id')->latest('issued_at')->latest('id')->first();
        if ($file === null) {
            return null;
        }

        $newer = \App\Models\LicenseFile::query()
            ->where('user_id', $file->user_id)
            ->where('install_id', $file->install_id)
            ->whereNotNull('token_id')
            ->where('token_id', '!=', $licence->id)
            ->where(fn ($q) => $q->where('issued_at', '>', $file->issued_at)
                ->orWhere(fn ($q) => $q->where('issued_at', $file->issued_at)->where('id', '>', $file->id)))
            ->latest('issued_at')->latest('id')
            ->first();

        return $newer ? License::find($newer->token_id) : null;
    }

    /** Revoke with the account password, or the emailed revoke code. */
    public function revoke(License $licence, ?string $password, ?string $code): void
    {
        $until = self::lockedUntil($licence);
        if ($until !== null) {
            abort(409, 'This licence is in use and cannot be revoked until it expires ('.$until->format('M j, Y').'). Contact AtGlance Support if you need help.');
        }

        if (filled($password)) {
            if (! $this->user->password || ! Hash::check($password, $this->user->password)) {
                throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
            }
        } elseif (filled($code)) {
            $result = LicenseCode::check($this->user, (string) $licence->id, $code, LicenseCode::REVOKE);
            if ($result !== 'ok') {
                throw ValidationException::withMessages(['code' => LicenseCode::errorMessage($result)]);
            }
        } else {
            throw ValidationException::withMessages(['password' => 'Enter your password or the code we emailed you.']);
        }

        $licence->delete();
    }

    /** The plain key, for KEY_VISIBLE_MINUTES after creation. */
    public function plainKey(License $licence): string
    {
        if (! $licence->keyIsVisible()) {
            License::forgetExpiredKeys();
            abort(410, 'This licence key can no longer be shown (it is viewable for '.License::KEY_VISIBLE_MINUTES
                .' minutes after creation). Revoke this licence and create a new one if you lost the key.');
        }

        return Str::after($licence->plain_key, '|'); // keys stored before the prefix was dropped
    }

    public function atLimit(): bool
    {
        return $this->user->licenses()->count() >= $this->user->licenseLimit();
    }

    /** Shown when the licence limit is reached: only AtGlance Support raises it. */
    public static function limitMessage(int $limit): string
    {
        return "Your account can hold {$limit} ".($limit === 1 ? 'licence' : 'licences')
            .'. To increase the limit, contact the AtGlance Support team. You can also revoke a licence you no longer use.';
    }

    /** User-facing message after a successful confirm(). */
    public static function confirmedMessage(License $licence): string
    {
        return $licence->needsApproval()
            ? 'Licence verified. An AtGlance admin will review it before it can be used.'
            : 'Licence verified and active. Enter the key in the Management Console installer.';
    }

    public function codeSentMessage(): string
    {
        return "We sent a 5-digit code to {$this->user->email}.";
    }

    private function emailCode(License $licence, string $purpose, bool $failHard = true): bool
    {
        $this->ensureDeliverable();

        $code = LicenseCode::issue($this->user, (string) $licence->id, $purpose);

        try {
            $this->user->notify(new LicenseCodeNotification($code, $licence->name, LicenseCode::TTL_MINUTES, $purpose));

            return true;
        } catch (Throwable $e) {
            Log::error('Could not send licence code', ['user' => $this->user->id, 'purpose' => $purpose, 'error' => $e->getMessage()]);
            abort_if($failHard, 503, 'Could not send the verification code. Please try again later.');

            return false;
        }
    }

    private function ensureDeliverable(): void
    {
        abort_unless(
            $this->user->hasDeliverableEmail(),
            422,
            'Your account has no deliverable email address. Add a public email on GitHub and sign in again.'
        );
    }

    private function ensureWithinLimit(): void
    {
        $limit = $this->user->licenseLimit();

        abort_if(
            $this->atLimit(),
            403,
            self::limitMessage($limit)
        );
    }
}
