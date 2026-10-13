{{--
    Google reCAPTCHA v3 (invisible). Renders nothing while the keys are not set.
    On submit it asks Google for a fresh token (they expire after 2 minutes), puts it in the
    hidden field, then sends the form. Usage: <x-recaptcha action="login" />
--}}
@props(['action'])
@if (\App\Rules\Recaptcha::enabled())
    <div>
        <input type="hidden" name="{{ \App\Rules\Recaptcha::FIELD }}" data-recaptcha-action="{{ $action }}">
        @error(\App\Rules\Recaptcha::FIELD)<p class="text-[13px] text-ag-danger-text">{{ $message }}</p>@enderror
    </div>
    @once
        <script src="https://www.google.com/recaptcha/api.js?render={{ \App\Support\Captcha::siteKey() }}"></script>
        <script>
            document.addEventListener('submit', (event) => {
                const form = event.target;
                const input = form.querySelector('input[data-recaptcha-action]');
                // No widget on this form, or the script was blocked: let the server explain.
                if (!input || !window.grecaptcha) return;
                event.preventDefault();
                grecaptcha.ready(() => {
                    grecaptcha.execute(@js(\App\Support\Captcha::siteKey()), { action: input.dataset.recaptchaAction })
                        .then((token) => { input.value = token; form.submit(); });
                });
            }, true);
        </script>
    @endonce
@endif
