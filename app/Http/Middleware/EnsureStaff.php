<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel is for the people who run the site. A student is signed in to
 * the same session guard (one account system for everyone), so without this
 * a learner who typed /admin would get the panel shell. They are sent to
 * their own dashboard instead, still signed in.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isStaff()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'The admin panel is for staff accounts.'], 403)
                : redirect()->route('student.dashboard');
        }

        return $next($request);
    }
}
