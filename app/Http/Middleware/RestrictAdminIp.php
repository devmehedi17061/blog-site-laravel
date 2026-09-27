<?php

namespace App\Http\Middleware;

use App\Support\Security;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings → Security → IP rules, for the admin panel only. The public site
 * is never behind these.
 *
 * Locked out by mistake? From the server: php artisan security:unlock
 */
class RestrictAdminIp
{
    public function __construct(private readonly Security $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->security->adminIpAllowed((string) $request->ip())) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Your address is not allowed to use the admin panel.'], 403)
            : response()->view('backend.ip-blocked', ['ip' => $request->ip()], 403);
    }
}
