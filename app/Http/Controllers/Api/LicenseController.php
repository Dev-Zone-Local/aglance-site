<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Management Console licences. A user creates a licence on the dashboard and pastes
 * it into the self-hosted Console installer, which confirms it through verify().
 * How many licences a user may hold depends on their plan (config atglance.plans).
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $limit = $user->licenseLimit();

        abort_if(
            $limit !== null && $this->licenses($user)->count() >= $limit,
            403,
            "Your {$user->planLabel()} plan includes {$limit} ".($limit === 1 ? 'licence' : 'licences')
                .'. Revoke an existing licence or upgrade your plan.'
        );

        // Licences do not expire; they stay valid until the user revokes them.
        $new = $user->createToken($data['name'], [self::ABILITY]);

        return response()->json([
            ...$this->present($new->accessToken),
            // Only time the plain-text licence key is ever returned.
            'key' => $new->plainTextToken,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = $this->licenses($request->user())->whereKey($id)->delete();
        abort_unless($deleted, 404, 'Licence not found');

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

    private function planInfo(User $user): array
    {
        return [
            'plan' => $user->plan,
            'plan_label' => $user->planLabel(),
            'limit' => $user->licenseLimit(),
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
