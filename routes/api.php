<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CmsController;
use App\Http\Controllers\Api\GithubAuthController;
use App\Http\Controllers\Api\InstallTokenController;
use App\Support\GithubOAuth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => ['service' => 'AtGlance API', 'ok' => true]);

// Auth (Sanctum SPA session cookies)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');

    Route::get('providers', fn () => ['github' => GithubOAuth::enabled()]);
    Route::get('github/start', [GithubAuthController::class, 'start']);
    Route::post('github/callback', [GithubAuthController::class, 'callback'])->middleware('throttle:10,1');
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

// Management Console install tokens (created on the dashboard, verified by the installer)
Route::middleware('auth:sanctum')->prefix('install-tokens')->controller(InstallTokenController::class)->group(function () {
    Route::post('verify', 'verify')->middleware('throttle:30,1');
    Route::get('/', 'index');
    Route::post('/', 'store')->middleware('throttle:10,1');
    Route::delete('{id}', 'destroy')->whereNumber('id');
});
