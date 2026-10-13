<?php

namespace App\Http\Middleware;

use App\Support\AccountInput;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class PrivateTestAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('private_test.enabled')) {
            return $next($request);
        }

        abort_unless(IpUtils::checkIp($request->ip(), config('private_test.networks')), 403);
        abort_if($request->is('register'), 403, 'Registration is disabled on this private test site.');

        $allowed = config('private_test.emails');
        if ($request->user()) {
            abort_unless(in_array(strtolower(trim($request->user()->email)), $allowed, true), 403);
        }

        if ($request->isMethod('post') && $request->is('login', 'forgot-password', 'reset-password')) {
            $email = AccountInput::email($request->input('email'));
            if (! in_array($email, $allowed, true)) {
                if ($request->is('forgot-password')) {
                    return back()->with('status', 'Si cette adresse correspond à un compte, vous recevrez un lien de réinitialisation. Vérifiez également vos courriers indésirables.');
                }

                return back()->withErrors(['email' => 'Les identifiants ne correspondent pas à nos données.'])->onlyInput('email');
            }
        }

        return $next($request);
    }
}
