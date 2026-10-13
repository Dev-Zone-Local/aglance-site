{{--
    Google reCAPTCHA v3 (invisible) for the admin login form (App\Filament\Pages\Auth\Login).
    On "Sign in" it holds the submit, asks Google for a fresh token, puts it in the component's
    $captcha property (deferred), then submits again so Filament's wire:submit runs as usual.
--}}
<div
    x-data
    x-init="
        const form = $el.closest('form');
        if (form && !form.dataset.recaptchaBound) {
            form.dataset.recaptchaBound = '1';
            form.addEventListener('submit', (event) => {
                if (form.dataset.recaptchaReady === '1') { form.dataset.recaptchaReady = ''; return; }
                if (!window.grecaptcha) return; // blocked script: the server says why
                event.preventDefault();
                event.stopImmediatePropagation();
                grecaptcha.ready(() => {
                    grecaptcha.execute(@js(\App\Support\Captcha::siteKey()), { action: 'admin_login' }).then((token) => {
                        $wire.set('captcha', token, false);
                        form.dataset.recaptchaReady = '1';
                        form.requestSubmit();
                    });
                });
            }, true);
        }
    "
>
    @error('captcha')
        <p style="font-size: .875rem; color: #C53030;">{{ $message }}</p>
    @enderror
    @once
        <script src="https://www.google.com/recaptcha/api.js?render={{ \App\Support\Captcha::siteKey() }}"></script>
    @endonce
</div>
