<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireCanonicalHost
{
    public function handle(Request $request, Closure $next)
    {
        $allowed = [strtolower((string) parse_url(config('app.url'), PHP_URL_HOST))];
        if (app()->environment('local', 'testing')) {
            $allowed = [...$allowed, 'localhost', '127.0.0.1'];
        }
        abort_unless(in_array(strtolower($request->getHost()), $allowed, true), 400, 'Unexpected request host.');

        return $next($request);
    }
}
