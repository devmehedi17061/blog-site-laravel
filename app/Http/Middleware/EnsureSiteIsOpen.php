<?php

namespace App\Http\Middleware;

use App\Support\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance mode from Settings → Site.
 *
 * Visitors get a 503 "back soon" page — the status search engines read as
 * "temporary, come back later", so rankings are not lost. Anyone signed in
 * to the panel still sees the real site, with a bar saying maintenance is
 * on. The panel itself is never behind this: it is how you turn it off.
 */
class EnsureSiteIsOpen
{
    public function __construct(private readonly Site $site) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->site->inMaintenance() || $request->user()?->isActive()) {
            return $next($request);
        }

        return response()
            ->view('frontend.maintenance', [], 503)
            ->header('Retry-After', '3600')
            ->header('Cache-Control', 'no-store');
    }
}
