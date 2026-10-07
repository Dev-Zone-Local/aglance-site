<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials = ['email' => Str::lower(trim($data['email'])), 'password' => $data['password']];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password']);
        }

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
            return redirect('/admin');
        }

        return $intended ? redirect()->intended(route('dashboard')) : redirect()->route('dashboard');
    }
}
