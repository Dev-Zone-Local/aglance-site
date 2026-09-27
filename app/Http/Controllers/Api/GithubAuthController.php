<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\GithubOAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GithubAuthController extends Controller
{
    public function start(): JsonResponse
    {
        abort_unless(GithubOAuth::enabled(), 503, 'GitHub sign-in is not configured');
        GithubOAuth::apply();

        // Socialite stores the OAuth state in the session and verifies it on callback.
        $url = Socialite::driver('github')->redirect()->getTargetUrl();

        return response()->json(['auth_url' => $url]);
    }

    public function callback(Request $request): UserResource
    {
        $request->validate(['code' => 'required|string', 'state' => 'required|string']);

        abort_unless(GithubOAuth::enabled(), 503, 'GitHub sign-in is not configured');
        GithubOAuth::apply();

        try {
            $gh = Socialite::driver('github')->user();
        } catch (InvalidStateException) {
            abort(400, 'Invalid OAuth state. Please start the sign-in again.');
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
            abort_if(User::where('email', $email)->exists(), 409, 'An account with this email already exists. Sign in with your password.');

            // SSO never grants admin; role defaults to "user".
            $user = User::create([
                'email' => $email,
                'name' => $gh->getName() ?: $gh->getNickname(),
                'github_id' => $gh->getId(),
                'github_login' => $gh->getNickname(),
                'avatar_url' => $gh->getAvatar(),
                'auth_method' => 'github',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }
}
