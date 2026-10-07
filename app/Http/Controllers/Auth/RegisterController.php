<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:128', 'confirmed'],
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

        return redirect()->route('dashboard')
            ->with('status', "Welcome to AtGlance! We sent a verification link to {$user->email}.");
    }
}
