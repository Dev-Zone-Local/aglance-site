<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Checks the Google reCAPTCHA v2 token ("g-recaptcha-response") with Google's siteverify API.
 * Only used while both keys are set; see Recaptcha::enabled().
 */
class Recaptcha implements ValidationRule
{
    public const FIELD = 'g-recaptcha-response';

    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public static function enabled(): bool
    {
        return filled(config('services.recaptcha.site_key')) && filled(config('services.recaptcha.secret_key'));
    }

    /** Rules to merge into a form's validation: nothing when reCAPTCHA is off. */
    public static function rules(): array
    {
        return self::enabled() ? [self::FIELD => ['required', 'string', new self]] : [];
    }

    public static function messages(): array
    {
        return [self::FIELD.'.required' => 'Please confirm you are not a robot.'];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);
            $ok = $response->successful() && $response->json('success') === true;
        } catch (Throwable $e) {
            Log::warning('reCAPTCHA verification failed', ['error' => $e->getMessage()]);
            $ok = false;
        }

        if (! $ok) {
            $fail('CAPTCHA check failed. Please try again.');
        }
    }
}
