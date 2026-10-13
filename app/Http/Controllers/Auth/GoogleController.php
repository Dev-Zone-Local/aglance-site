<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\GoogleOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * "Sign in with Google" on the website (Settings → Google login). Socialite keeps the OAuth
 * state in the session and checks it on the callback. Only emails Google has verified are
 * accepted, and Google sign-in never grants admin.
 */
class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        abort_unless(GoogleOAuth::enabled(), 404);
        GoogleOAuth::apply();

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! GoogleOAuth::enabled()) {
            return $this->fail('Google sign-in is not configured.');
        }
        if ($request->filled('error')) {
            return $this->fail('Google sign-in was cancelled.');
        }
        GoogleOAuth::apply();

        try {
            $google = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->fail('Invalid OAuth state. Please start the sign-in again.');
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Google sign-in failed. Please try again.');
        }

        $email = $google->getEmail() ? Str::lower($google->getEmail()) : null;
        $verified = filter_var($google->getRaw()['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $email || ! $verified) {
            return $this->fail('Your Google account email is not verified. Verify it with Google or sign in with your password.');
        }

        $user = User::where('google_id', $google->getId())->first() ?? User::where('email', $email)->first();

        if ($user) {
            $user->fill([
                'google_id' => $google->getId(),
                'avatar_url' => $user->avatar_url ?: $google->getAvatar(),
                'name' => $user->name ?: $google->getName(),
                'auth_method' => $user->auth_method ?: 'google',
            ]);
            // Google confirmed the address, so the account email is verified too.
            $user->email_verified_at ??= now();
            $user->save();
        } else {
            // SSO never grants admin; role defaults to "user".
            $user = User::create([
                'email' => $email,
                'name' => $google->getName() ?: Str::before($email, '@'),
                'google_id' => $google->getId(),
                'avatar_url' => $google->getAvatar(),
                'auth_method' => 'google',
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return LoginController::home($user, intended: true);
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}
