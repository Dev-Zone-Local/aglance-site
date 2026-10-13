<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Email a reset link. Always answers the same way, so it cannot be used to find out
     * which emails have an account.
     */
    public function email(Request $request): RedirectResponse
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

        return back()->with('sent', 'If an account exists for that email, we sent a link to reset your password.');
    }

    /** Target of the link in the reset email: /reset-password?token=...&email=... */
    public function edit(Request $request): View
    {
        return view('auth.reset-password', [
            'token' => (string) $request->query('token', ''),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'max:128', 'confirmed'],
        ], ['password.confirmed' => 'Passwords do not match']);

        $status = Password::reset(
            ['email' => Str::lower(trim($data['email'])), 'token' => $data['token'], 'password' => $data['password']],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                // The link reached the user's inbox, which proves they own the address.
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }

                // Sign out every session of this account.
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

        return redirect()->route('login')->with('status', 'Your password has been reset. You can sign in now.');
    }
}
