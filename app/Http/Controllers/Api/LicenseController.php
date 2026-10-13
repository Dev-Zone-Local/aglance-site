<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\User;
use App\Support\LicenceManager;
use App\Support\LicenseCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Management Console licences as JSON (the rules live in App\Support\LicenceManager,
 * shared with the dashboard's Licences page).
 */
class LicenseController extends Controller
{
    public const ABILITY = License::ABILITY;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        License::forgetExpiredKeys();
        $licenses = $user->licenses()->with('activation', 'tokenable')->latest('id')->get();

        return response()->json([
            ...$this->planInfo($user),
            'licenses' => $licenses->map(fn (License $license) => $this->present($license)),
        ]);
    }

    /** Create a licence, return the key (viewable again for 30 minutes) and email the activation code. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $user = $request->user();

        ['licence' => $license, 'key' => $key, 'code_sent' => $sent] = LicenceManager::for($user)->create($data['name']);

        return response()->json([
            ...$this->present($license),
            'key' => $key,
            'code_sent' => $sent,
            'message' => $sent
                ? "We sent a 5-digit code to {$user->email}. Enter it to activate this licence."
                : 'We could not send the verification email. Use "Resend code" to try again.',
        ], 201);
    }

    public function requestConfirmCode(Request $request, int $id): JsonResponse
    {
        $manager = LicenceManager::for($request->user());
        $manager->sendConfirmCode($manager->find($id));

        return $this->codeSentResponse($manager);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:5']]);
        $manager = LicenceManager::for($request->user());
        $license = $manager->confirm($manager->find($id), $data['code']);

        return response()->json([
            ...$this->present($license),
            'message' => LicenceManager::confirmedMessage($license),
        ]);
    }

    public function requestRevokeCode(Request $request, int $id): JsonResponse
    {
        $manager = LicenceManager::for($request->user());
        $manager->sendRevokeCode($manager->find($id));

        return $this->codeSentResponse($manager);
    }

    public function showKey(Request $request, int $id): JsonResponse
    {
        $manager = LicenceManager::for($request->user());
        $license = $manager->find($id);
        $key = $manager->plainKey($license);

        return response()->json([
            'id' => $license->id,
            'name' => $license->name,
            'key' => $key,
            'key_visible_until' => $license->key_visible_until->toIso8601String(),
        ]);
    }

    /** Revoke a licence. Needs the account password, or the emailed revoke code. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required_without:code', 'nullable', 'string'],
            'code' => ['required_without:password', 'nullable', 'digits:5'],
        ], [
            'password.required_without' => 'Enter your password or the code we emailed you.',
            'code.required_without' => 'Enter your password or the code we emailed you.',
        ]);

        $manager = LicenceManager::for($request->user());
        $manager->revoke($manager->find($id), $data['password'] ?? null, $data['code'] ?? null);

        return response()->json(['ok' => true]);
    }

    private function codeSentResponse(LicenceManager $manager): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => $manager->codeSentMessage(),
            'expires_in_minutes' => LicenseCode::TTL_MINUTES,
        ]);
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
            'expires_at' => $license->expires_at?->toIso8601String(),
            'expired' => $status === License::EXPIRED,
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
