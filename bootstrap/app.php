<?php

use App\Http\Middleware\EnsureUserHasCredits;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireCanonicalHost;
use App\Http\Middleware\ValidateSessionVersion;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(RequireCanonicalHost::class);
        $middleware->alias([
            'admin' => RequireAdmin::class,
            'session.version' => ValidateSessionVersion::class,
            'credits' => EnsureUserHasCredits::class,
        ]);
        $middleware->trustHosts(at: fn () => array_map(
            fn ($host) => '^'.preg_quote($host, '/').'$',
            array_filter(array_unique([
                parse_url(config('app.url'), PHP_URL_HOST),
                ...(app()->environment('local', 'testing') ? ['localhost', '127.0.0.1'] : []),
            ]))
        ), subdomains: false);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
