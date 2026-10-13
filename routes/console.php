<?php

use App\Models\License;
use App\Models\User;
use App\Support\Captcha;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Create the first admin, or make an existing user admin and set a new password (lost-password recovery).
// The admin account is not configured in .env; everything else is set in the admin panel.
Artisan::command('atglance:make-admin {email? : Admin email} {--password= : Password (asked for, hidden, when left out)}', function () {
    $email = Str::lower(trim((string) ($this->argument('email') ?: $this->ask('Admin email'))));
    $password = (string) ($this->option('password') ?: $this->secret('Password (at least 8 characters)'));

    $validator = Validator::make(compact('email', 'password'), [
        'email' => ['required', 'email', 'max:255'],
        'password' => ['required', 'string', 'min:8', 'max:128'],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $user = User::firstOrNew(['email' => $email]);
    $isNew = ! $user->exists;
    $user->forceFill([
        'name' => $user->name ?: 'AtGlance Admin',
        'auth_method' => $user->auth_method ?: 'email',
        'password' => $password,
        'role' => User::ROLE_ADMIN,
        'email_verified_at' => $user->email_verified_at ?? now(),
    ])->save();

    $this->info(($isNew ? 'Created admin ' : 'Updated admin ').$email.'. Sign in at '.url('/admin').'.');

    return 0;
})->purpose('Create an admin account, or make a user admin and reset their password');

// Way back in when a wrong CAPTCHA key locks everyone out of the admin login.
Artisan::command('atglance:captcha {--off : Turn the CAPTCHA off}', function () {
    if ($this->option('off')) {
        Captcha::disable();
        $this->info('CAPTCHA turned off. Fix the keys under Settings → CAPTCHA, then turn it on again.');

        return 0;
    }

    $this->line('CAPTCHA is '.(Captcha::enabled() ? 'on' : 'off').'. Use --off to turn it off.');

    return 0;
})->purpose('Show the CAPTCHA status or turn it off');

// Wipe stored plain licence keys once their 30-minute viewing window has passed.
// Also runs lazily on the dashboard, so it is safe even without a scheduler.
Artisan::command('licenses:forget-expired-keys', function () {
    $this->info('Forgot '.License::forgetExpiredKeys().' expired licence key(s).');
})->purpose('Remove plain licence keys whose viewing window has passed');

Schedule::command('licenses:forget-expired-keys')->everyFiveMinutes();
