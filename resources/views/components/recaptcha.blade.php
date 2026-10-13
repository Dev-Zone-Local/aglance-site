{{-- Google reCAPTCHA v2 checkbox. Renders nothing while the keys are not set. --}}
@if (\App\Rules\Recaptcha::enabled())
    <div>
        <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
        @error(\App\Rules\Recaptcha::FIELD)<p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p>@enderror
    </div>
    @once
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endonce
@endif
