<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints called by the self-hosted AtGlance Management Console, authenticated with
 * "Authorization: Bearer <licence key>". A licence works once the owner has entered the
 * emailed code (and, for flagged users, an admin approved it), on exactly one console
 * instance: the first console to activate it owns it until the licence is revoked.
 */
class ConsoleLicenseController extends Controller
{
    /**
     * Verify the key and mark the licence "In Use" for this console (same as activate()).
     * The first console to verify owns the licence; others get 409.
     */
    public function verify(Request $request): JsonResponse
    {
        return $this->activate($request);
    }

    /**
     * Bind the licence to this console. Idempotent for the same console identity;
     * 409 when another console already uses the licence.
     */
    public function activate(Request $request): JsonResponse
    {
        $token = $this->licenseToken($request);
        if (! $token) {
            return $this->invalid();
        }
        if (! $token->isUsable()) {
            return $this->notUsable($token);
        }

        $data = $this->validateConsole($request);
        $activation = $this->activation($token);

        if (! $activation) {
            try {
                $activation = LicenseActivation::create([
                    'token_id' => $token->id,
                    'instance_id' => $data['instance_id'],
                    'hostname' => $data['hostname'] ?? null,
                    'console_version' => $data['version'] ?? null,
                    'ip_address' => $request->ip(),
                    'activated_at' => now(),
                    'last_seen_at' => now(),
                ]);

                return response()->json($this->payload($request, $token, $activation), 201);
            } catch (UniqueConstraintViolationException) {
                // Another console activated the same licence at the same moment.
                $activation = $this->activation($token);
            }
        }

        if ($activation->instance_id !== $data['instance_id']) {
            return $this->inUseElsewhere($activation);
        }

        $this->touch($request, $activation, $data);

        return response()->json($this->payload($request, $token, $activation));
    }

    /**
     * Periodic "still alive" call from the activated console. Records last seen.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $token = $this->licenseToken($request);
        if (! $token) {
            return $this->invalid();
        }

        $data = $this->validateConsole($request);
        $activation = $this->activation($token);

        if (! $activation) {
            return response()->json([
                'valid' => false,
                'status' => 'not_activated',
                'message' => 'This licence is not activated. Activate it first.',
            ], 409);
        }

        if ($activation->instance_id !== $data['instance_id']) {
            return $this->inUseElsewhere($activation);
        }

        $this->touch($request, $activation, $data);

        return response()->json([
            'valid' => true,
            'status' => 'in_use',
            'last_seen_at' => $activation->last_seen_at->toIso8601String(),
        ]);
    }

    private function licenseToken(Request $request): ?License
    {
        $token = $request->user()?->currentAccessToken();

        // Browser sessions (TransientToken) and tokens without the licence ability are rejected.
        return $token instanceof License && $token->can(License::ABILITY) ? $token : null;
    }

    private function activation(License $token): ?LicenseActivation
    {
        return LicenseActivation::where('token_id', $token->id)->first();
    }

    /**
     * Validate the console details and resolve its identity: instance_id when sent
     * (recommended), otherwise the hostname, otherwise the caller's IP address.
     */
    private function validateConsole(Request $request): array
    {
        $data = $request->validate([
            'instance_id' => ['nullable', 'string', 'min:8', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:50'],
        ]);

        $data['instance_id'] = $data['instance_id']
            ?? (filled($data['hostname'] ?? null) ? 'host:'.mb_substr($data['hostname'], 0, 95) : 'ip:'.$request->ip());

        return $data;
    }

    private function touch(Request $request, LicenseActivation $activation, array $data): void
    {
        $activation->fill(array_filter([
            'hostname' => $data['hostname'] ?? null,
            'console_version' => $data['version'] ?? null,
        ]))->fill([
            'ip_address' => $request->ip(),
            'last_seen_at' => now(),
        ])->save();
    }

    private function payload(Request $request, License $token, ?LicenseActivation $activation): array
    {
        $user = $request->user();

        return [
            'valid' => true,
            'status' => $activation ? 'in_use' : 'available',
            'user' => ['id' => (string) $user->id, 'email' => $user->email, 'name' => $user->name],
            'license' => ['name' => $token->name],
            'plan' => $user->plan,
            'console' => $activation?->toConsoleArray(),
        ];
    }

    /** 403 for a licence that is not verified with the emailed code, or still awaits an admin. */
    private function notUsable(License $license): JsonResponse
    {
        $status = $license->status();

        return response()->json([
            'valid' => false,
            'status' => $status,
            'message' => $status === License::UNVERIFIED
                ? 'This licence is not verified yet. Enter the 5-digit code we emailed you on your AtGlance dashboard.'
                : 'This licence is under review. It can be used once an AtGlance admin approves it.',
        ], 403);
    }

    private function invalid(): JsonResponse
    {
        return response()->json(['valid' => false, 'message' => 'Invalid licence'], 401);
    }

    private function inUseElsewhere(LicenseActivation $activation): JsonResponse
    {
        return response()->json([
            'valid' => false,
            'status' => 'in_use_elsewhere',
            'message' => 'This licence is already in use by another Management Console'
                .($activation->hostname ? " ({$activation->hostname})" : '')
                .'. Revoke it and create a new licence to move it.',
        ], 409);
    }
}
