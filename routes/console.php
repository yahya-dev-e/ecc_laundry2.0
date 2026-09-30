<?php

use App\Jobs\AutoReleaseExpiredBookings;
use App\Jobs\CheckActiveCycles;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Check for machine cycles that have reached zero seconds remaining
Schedule::job(new CheckActiveCycles)->everyMinute();

// Release bookings where user has not showed up within grace period
Schedule::job(new AutoReleaseExpiredBookings)->everyFiveMinutes();
