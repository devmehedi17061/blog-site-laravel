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
            $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

            // A download (CSV export, an uploaded file) keeps its own stricter policy.
            if (! $response->headers->has('Content-Security-Policy')) {
                $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request, $local));
            }
        }

        // The server need not say what it runs on.
        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        // Only ever sent over HTTPS: a browser that sees it remembers to use
        // HTTPS for a year, so it must not be sent while HTTPS is untested.
        if ($this->security->get('hsts') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * What a page may load and do. Wide enough for what the site really uses
     * — inline scripts and JSON-LD, Alpine (which needs eval), Google Fonts,
     * YouTube/Maps embeds, CAPTCHA widgets, analytics added under Front end —
     * and closed where an attack would need it: no plugins (<object>/<embed>),
     * no <base> hijacking, forms only post to this site, nothing but this
     * site may frame it, and plain http:// resources are refused on https.
     */
    private function contentSecurityPolicy(Request $request, bool $local): string
    {
        // `npm run dev` serves scripts from its own port while developing.
        $dev = $local && is_file(public_path('hot')) ? ' http://localhost:* http://127.0.0.1:* ws://localhost:* ws://127.0.0.1:*' : '';

        $policy = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:".$dev,
            "style-src 'self' 'unsafe-inline' https:".$dev,
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https:",
            "media-src 'self' blob: https:",
            "connect-src 'self' https:".$dev,
            "frame-src 'self' https:",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        if ($request->isSecure()) {
            $policy[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $policy);
    }
}
