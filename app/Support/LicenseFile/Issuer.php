<?php

namespace App\Support\LicenseFile;

use App\Models\LicenseFile;
use App\Models\User;
use App\Notifications\LicenseCodeNotification;
use App\Support\LicenseCode;
use App\Support\LicensePolicy\PolicyResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Issues a Management Console licence file (.atgl, payload v2) for a signed-in user. Licences
 * are files only. Everything comes from the user's resolved policy (custom license, then plan
 * default, then global default):
 *
 * - limits, features, validity and activation window go into the file;
 * - "Licence file limit": files count until they expire (they cannot be revoked offline), so a
 *   user cannot free a slot by deleting anything; only an admin can release one early;
 * - "Email verification": a 5-digit code emailed to the account must be entered first;
 * - "Installation ID": required or optional; with one, the file works on that console only.
 *
 * Failures: HttpException via abort() (not allowed, limit reached, not configured) or
 * ValidationException (Installation ID, code).
 */
class Issuer
{
    public function __construct(private readonly Packer $packer) {}

    /** Packer from config('atglance.license_file'); 503 when the keys are missing or malformed. */
    public static function fromConfig(): self
    {
        $config = (array) config('atglance.license_file');
        try {
            $packer = Packer::fromEncoded(
                (string) ($config['key_id'] ?? ''),
                (string) ($config['signing_key'] ?? ''),
                (string) ($config['enc_key'] ?? ''),
            );
        } catch (InvalidArgumentException) {
            abort(503, 'Licence files cannot be generated right now. Please try again later.');
        }

        return new self($packer);
    }

    /** Files of $user that count toward the limit: not expired and not released by an admin. */
    public static function activeCount(User $user): int
    {
        return LicenseFile::query()->where('user_id', $user->id)->active()->count();
    }

    /** Shown when the limit is reached: only an admin (AtGlance Support) changes it. */
    public static function limitMessage(int $limit): string
    {
        return "Your account can hold {$limit} active licence ".($limit === 1 ? 'file' : 'files')
            .'. A file counts until it expires. To increase the limit, contact the AtGlance Support team.';
    }

    /** Emails the 5-digit code a file generation needs when the policy requires it. */
    public static function sendCode(User $user): void
    {
        self::ensureAllowed($user, PolicyResolver::for($user));
        abort_unless($user->hasDeliverableEmail(), 422, 'Your account has no deliverable email address. Add a public email on GitHub and sign in again.');

        $code = LicenseCode::issue($user, 'file', LicenseCode::FILE);
        try {
            $user->notify(new LicenseCodeNotification($code, LicenseCode::TTL_MINUTES));
        } catch (Throwable $e) {
            Log::error('Could not send licence file code', ['user' => $user->id, 'error' => $e->getMessage()]);
            abort(503, 'Could not send the verification code. Please try again later.');
        }
    }

    /**
     * @return array{bytes: string, license: LicenseFile}
     */
    public function issue(User $user, ?string $installId, ?string $code = null): array
    {
        $policy = PolicyResolver::for($user);
        self::ensureAllowed($user, $policy);

        $installId = strtolower(trim((string) $installId));
        if ($policy['install_id']) {
            // A custom license bound to one installation.
            if ($installId !== '' && $installId !== strtolower($policy['install_id'])) {
                throw ValidationException::withMessages(['install_id' => 'Your licence can only be used on the installation '.$policy['install_id'].'. Contact AtGlance Support to change it.']);
            }
            $installId = strtolower($policy['install_id']);
        } elseif ($installId === '' && $policy['install_id_mode'] === 'required') {
            throw ValidationException::withMessages(['install_id' => 'Enter the Installation ID shown in the Management Console (installer, or Admin Settings › Licence).']);
        }
        if ($installId !== '' && ! Str::isUuid($installId)) {
            throw ValidationException::withMessages(['install_id' => 'The Installation ID is the UUID shown in the Management Console (installer, or Admin Settings › Licence).']);
        }

        if ($policy['require_email_code']) {
            $result = LicenseCode::check($user, 'file', trim((string) $code), LicenseCode::FILE);
            if ($result !== 'ok') {
                throw ValidationException::withMessages(['code' => LicenseCode::errorMessage($result)]);
            }
        }

        $plans = (array) config('atglance.plans');
        $now = now();
        $payload = PayloadBuilder::buildV2(
            licenseId: 'LIC-'.Str::uuid(),
            keyId: $this->packer->keyId(),
            customer: $user->name !== '' ? $user->name : $user->email,
            email: $user->email,
            plan: array_key_exists($user->plan, $plans) ? $user->plan : (string) config('atglance.default_plan'),
            features: $policy['features'],
            maxNodes: $policy['max_nodes'],
            maxUsers: $policy['max_users'],
            policyId: $policy['policy_id'],
            policyType: $policy['policy_type'],
            validityMonths: $policy['validity_months'],
            activationWindowMinutes: $policy['activation_window_minutes'],
            minVersion: (string) config('atglance.license_file.min_version', '2.3.0'),
            installId: $installId !== '' ? $installId : null,
            now: $now,
            expiresAt: $policy['expires_on'],
        );

        $bytes = $this->packer->pack(PayloadBuilder::encode($payload));

        $file = LicenseFile::create([
            'format_version' => 2,
            'license_id' => $payload['license_id'],
            'user_id' => $user->id,
            'org' => $payload['customer'],
            'plan' => $payload['plan'],
            'max_nodes' => $payload['max_nodes'],
            'max_users' => $payload['max_users'],
            'features' => $payload['features'],
            'policy_id' => $payload['policy_id'],
            'policy_type' => $payload['policy_type'],
            'validity_months' => $payload['validity_months'],
            'issued_by' => 'user',
            'issued_at' => self::localTime($payload['issued_at']),
            'activate_before' => self::localTime($payload['activate_before']),
            'expires_at' => self::localTime($payload['expires_at']),
            'key_id' => $payload['key_id'],
            'install_id' => $payload['install_id'],
        ]);

        return ['bytes' => $bytes, 'license' => $file];
    }

    /**
     * Not flagged by an admin, and below the licence file limit.
     *
     * @param  array<string, mixed>  $policy
     */
    private static function ensureAllowed(User $user, array $policy): void
    {
        abort_if($user->license_requires_approval, 403, 'Licence file generation is blocked for your account. Contact AtGlance Support.');
        abort_if(self::activeCount($user) >= $policy['max_files'], 403, self::limitMessage($policy['max_files']));
    }

    /** A payload date (UTC) in the app timezone, so the stored row matches the file. */
    private static function localTime(string $date): Carbon
    {
        return Carbon::parse($date)->setTimezone(config('app.timezone'));
    }
}
