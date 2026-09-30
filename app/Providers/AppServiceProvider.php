<?php

namespace App\Providers;

use App\Models\License;
use App\Support\MailSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Admin-panel SMTP settings are applied lazily, only when mail is actually sent,
        // so normal requests do not pay for the settings lookup.
        $this->app->afterResolving('mail.manager', fn () => MailSettings::apply());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The SPA expects bare JSON objects, not {"data": ...}.
        JsonResource::withoutWrapping();

        // Licence keys are Sanctum tokens; resolve them as License models (status, approval, activation).
        Sanctum::usePersonalAccessTokenModel(License::class);

        // Password reset links open the SPA page, which posts token + new password to the API.
        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('atglance.frontend_url'), '/')
            .'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset()));

        // Verification links hit the API (proxied on the SPA origin), which then redirects to the dashboard.
        VerifyEmail::createUrlUsing(fn ($user) => URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        ));
    }
}
