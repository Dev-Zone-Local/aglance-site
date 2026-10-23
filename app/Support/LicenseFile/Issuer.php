<?php

namespace App\Support\LicenseFile;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseFile;
use App\Models\User;
use App\Support\LicensePolicy\PolicyResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Issues a licence file (.atgl, payload v2) for one licence. The same file is what a console
 * gets when it activates the licence key online, and what the user downloads for an offline
 * console. Limits, features and activation window come from the owner's resolved policy (custom,
 * then plan default, then global default); the expiry is the licence's own expiry.
 *
 * One licence = one console: the file is bound to the console's Installation ID, and a licence
 * already in use by another console is refused.
 *
 * Failures: HttpException via abort() (licence not usable, in use elsewhere, not configured) or
 * ValidationException (bad Installation ID).
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

    /**
     * @param  string  $installId  the console's Installation ID
     * @param  string  $issuedBy  "user" (dashboard / API download) or "console" (online key activation)
     * @return array{bytes: string, license: LicenseFile}
     */
    public function issue(License $licence, string $installId, string $issuedBy = 'user'): array
    {
        /** @var User $user */
        $user = $licence->tokenable;
        $this->ensureUsable($licence, $user);

        $installId = strtolower(trim($installId));
        if ($issuedBy === 'user' && ! Str::isUuid($installId)) {
            throw ValidationException::withMessages(['install_id' => 'Enter the Installation ID shown in the Management Console (installer, or Admin Settings › Licence).']);
        }

        $policy = PolicyResolver::for($user);
        if ($policy['install_id'] && strtolower($policy['install_id']) !== $installId) {
            throw ValidationException::withMessages(['install_id' => 'Your licence can only be used on the installation '.$policy['install_id'].'. Contact AtGlance Support to change it.']);
        }

        $this->bindToConsole($licence, $installId);

        $plans = (array) config('atglance.plans');
        $now = now();
        // The licence's own expiry (set from the policy when it was created); older licences
        // without one get the policy's validity from today.
        $expiresAt = $licence->expires_at ?? ($policy['expires_on'] ?? null);

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
            validityMonths: $expiresAt ? PolicyResolver::monthsUntil($expiresAt) : $policy['validity_months'],
            activationWindowMinutes: $policy['activation_window_minutes'],
            minVersion: (string) config('atglance.license_file.min_version', '2.3.0'),
            installId: $installId,
            now: $now,
            expiresAt: $expiresAt,
        );

        $bytes = $this->packer->pack(PayloadBuilder::encode($payload));

        $file = LicenseFile::create([
            'format_version' => 2,
            'license_id' => $payload['license_id'],
            'user_id' => $user->id,
            'token_id' => $licence->id,
            'org' => $payload['customer'],
            'plan' => $payload['plan'],
            'max_nodes' => $payload['max_nodes'],
            'max_users' => $payload['max_users'],
            'features' => $payload['features'],
            'policy_id' => $payload['policy_id'],
            'policy_type' => $payload['policy_type'],
            'validity_months' => $payload['validity_months'],
            'issued_by' => $issuedBy,
            'issued_at' => self::localTime($payload['issued_at']),
            'activate_before' => self::localTime($payload['activate_before']),
            'expires_at' => self::localTime($payload['expires_at']),
            'key_id' => $payload['key_id'],
            'install_id' => $payload['install_id'],
        ]);

        return ['bytes' => $bytes, 'license' => $file];
    }

    /**
     * Marks the licence in use by this console (the same record an online activation writes).
     * A licence already used by another console is refused: one licence, one console.
     */
    private function bindToConsole(License $licence, string $installId): void
    {
        $activation = LicenseActivation::query()->where('token_id', $licence->id)->first();
        if ($activation === null) {
            try {
                DB::transaction(fn () => LicenseActivation::create([
                    'token_id' => $licence->id,
                    'instance_id' => $installId,
                    'activated_at' => now(),
                    'last_seen_at' => now(),
                ]));

                return;
            } catch (UniqueConstraintViolationException) {
                $activation = LicenseActivation::query()->where('token_id', $licence->id)->first();
            }
        }

        abort_if(
            strtolower((string) $activation->instance_id) !== $installId,
            409,
            'This licence is already in use by another Management Console. Revoke it and create a new licence to move to another console.'
        );
    }

    private function ensureUsable(License $licence, ?User $user): void
    {
        abort_unless($user instanceof User, 404, 'Licence not found');
        abort_unless($user->hasDeliverableEmail(), 422, 'Your account has no deliverable email address. Add a public email on GitHub and sign in again.');
        abort_if($licence->isExpired(), 403, 'This licence has expired. Create a new licence.');
        abort_unless($licence->isConfirmed(), 403, 'Confirm this licence with the code we emailed you first.');
        abort_if($licence->needsApproval(), 403, 'This licence is waiting for approval by an AtGlance admin.');
    }

    /** A payload date (UTC) in the app timezone, so the stored row matches the file. */
    private static function localTime(string $date): Carbon
    {
        return Carbon::parse($date)->setTimezone(config('app.timezone'));
    }
}
