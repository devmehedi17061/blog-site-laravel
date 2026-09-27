<?php

namespace App\Http\Middleware;

use App\Support\Security;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings → Security → HTTPS & headers, on every web response.
 *
 * Forcing HTTPS is skipped on development hosts (localhost, 127.0.0.1,
 * *.test): no local server answers https://, so switching it on there would
 * make the site — and the switch to turn it off — unreachable.
 */
class SecurityHeaders
{
    public function __construct(private readonly Security $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        $local = Security::isLocalHost($request->getHost());

        if ($this->security->get('force_https') && ! $request->isSecure() && ! $local) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        if ($this->security->get('headers')) {
            // Nobody may frame the site in theirs (clickjacking), browsers
            // must not second-guess file types, and other sites only learn
            // which site a visitor came from, not the full address.
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        }

        // Only ever sent over HTTPS: a browser that sees it remembers to use
        // HTTPS for a year, so it must not be sent while HTTPS is untested.
        if ($this->security->get('hsts') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
