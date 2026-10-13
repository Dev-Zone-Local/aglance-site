{{--
    Google reCAPTCHA v2 checkbox inside the admin login form (App\Filament\Pages\Auth\Login).
    wire:ignore keeps Livewire from wiping the widget on re-render. The token is passed to the
    component's $captcha property (deferred, sent with "Sign in"); the component dispatches
    "recaptcha-reset" after a failed try, because Google accepts each token only once.
--}}
<div
    x-data
    x-init="
        window.agAdminCaptchaDone = (token) => $wire.set('captcha', token, false);
        window.agAdminCaptchaExpired = () => $wire.set('captcha', null, false);
        window.addEventListener('recaptcha-reset', () => window.grecaptcha && window.grecaptcha.reset());
    "
>
    <div wire:ignore>
        <div class="g-recaptcha"
            data-sitekey="{{ config('services.recaptcha.site_key') }}"
            data-callback="agAdminCaptchaDone"
            data-expired-callback="agAdminCaptchaExpired"></div>
    </div>
    @error('captcha')
        <p style="margin-top: .375rem; font-size: .875rem; color: #C53030;">{{ $message }}</p>
    @enderror
    @once
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endonce
</div>
