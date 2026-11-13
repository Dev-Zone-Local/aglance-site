<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Recaptcha;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** No sign-in page: back to the page the visitor came from, with the sign-in panel open. */
    public function show(Request $request): RedirectResponse
    {
        return self::openPanel($request, 'login');
    }

    /**
     * Send a guest back to a public page with the header sign-in / sign-up panel open.
     * Messages flashed for /login (e.g. "Google sign-in was cancelled") are kept.
     */
    public static function openPanel(Request $request, string $mode): RedirectResponse
    {
        $request->session()->reflash();

        return redirect()->to(self::publicReferer($request))->with('auth_panel', $mode);
    }

    /** The previous page on this site, unless it needs a sign-in (that would loop back here). */
    public static function publicReferer(Request $request): string
    {
        $previous = (string) $request->headers->get('referer');
        $path = trim((string) parse_url($previous, PHP_URL_PATH), '/');
        $sameHost = parse_url($previous, PHP_URL_HOST) === $request->getHost();
        $private = preg_match('#^(login|register|logout|dashboard|admin|email|reset-password|forgot-password|auth)(/|$)#', $path);

        return $previous && $sameHost && ! $private ? $previous : route('home');
    }

    public function store(Request $request): RedirectResponse
    {
        // A failed sign-in returns to the same page with the panel open on "Sign in".
        $request->session()->flash('auth_panel', 'login');

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            ...Recaptcha::rules('login'),
        ], Recaptcha::messages());

        $credentials = ['email' => Str::lower(trim($data['email'])), 'password' => $data['password']];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password']);
        }

        $request->session()->forget('auth_panel');
        $request->session()->regenerate();

        return self::home($request->user(), intended: true);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /** Where a user lands after signing in: admins go to the admin panel, users to the dashboard. */
    public static function home(User $user, bool $intended = false): RedirectResponse
    {
        if ($user->role === 'admin') {
            // Back to the admin page they opened before signing in; other intended pages are ignored.
            $intendedUrl = $intended ? session()->pull('url.intended') : null;
            $path = $intendedUrl ? '/'.ltrim((string) parse_url($intendedUrl, PHP_URL_PATH), '/') : null;

            return redirect($path && ($path === '/admin' || str_starts_with($path, '/admin/')) ? $intendedUrl : '/admin');
        }

        return $intended ? redirect()->intended(route('dashboard')) : redirect()->route('dashboard');
    }
}
