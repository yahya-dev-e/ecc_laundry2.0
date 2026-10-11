<?php

namespace App\Providers;

use App\Events\CycleCompleted;
use App\Listeners\SendCycleAlertNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(CycleCompleted::class, SendCycleAlertNotification::class);
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by('login:'.hash('sha256', strtolower(trim((string) $r->input('email'))).'|'.$r->ip())),
            Limit::perMinute(30)->by('login-ip:'.$r->ip()),
        ]);
        RateLimiter::for('reset', fn (Request $r) => [
            Limit::perMinute(3)->by('reset:'.hash('sha256', strtolower(trim((string) $r->input('email'))))),
            Limit::perHour(5)->by('reset-hour:'.hash('sha256', strtolower(trim((string) $r->input('email'))))),
            Limit::perMinute(10)->by('reset-ip:'.$r->ip()),
        ]);
        RateLimiter::for('signup', fn (Request $r) => Limit::perHour(10)->by('signup:'.$r->ip()));
    }
}
