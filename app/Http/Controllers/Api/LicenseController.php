<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\LicenseCodeNotification;
use App\Support\LicenseCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * Management Console licences. A user creates a licence on the dashboard and pastes
 * it into the self-hosted Console installer, which confirms it through verify().
 * How many licences a user may hold depends on their plan (config atglance.plans).
 * Creating a licence requires a one-time code emailed to the user (requestCode, then store).
 */
class LicenseController extends Controller
{
    public const ABILITY = 'console:license';

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            ...$this->planInfo($user),
            'licenses' => $this->licenses($user)->latest('id')->get()->map(fn (PersonalAccessToken $t) => $this->present($t)),
        ]);
    }

    /**
     * Step 1: email a 5-digit code to the user. Licence creation needs that code (step 2).
     */
    public function requestCode(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $user = $request->user();
        $name = trim($data['name']);

        $this->ensureWithinLimit($user);

        return $this->emailCode($user, $name, $name, LicenseCode::CREATE);
    }

    /**
     * Step 2: create the licence after the emailed code is confirmed.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'digits:5'],
        ]);

        $user = $request->user();
        $name = trim($data['name']);
        $this->ensureWithinLimit($user);

        $result = LicenseCode::check($user, $name, $data['code']);
        if ($result !== 'ok') {
            throw ValidationException::withMessages(['code' => LicenseCode::errorMessage($result)]);
        }

        // The code reached the user's inbox, which proves they own the email address.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // Licences do not expire; they stay valid until the user revokes them.
        $new = $user->createToken($name, [self::ABILITY]);

        return response()->json([
            ...$this->present($new->accessToken),
            // Only time the plain-text licence key is ever returned.
            'key' => $new->plainTextToken,
        ], 201);
    }

    /**
     * Email a 5-digit code that confirms revoking licence {id}.
     */
    public function requestRevokeCode(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $license = $this->licenses($user)->whereKey($id)->first();
        abort_unless($license, 404, 'Licence not found');

        return $this->emailCode($user, (string) $license->id, $license->name, LicenseCode::REVOKE);
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

    /**
     * Called by the Management Console installer with "Authorization: Bearer <licence key>".
     */
    public function verify(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        // Reject browser sessions: only a real licence key may confirm an installation.
        if (! $token instanceof PersonalAccessToken || ! $token->can(self::ABILITY)) {
            return response()->json(['valid' => false, 'message' => 'Invalid licence'], 401);
        }

        $user = $request->user();

        return response()->json([
            'valid' => true,
            'user' => ['id' => (string) $user->id, 'email' => $user->email, 'name' => $user->name],
            'license' => ['name' => $token->name],
            'plan' => $user->plan,
        ]);
    }

    private function licenses(User $user)
    {
        return $user->tokens()->whereJsonContains('abilities', self::ABILITY);
    }

    private function emailCode(User $user, string $subject, string $licenseName, string $purpose): JsonResponse
    {
        abort_unless(
            $user->hasDeliverableEmail(),
            422,
            'Your account has no deliverable email address. Add a public email on GitHub and sign in again.'
        );

        $code = LicenseCode::issue($user, $subject, $purpose);

        try {
            $user->notify(new LicenseCodeNotification($code, $licenseName, LicenseCode::TTL_MINUTES, $purpose));
        } catch (Throwable $e) {
            Log::error('Could not send licence code', ['user' => $user->id, 'purpose' => $purpose, 'error' => $e->getMessage()]);
            abort(503, 'Could not send the verification code. Please try again later.');
        }

        return response()->json([
            'ok' => true,
            'message' => "We sent a 5-digit code to {$user->email}.",
            'expires_in_minutes' => LicenseCode::TTL_MINUTES,
        ]);
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

    private function present(PersonalAccessToken $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'created_at' => $t->created_at?->toIso8601String(),
            'last_used_at' => $t->last_used_at?->toIso8601String(),
        ];
    }
}
