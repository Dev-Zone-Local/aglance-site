<?php

namespace Tests;

use App\Models\Setting;
use App\Support\Captcha;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Crypt;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Behave like the SPA: Sanctum only starts a session for stateful origins.
        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'Accept' => 'application/json',
        ]);
    }

    /** Turn on "Sign in with GitHub" the way the admin panel does (Settings → GitHub login). */
    protected function enableGithubLogin(): void
    {
        Setting::put(Setting::GITHUB, ['enabled' => true, 'client_id' => 'test-client', 'client_secret' => Crypt::encryptString('test-secret')]);
    }

    /** Turn on "Sign in with Google" the way the admin panel does (Settings → Google login). */
    protected function enableGoogleLogin(): void
    {
        Setting::put(Setting::GOOGLE, ['enabled' => true, 'client_id' => 'google-client', 'client_secret' => Crypt::encryptString('google-secret')]);
    }

    /** Turn on reCAPTCHA v3 the way the admin panel does (Settings → CAPTCHA). */
    protected function enableCaptcha(float $minScore = 0.5): void
    {
        Captcha::save(true, 'site-key-123', 'secret-456', $minScore);
    }
}
