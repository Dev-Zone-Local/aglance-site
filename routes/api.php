<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CmsController;
use App\Http\Controllers\Api\ConsoleLicenseController;
use App\Http\Controllers\Api\GithubAuthController;
use App\Http\Controllers\Api\LicenseController;
use App\Support\GithubOAuth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => ['service' => 'AtGlance API', 'ok' => true]);

// Auth (Sanctum SPA session cookies).
// Every throttle has its own prefix: without one, Laravel shares a single counter per user/IP across routes.
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1,register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1,login');
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1,forgot-password');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1,reset-password');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');

    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->whereNumber('id')->middleware('throttle:20,1,verify-email')->name('verification.verify');
    Route::post('email/resend', [AuthController::class, 'resendVerification'])
        ->middleware(['auth:sanctum', 'throttle:3,1,resend-email']);

    Route::get('providers', fn () => ['github' => GithubOAuth::enabled()]);
    Route::get('github/start', [GithubAuthController::class, 'start']);
    Route::post('github/callback', [GithubAuthController::class, 'callback'])->middleware('throttle:10,1,github-callback');
});

// Public CMS reads
Route::prefix('cms')->controller(CmsController::class)->group(function () {
    Route::get('pricing', 'pricing');
    Route::get('faqs', 'faqs');
    Route::get('docs', 'docs');
    Route::get('docs/{slug}', 'doc');
    Route::get('contact', 'contact');
    Route::get('pages/{slug}', 'page');
});

// Logged-in users only
Route::get('downloads', [CmsController::class, 'downloads'])->middleware('auth:sanctum');
Route::put('account/preferences', [CmsController::class, 'updatePreferences'])->middleware('auth:sanctum');
Route::put('account/profile', [CmsController::class, 'updateProfile'])->middleware('auth:sanctum');
Route::put('account/password', [CmsController::class, 'updatePassword'])->middleware(['auth:sanctum', 'throttle:5,1,account-password']);

// Signed link in release emails
Route::get('updates/unsubscribe/{id}', [CmsController::class, 'unsubscribe'])
    ->whereNumber('id')->middleware('throttle:20,1,unsubscribe')->name('updates.unsubscribe');

// Called by the self-hosted Management Console with "Authorization: Bearer <licence key>"
Route::middleware('auth:sanctum')->prefix('licenses')->controller(ConsoleLicenseController::class)->group(function () {
    Route::post('verify', 'verify')->middleware('throttle:30,1,license-verify');
    Route::post('activate', 'activate')->middleware('throttle:30,1,license-activate');
    Route::post('heartbeat', 'heartbeat')->middleware('throttle:60,1,license-heartbeat');
    Route::put('org', 'updateOrg')->middleware('throttle:10,1,license-org');
});

// Management Console licences managed from the dashboard
Route::middleware('auth:sanctum')->prefix('licenses')->controller(LicenseController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store')->middleware('throttle:5,1,license-store');
    Route::post('{id}/confirm-code', 'requestConfirmCode')->whereNumber('id')->middleware('throttle:3,1,license-confirm-code');
    Route::post('{id}/confirm', 'confirm')->whereNumber('id')->middleware('throttle:10,1,license-confirm');
    Route::get('{id}/key', 'showKey')->whereNumber('id')->middleware('throttle:20,1,license-key');
    Route::post('{id}/revoke-code', 'requestRevokeCode')->whereNumber('id')->middleware('throttle:3,1,license-revoke-code');
    Route::delete('{id}', 'destroy')->whereNumber('id')->middleware('throttle:10,1,license-revoke');
});
