<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): UserResource
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:128', 'confirmed'],
            'name' => ['nullable', 'string', 'max:255'],
        ], [
            'email.unique' => 'Email already registered',
            'password.confirmed' => 'Passwords do not match',
        ]);

        // New accounts are always plain users; admin is only granted by the seeder or the admin panel.
        $user = User::create([
            'email' => $data['email'],
            'name' => ($data['name'] ?? null) ?: Str::before($data['email'], '@'),
            'password' => $data['password'],
            'auth_method' => 'email',
        ]);

        $user->sendVerificationSafely();

        Auth::login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }

    /**
     * Target of the link in the verification email. The signed URL proves it came from us,
     * so it works even if the user opens it in a different browser (not logged in).
     */
    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        $dashboard = rtrim(config('atglance.frontend_url'), '/').'/dashboard';

        if (! $request->hasValidSignature()) {
            return redirect()->away($dashboard.'?verified=expired');
        }

        $user = User::find($id);
        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()->away($dashboard.'?verified=invalid');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->away($dashboard.'?verified=1');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['ok' => true, 'message' => 'Email already verified']);
        }

        abort_unless($user->hasDeliverableEmail(), 422, 'Your account has no deliverable email address. Add a public email on GitHub and sign in again.');
        abort_unless($user->sendVerificationSafely(), 503, 'Could not send the verification email. Please try again later.');

        return response()->json(['ok' => true, 'message' => "Verification email sent to {$user->email}"]);
    }

    public function login(Request $request): UserResource
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials = [
            'email' => Str::lower(trim($data['email'])),
            'password' => $data['password'],
        ];

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password']);
        }

        $request->session()->regenerate();

        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
