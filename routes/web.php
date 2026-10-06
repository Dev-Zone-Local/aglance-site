<?php

use App\Http\Controllers\SitemapController;
use App\Support\GithubOAuth;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

// The public site is the React SPA in frontend/. Laravel serves /api, /sanctum and the Filament panel at /admin.
Route::get('/', fn () => redirect(config('atglance.frontend_url')));

// Search engines: generated from the database (docs, CMS pages) with FRONTEND_URL links.
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// "Sign in with GitHub" on the Filament login page. GitHub returns to the SPA callback page,
// which completes the login through /api/auth/github/callback and sends admins back to /admin.
Route::get('/admin/auth/github', function () {
    abort_unless(GithubOAuth::enabled(), 404);
    GithubOAuth::apply();

    return Socialite::driver('github')->redirect();
})->name('admin.auth.github');
