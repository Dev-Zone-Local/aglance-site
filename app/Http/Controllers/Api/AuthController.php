<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

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

    /**
     * Email a password reset link. Always answers the same way, so it cannot be used
     * to find out which emails have an account.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = Str::lower(trim($data['email']));

        $user = User::firstWhere('email', $email);
        if ($user?->hasDeliverableEmail()) {
            try {
                Password::sendResetLink(['email' => $email]);
            } catch (Throwable $e) {
                Log::error('Could not send password reset email', ['user' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'If an account exists for that email, we sent a link to reset your password.',
        ]);
    }

    /**
     * Set a new password with the token from the reset email.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'max:128', 'confirmed'],
        ], [
            'password.confirmed' => 'Passwords do not match',
        ]);

        $status = Password::reset(
            ['email' => Str::lower(trim($data['email'])), 'token' => $data['token'], 'password' => $data['password']],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                // The link reached the user's inbox, which proves they own the address.
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }

                // Sign out every other session of this account.
                DB::table('sessions')->where('user_id', $user->id)->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => $status === Password::INVALID_USER
                    ? 'This reset link is not valid. Request a new one.'
                    : 'This reset link is invalid or has expired. Request a new one.',
            ]);
        }

        return response()->json(['ok' => true, 'message' => 'Your password has been reset. You can sign in now.']);
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
