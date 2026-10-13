<?php

namespace App\Rules;

use App\Support\Captcha;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Checks a Google reCAPTCHA v3 token with Google's siteverify API: it must be valid, made for
 * this form's action, and score at least the minimum score (0.0 bot .. 1.0 human).
 * Keys and score come from Settings → CAPTCHA in the admin panel (App\Support\Captcha).
 */
class Recaptcha implements ValidationRule
{
    public const FIELD = 'g-recaptcha-response';

    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /** @param string $action the action name the page passed to grecaptcha.execute() */
    public function __construct(private string $action) {}

    public static function enabled(): bool
    {
        return Captcha::enabled();
    }

    /** Rules to merge into a form's validation: nothing when reCAPTCHA is off. */
    public static function rules(string $action): array
    {
        return self::enabled() ? [self::FIELD => ['required', 'string', new self($action)]] : [];
    }

    public static function messages(string $field = self::FIELD): array
    {
        return [$field.'.required' => 'The security check did not load. Reload the page and try again.'];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => Captcha::secretKey(),
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);
            $result = $response->successful() ? (array) $response->json() : [];
        } catch (Throwable $e) {
            Log::warning('reCAPTCHA verification failed', ['error' => $e->getMessage()]);
            $result = [];
        }

        $passed = ($result['success'] ?? false) === true
            && ($result['action'] ?? null) === $this->action
            && (float) ($result['score'] ?? 0) >= Captcha::minScore();

        if (! $passed) {
            Log::info('reCAPTCHA rejected', [
                'action' => $this->action,
                'got_action' => $result['action'] ?? null,
                'score' => $result['score'] ?? null,
                'errors' => $result['error-codes'] ?? null,
            ]);
            $fail('We could not verify that you are human. Please try again.');
        }
    }
}
