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
    public function handle(Request $request, Closure $next, int $minCredits = 1): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->hasCredits($minCredits)) {
            $limit = $user->weeklyLimit();
            $remaining = $user->weeklyRemainingLimit();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Insufficient credits to perform this laundry operation.',
                    'credits_needed' => $minCredits,
                    'current_credits' => $remaining,
                ], 403);
            }

            return redirect()->route('dashboard')
                ->with('error', "Quota hebdomadaire insuffisant ({$remaining} crédit(s) disponible(s) sur vos {$limit} crédits cette semaine).");
        }

        return $next($request);
    }
}
