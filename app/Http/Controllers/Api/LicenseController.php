<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\User;
use App\Notifications\LicenseCodeNotification;
use App\Support\LicenseCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Management Console licences. A user creates a licence on the dashboard and pastes
 * it into the self-hosted Console installer, which confirms it through verify().
 * How many licences a user may hold depends on their plan (config atglance.plans).
 * Creating a licence shows the key (viewable for 30 minutes) and emails a 5-digit code. The
 * licence becomes usable once the user enters the code (confirm); users flagged with
 * `license_requires_approval` additionally need an admin approval.
 */
class LicenseController extends Controller
{
    public const ABILITY = License::ABILITY;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        License::forgetExpiredKeys();
        $licenses = $this->licenses($user)->with('activation', 'tokenable')->latest('id')->get();

        return response()->json([
            ...$this->planInfo($user),
            'licenses' => $licenses->map(fn (License $license) => $this->present($license)),
        ]);
    }

    /**
     * Create a licence, return the key (viewable again for 30 minutes) and email the
     * 5-digit code that activates it.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $user = $request->user();
        $name = trim($data['name']);

        $this->ensureWithinLimit($user);
        $this->ensureDeliverable($user);

        // Licences do not expire; they stay valid until the user revokes them.
        $new = $user->createToken($name, [self::ABILITY]);
        $license = License::findOrFail($new->accessToken->id);
        // Hide Sanctum's "<id>|" prefix: users only see "atg_...". Sanctum finds such keys by hash.
        $key = Str::after($new->plainTextToken, '|');
        $license->rememberPlainKey($key);

        $sent = $this->emailCode($user, $license, LicenseCode::CONFIRM, failHard: false);

        return response()->json([
            ...$this->present($license),
            'key' => $key,
            'code_sent' => $sent,
            'message' => $sent
                ? "We sent a 5-digit code to {$user->email}. Enter it to activate this licence."
                : 'We could not send the verification email. Use "Resend code" to try again.',
        ], 201);
    }

    /**
     * Email a new confirmation code for an unconfirmed licence request.
     */
    public function requestConfirmCode(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $license = $this->licenses($user)->whereKey($id)->first();
        abort_unless($license, 404, 'Licence not found');
        abort_if($license->isConfirmed(), 422, 'This licence is already confirmed.');

        $this->emailCode($user, $license, LicenseCode::CONFIRM);

        return $this->codeSentResponse($user);
    }

    /**
     * Enter the emailed code: activates the licence and verifies the email address.
     */
    public function confirm(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:5']]);
        $user = $request->user();
        $license = $this->licenses($user)->whereKey($id)->first();
        abort_unless($license, 404, 'Licence not found');

        $result = LicenseCode::check($user, (string) $license->id, $data['code'], LicenseCode::CONFIRM);
        if ($result !== 'ok') {
            throw ValidationException::withMessages(['code' => LicenseCode::errorMessage($result)]);
        }

        // The code reached the user's inbox, which proves they own the email address.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $license->confirm();

        $license = $license->fresh(['activation', 'tokenable']);

        return response()->json([
            ...$this->present($license),
            'message' => $license->needsApproval()
                ? 'Licence verified. An AtGlance admin will review it before it can be used.'
                : 'Licence verified and active. Enter the key in the Management Console installer.',
        ]);
    }

    /**
     * Email a 5-digit code that confirms revoking licence {id}.
     */
    public function requestRevokeCode(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $license = $this->licenses($user)->whereKey($id)->first();
        abort_unless($license, 404, 'Licence not found');

        $this->emailCode($user, $license, LicenseCode::REVOKE);

        return $this->codeSentResponse($user);
    }

    /**
     * Show the plain licence key again, for KEY_VISIBLE_MINUTES after creation.
     */
    public function showKey(Request $request, int $id): JsonResponse
    {
        $license = $this->licenses($request->user())->whereKey($id)->first();
        abort_unless($license, 404, 'Licence not found');

        if (! $license->keyIsVisible()) {
            License::forgetExpiredKeys();
            abort(410, $this->keyGoneMessage());
        }

        return response()->json([
            'id' => $license->id,
            'name' => $license->name,
            'key' => Str::after($license->plain_key, '|'), // keys stored before the prefix was dropped
            'key_visible_until' => $license->key_visible_until->toIso8601String(),
        ]);
    }

    /**
     * Revoke a licence. Needs the account password, or the emailed revoke code.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required_without:code', 'nullable', 'string'],
            'code' => ['required_without:password', 'nullable', 'digits:5'],
        ], [
            'password.required_without' => 'Enter your password or the code we emailed you.',
            'code.required_without' => 'Enter your password or the code we emailed you.',
        ]);

        $user = $request->user();
        $license = $this->licenses($user)->whereKey($id)->first();
        abort_unless($license, 404, 'Licence not found');

        if (filled($data['password'] ?? null)) {
            if (! $user->password || ! Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
            }
        } else {
            $result = LicenseCode::check($user, (string) $license->id, $data['code'], LicenseCode::REVOKE);
            if ($result !== 'ok') {
                throw ValidationException::withMessages(['code' => LicenseCode::errorMessage($result)]);
            }
        }

        $license->delete();

        return response()->json(['ok' => true]);
    }

    private function licenses(User $user)
    {
        return $user->licenses();
    }

    /**
     * Issue and email a 5-digit code for this licence. Returns false (or aborts with 503
     * when $failHard) if the email could not be sent.
     */
    private function emailCode(User $user, License $license, string $purpose, bool $failHard = true): bool
    {
        $this->ensureDeliverable($user);

        $code = LicenseCode::issue($user, (string) $license->id, $purpose);

        try {
            $user->notify(new LicenseCodeNotification($code, $license->name, LicenseCode::TTL_MINUTES, $purpose));

            return true;
        } catch (Throwable $e) {
            Log::error('Could not send licence code', ['user' => $user->id, 'purpose' => $purpose, 'error' => $e->getMessage()]);
            abort_if($failHard, 503, 'Could not send the verification code. Please try again later.');

            return false;
        }
    }

    private function codeSentResponse(User $user): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => "We sent a 5-digit code to {$user->email}.",
            'expires_in_minutes' => LicenseCode::TTL_MINUTES,
        ]);
    }

    private function ensureDeliverable(User $user): void
    {
        abort_unless(
            $user->hasDeliverableEmail(),
            422,
            'Your account has no deliverable email address. Add a public email on GitHub and sign in again.'
        );
    }

    private function keyGoneMessage(): string
    {
        return 'This licence key can no longer be shown (it is viewable for '.License::KEY_VISIBLE_MINUTES
            .' minutes after creation). Revoke this licence and create a new one if you lost the key.';
    }

    private function ensureWithinLimit(User $user): void
    {
        $limit = $user->licenseLimit();

        abort_if(
            $limit !== null && $this->licenses($user)->count() >= $limit,
            403,
            "Your {$user->planLabel()} plan includes {$limit} ".($limit === 1 ? 'licence' : 'licences')
                .'. Revoke an existing licence or upgrade your plan.'
        );
    }

    private function planInfo(User $user): array
    {
        return [
            'plan' => $user->plan,
            'plan_label' => $user->planLabel(),
            'limit' => $user->licenseLimit(),
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }

    private function present(License $license): array
    {
        $status = $license->status();

        return [
            'id' => $license->id,
            'name' => $license->name,
            'status' => $status,
            'status_label' => License::STATUS_LABELS[$status],
            'approved' => $license->isApproved(),
            'requires_approval' => $license->requiresApproval(),
            'approved_at' => $license->approved_at?->toIso8601String(),
            'created_at' => $license->created_at?->toIso8601String(),
            'last_used_at' => $license->last_used_at?->toIso8601String(),
            'in_use' => $status === License::IN_USE,
            'key_visible_until' => $license->keyIsVisible() ? $license->key_visible_until->toIso8601String() : null,
            'confirmed' => $license->isConfirmed(),
            // The user has not entered the emailed verification code yet.
            'awaiting_code' => ! $license->isConfirmed(),
            'console' => $license->activation?->toConsoleArray(),
        ];
    }
}
