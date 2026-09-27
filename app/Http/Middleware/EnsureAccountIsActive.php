<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspending an account has to take effect on its next request, not at its
 * next sign-in — a session opened yesterday would otherwise carry on for as
 * long as the browser stays open.
 *
 * Also keeps "last seen" current, written at most every few minutes so an
 * ordinary page of API calls is not a write each.
 */
class EnsureAccountIsActive
{
    private const SEEN_EVERY_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // api.js sends a 401 back to the sign-in form.
            return $request->expectsJson()
                ? response()->json(['message' => 'This account is suspended.'], 401)
                : redirect()->route('admin.login')->withErrors(['email' => 'This account is suspended. Contact a super admin.']);
        }

        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(self::SEEN_EVERY_MINUTES)))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
