<?php

use App\Jobs\AutoReleaseExpiredBookings;
use App\Jobs\CheckActiveCycles;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Check for machine cycles that have reached zero seconds remaining
Schedule::job(new CheckActiveCycles)->everyMinute()->withoutOverlapping();

// Release bookings where user has not showed up within grace period
Schedule::job(new AutoReleaseExpiredBookings)->everyMinute()->withoutOverlapping();

Schedule::call(fn () => DB::table('password_reset_links')->where('expires_at', '<', now()->subDay())->delete())
    ->daily()->name('prune-reset-links')->withoutOverlapping();
