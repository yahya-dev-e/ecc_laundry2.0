<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasCredits
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, int $minCredits = 2): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->hasCredits($minCredits)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Insufficient credits to perform this laundry operation.',
                    'credits_needed' => $minCredits,
                    'current_credits' => $user->credits,
                ], 403);
            }

            return redirect()->route('dashboard')
                ->with('error', "Insufficient credits ({$user->credits} available, {$minCredits} required). Please top up your laundry card at the front desk.");
        }

        return $next($request);
    }
}
