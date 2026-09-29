<?php

use App\Models\License;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Wipe stored plain licence keys once their 30-minute viewing window has passed.
// Also runs lazily on the dashboard, so it is safe even without a scheduler.
Artisan::command('licenses:forget-expired-keys', function () {
    $this->info('Forgot '.License::forgetExpiredKeys().' expired licence key(s).');
})->purpose('Remove plain licence keys whose viewing window has passed');

Schedule::command('licenses:forget-expired-keys')->everyFiveMinutes();
