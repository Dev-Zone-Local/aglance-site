<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CmsController;
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

// Management Console licences (created on the dashboard, verified by the Console installer)
Route::middleware('auth:sanctum')->prefix('licenses')->controller(LicenseController::class)->group(function () {
    Route::post('verify', 'verify')->middleware('throttle:30,1,license-verify');
    Route::get('/', 'index');
    Route::post('code', 'requestCode')->middleware('throttle:3,1,license-code');
    Route::post('/', 'store')->middleware('throttle:10,1,license-store');
    Route::post('{id}/revoke-code', 'requestRevokeCode')->whereNumber('id')->middleware('throttle:3,1,license-revoke-code');
    Route::delete('{id}', 'destroy')->whereNumber('id')->middleware('throttle:10,1,license-revoke');
});
