<?php

use App\Models\User;
use App\Support\Captcha;
use App\Support\LicenseFile\Packer;
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


// New signing and encryption keys for offline licence files (.atgl). Prints them only:
// nothing is written to disk. See docs/LICENSING.md, "Keys".
Artisan::command('license:generate-keys {key_id? : Key ID, e.g. atgl-2026-10}', function () {
    $keyId = (string) ($this->argument('key_id') ?: 'atgl-'.now()->format('Y-m'));
    if (preg_match('/^[A-Za-z0-9._-]{1,64}$/', $keyId) !== 1) {
        $this->error('The key ID must be 1-64 characters from A-Z a-z 0-9 . _ -');

        return 1;
    }
    $keys = Packer::generateKeys();

    $this->warn('Keep the private key and the AES key secret. Do not commit them or paste them into tickets.');
    $this->newLine();
    $this->line('<info>1. atglance.live environment</info> (secret manager or the server .env):');
    $this->line('LICENSE_KEY_ID='.$keyId);
    $this->line('LICENSE_SIGNING_PRIVATE_KEY='.$keys['private_b64']);
    $this->line('LICENSE_ENC_KEY='.$keys['enc_key_hex']);
    $this->newLine();
    $this->line('<info>2. Management Console keyring entry</info> (add to the ATGLANCE_LICENSE_KEYRING build arg / config/license-keyring.json):');
    $this->line(json_encode([$keyId => ['public_key' => $keys['public_b64'], 'enc_key' => $keys['enc_key_hex']]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->newLine();
    $this->line('<info>Private key as PEM</info> (same key as LICENSE_SIGNING_PRIVATE_KEY):');
    $this->line($keys['private_pem']);
    $this->line('<info>Public key as PEM</info>:');
    $this->line($keys['public_pem']);
    $this->line('Ship a console release with the new keyring entry before atglance.live starts signing with this key ID.');

    return 0;
})->purpose('Generate a new key set for offline licence files (.atgl)');
