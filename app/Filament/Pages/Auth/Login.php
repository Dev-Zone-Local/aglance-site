<?php

namespace App\Filament\Pages\Auth;

use App\Rules\Recaptcha;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin sign-in (/admin/login): email + password, plus invisible reCAPTCHA v3 when its keys are set
 * (no GitHub button here). It always says why a sign-in did not work instead of quietly showing
 * the form again:
 * - a correct password for an account without admin access gets its own message;
 * - only failed attempts count towards the 5-a-minute limit, and the "too many attempts" notice stays up;
 * - an expired page (Livewire 419, see filament.auth.login-problem) reloads with ?expired=1 and a notice;
 * - an "intended" page outside the panel (set by visiting /contact or /dashboard as a guest) is
 *   ignored, so an admin always lands in the panel.
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        if (request()->boolean('expired')) {
            Notification::make()
                ->title('Your session expired')
                ->body('The sign-in page was open too long, or you signed in or out in another tab. Enter your details again.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    /** reCAPTCHA v3 token, set by filament.auth.recaptcha just before each sign-in. */
    public ?string $captcha = null;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
                View::make('filament.auth.recaptcha')->visible(fn (): bool => Recaptcha::enabled()),
            ]);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            if (Recaptcha::enabled()) {
                Validator::make(
                    ['captcha' => $this->captcha],
                    ['captcha' => ['required', 'string', new Recaptcha('admin_login')]],
                    Recaptcha::messages('captcha'),
                )->validate();
            }

            $response = parent::authenticate();
        } catch (ValidationException $e) {
            $this->resetCaptcha();

            throw $e;
        }

        if (! $response) {
            $this->resetCaptcha();
        }

        if ($response) {
            // Filament counts every attempt (5 a minute per IP); only failed ones should count.
            $this->clearRateLimiter('authenticate');

            $intended = (string) session('url.intended');
            $panelUrl = rtrim(Filament::getUrl(), '/');

            if ($intended !== '' && ! Str::startsWith($intended, $panelUrl)) {
                session()->forget('url.intended');
            }
        }

        return $response;
    }

    /** Google accepts a token once; the page fetches a new one on the next "Sign in". */
    protected function resetCaptcha(): void
    {
        $this->captcha = null;
    }

    /** Stays on screen: a toast that fades in seconds looks like the form just reloaded. */
    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        return parent::getRateLimitedNotification($exception)?->persistent();
    }

    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if (parent::isUserAllowedToAccessPanel($user)) {
            return true;
        }

        // The password was right, so saying why does not help a guesser.
        throw ValidationException::withMessages([
            'data.email' => 'This account does not have admin access. Sign in on the website instead.',
        ]);
    }
}
