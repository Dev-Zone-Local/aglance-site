<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\GithubOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * "Sign in with GitHub" for the website and the admin panel. Socialite keeps the OAuth
 * state in the session and checks it on the callback.
 */
class GithubController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        abort_unless(GithubOAuth::enabled(), 404);
        GithubOAuth::apply();

        return Socialite::driver('github')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! GithubOAuth::enabled()) {
            return $this->fail('GitHub sign-in is not configured.');
        }
        if ($request->filled('error')) {
            return $this->fail('GitHub sign-in was cancelled.');
        }
        GithubOAuth::apply();

        try {
            $gh = Socialite::driver('github')->user();
        } catch (InvalidStateException) {
            return $this->fail('Invalid OAuth state. Please start the sign-in again.');
        } catch (Throwable $e) {
            report($e);

            return $this->fail('GitHub sign-in failed. Please try again.');
        }

        // Socialite returns the public profile email or the primary verified email, never an unverified one.
        $verifiedEmail = $gh->getEmail() ? Str::lower($gh->getEmail()) : null;

        $user = User::where('github_id', $gh->getId())->first()
            ?? ($verifiedEmail ? User::where('email', $verifiedEmail)->first() : null);

        if ($user) {
            $user->fill([
                'github_id' => $gh->getId(),
                'github_login' => $gh->getNickname(),
                'avatar_url' => $gh->getAvatar(),
                'name' => $user->name ?: ($gh->getName() ?: $gh->getNickname()),
                'auth_method' => $user->auth_method ?: 'github',
            ])->save();
        } else {
            $email = $verifiedEmail ?? Str::lower($gh->getNickname()).'@users.noreply.github.com';

            // Without a verified email we must not take over an account that already uses this address.
            if (User::where('email', $email)->exists()) {
                return $this->fail('An account with this email already exists. Sign in with your password.');
            }

            // SSO never grants admin; role defaults to "user".
            $user = User::create([
                'email' => $email,
                'name' => $gh->getName() ?: $gh->getNickname(),
                'github_id' => $gh->getId(),
                'github_login' => $gh->getNickname(),
                'avatar_url' => $gh->getAvatar(),
                'auth_method' => 'github',
            ]);

            // Email comes from GitHub; confirm it with our own verification link.
            $user->sendVerificationSafely();
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
