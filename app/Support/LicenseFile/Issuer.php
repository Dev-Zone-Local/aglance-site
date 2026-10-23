<?php

namespace App\Support\LicenseFile;

use App\Models\LicenseFile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Issues an offline licence file (.atgl) for a signed-in user. Every value in the file
 * comes from the user's account and plan; the only input is the optional Installation ID.
 *
 * Failures: HttpException via abort() (not allowed / not configured) or
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
     * @return array{bytes: string, license: LicenseFile}
     */
    public function issue(User $user, ?string $installId = null): array
    {
        $this->ensureAllowed($user);

        $installId = $installId !== null ? strtolower(trim($installId)) : '';
        if ($installId !== '' && ! Str::isUuid($installId)) {
            throw ValidationException::withMessages(['install_id' => 'The Installation ID is the UUID shown in the Management Console installer.']);
        }

        $plans = (array) config('atglance.plans');
        $planCode = array_key_exists($user->plan, $plans) ? $user->plan : config('atglance.default_plan');
        $plan = $plans[$planCode];
        $now = now();

        $payload = PayloadBuilder::build(
            licenseId: 'LIC-'.Str::uuid(),
            keyId: $this->packer->keyId(),
            customer: $user->name !== '' ? $user->name : $user->email,
            email: $user->email,
            plan: $planCode,
            seats: (int) ($plan['seats'] ?? 1),
            features: (array) ($plan['features'] ?? []),
            validDays: (int) ($plan['valid_days'] ?? 365),
            minVersion: (string) config('atglance.license_file.min_version', '2.3.0'),
            installId: $installId !== '' ? $installId : null,
            now: $now,
        );

        $bytes = $this->packer->pack(PayloadBuilder::encode($payload));

        $license = DB::transaction(fn () => LicenseFile::create([
            'license_id' => $payload['license_id'],
            'user_id' => $user->id,
            'org' => $payload['customer'],
            'plan' => $payload['plan'],
            'seats' => $payload['seats'],
            'features' => $payload['features'],
            'issued_at' => self::localTime($payload['issued_at']),
            'activate_before' => self::localTime($payload['activate_before']),
            'expires_at' => self::localTime($payload['expires_at']),
            'key_id' => $payload['key_id'],
            'install_id' => $payload['install_id'],
        ]));

        return ['bytes' => $bytes, 'license' => $license];
    }

    /** A payload date (UTC) in the app timezone, so the stored row matches the file. */
    private static function localTime(string $date): Carbon
    {
        return Carbon::parse($date)->setTimezone(config('app.timezone'));
    }

    /** Verified, deliverable email; not waiting for an admin review; within the daily cap. */
    private function ensureAllowed(User $user): void
    {
        abort_unless($user->hasVerifiedEmail(), 403, 'Verify your email address before generating a licence file.');
        abort_unless($user->hasDeliverableEmail(), 422, 'Your account has no deliverable email address. Add a public email on GitHub and sign in again.');
        abort_if($user->license_requires_approval, 403, 'Your account needs an AtGlance admin review before it can generate licence files. Contact support.');

        $max = (int) config('atglance.license_file.max_per_day', 20);
        $today = LicenseFile::query()->where('user_id', $user->id)->where('issued_at', '>=', now()->subDay())->count();
        abort_if($today >= $max, 429, "You can generate {$max} licence files per day. Try again tomorrow.");
    }
}
