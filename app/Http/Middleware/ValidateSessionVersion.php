<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ValidateSessionVersion
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            if (Auth::viaRemember() && ! $request->session()->has('auth_version')) {
                $request->session()->put('auth_version', $user->session_version);
            }
            if ((int) $request->session()->get('auth_version', -1) !== (int) $user->session_version) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Votre session a expiré. Veuillez vous reconnecter.'], 401);
                }

                return redirect()->route('login')->with('status', 'Votre session a expiré. Veuillez vous reconnecter.');
            }
        }

        return $next($request);
    }
}
