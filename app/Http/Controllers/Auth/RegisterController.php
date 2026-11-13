<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Recaptcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /** No sign-up page: back to the page the visitor came from, with the sign-up panel open. */
    public function show(Request $request): RedirectResponse
    {
        return LoginController::openPanel($request, 'register');
    }

    public function store(Request $request): RedirectResponse
    {
        // Validation errors return to the same page with the panel open on "Sign up".
        $request->session()->flash('auth_panel', 'register');
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:128', 'confirmed'],
            ...Recaptcha::rules('register'),
        ], [
            'email.unique' => 'Email already registered',
            'password.confirmed' => 'Passwords do not match',
            ...Recaptcha::messages(),
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
        $request->session()->forget('auth_panel');
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('status', "Welcome to AtGlance! We sent a verification link to {$user->email}.");
    }
}
