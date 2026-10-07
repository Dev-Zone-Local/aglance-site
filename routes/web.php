<?php

use App\Http\Controllers\Auth\GithubController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitemapController;
use App\Livewire\Licences;
use App\Livewire\Profile;
use Illuminate\Support\Facades\Route;

// Public site (Blade views in resources/views/site).
Route::controller(SiteController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/product', 'product')->name('product');
    Route::get('/cli', 'cli')->name('cli');
    Route::get('/console', 'console')->name('console');
    Route::get('/architecture', 'architecture')->name('architecture');
    Route::get('/use-cases', 'useCases')->name('use-cases');
    Route::get('/security', 'security')->name('security');
    Route::get('/pricing', 'pricing')->name('pricing');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/contact', 'contact')->name('contact');
    Route::get('/releases', 'releases')->name('releases');
    Route::get('/about', 'page')->defaults('slug', 'about')->name('about');
    Route::get('/terms', 'page')->defaults('slug', 'terms')->name('terms');
    Route::get('/privacy', 'page')->defaults('slug', 'privacy')->name('privacy');
    Route::get('/docs', 'docs')->name('docs');
    Route::get('/docs/{slug}', 'doc')->name('docs.show');
});

// Search engines: generated from the database (docs, CMS pages).
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Sign in / sign up. Every throttle has its own prefix so routes do not share one counter.
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1,web-login');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1,web-register');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1,web-forgot')->name('password.email');
    Route::get('/reset-password', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:10,1,web-reset')->name('password.update');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// GitHub sign-in. The callback path matches the redirect URI registered on GitHub.
Route::get('/auth/github', [GithubController::class, 'redirect'])->name('auth.github');
Route::get('/admin/auth/github', [GithubController::class, 'redirect'])->name('admin.auth.github');
Route::get('/auth/sso/github/callback', [GithubController::class, 'callback'])
    ->middleware('throttle:10,1,web-github-callback')->name('auth.github.callback');

// Signed-in area.
Route::middleware('auth')->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'overview'])->name('dashboard');
    Route::get('/install/{product?}/{target?}', [DashboardController::class, 'install'])->name('dashboard.install');
    Route::get('/licences', Licences::class)->name('dashboard.licences');
    Route::get('/profile', Profile::class)->name('dashboard.profile');
    Route::post('/email/resend', [DashboardController::class, 'resendVerification'])
        ->middleware('throttle:3,1,web-resend-email')->name('verification.resend');
});
