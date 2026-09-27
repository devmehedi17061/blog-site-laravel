<?php

namespace App\Http\Middleware;

use App\Support\SiteSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public site speaks the language set under Settings → Site: that is
 * what <html lang> says, and what dates are written in ("25 সেপ্টেম্বর
 * 2026"). Only on public routes — the admin panel stays in English.
 */
class SetSiteLanguage
{
    public function __construct(private readonly SiteSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $language = (string) $this->settings->get('site.language');

        if (array_key_exists($language, SiteSettings::LANGUAGES)) {
            // Laravel passes this on to Carbon, so translatedFormat() and
            // diffForHumans() follow it.
            app()->setLocale($language);
        }

        return $next($request);
    }
}
