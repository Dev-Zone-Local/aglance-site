<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Install tokens let a user prove ownership while installing the self-hosted
 * AtGlance Management Console. The installer calls verify() with the token.
 */
class InstallTokenController extends Controller
{
    public const ABILITY = 'console:install';

    public const MAX_ACTIVE = 10;

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->tokens($request)->latest('id')->get()->map(fn (PersonalAccessToken $t) => $this->present($t))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        abort_if(
            $this->tokens($request)->count() >= self::MAX_ACTIVE,
            422,
            'You can have at most '.self::MAX_ACTIVE.' install tokens. Revoke one first.'
        );

        // Install tokens do not expire; they stay valid until the user revokes them.
        $new = $request->user()->createToken($data['name'], [self::ABILITY]);

        return response()->json([
            ...$this->present($new->accessToken),
            // Only time the plain-text token is ever returned.
            'token' => $new->plainTextToken,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = $this->tokens($request)->whereKey($id)->delete();
        abort_unless($deleted, 404, 'Token not found');

        return response()->json(['ok' => true]);
    }

    /**
     * Called by the Management Console installer with "Authorization: Bearer <token>".
     */
    public function verify(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        // Reject browser sessions: only a real install token may confirm an installation.
        if (! $token instanceof PersonalAccessToken || ! $token->can(self::ABILITY)) {
            return response()->json(['valid' => false, 'message' => 'Invalid install token'], 401);
        }

        $user = $request->user();

        return response()->json([
            'valid' => true,
            'user' => ['id' => (string) $user->id, 'email' => $user->email, 'name' => $user->name],
            'token' => ['name' => $token->name],
        ]);
    }

    private function tokens(Request $request)
    {
        return $request->user()->tokens()->whereJsonContains('abilities', self::ABILITY);
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
