<?php

namespace App\Http\Middleware;

use App\Support\SiteSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Which language the public site speaks to this visitor:
 *
 *   1. the one they picked with the বাংলা / EN switch (this session),
 *   2. else the one saved on their account,
 *   3. else the site's own, from Settings → Site.
 *
 * That is what <html lang> says, what the pages and dates are written in,
 * and what Carbon's diffForHumans() uses. Only on public routes — the admin
 * panel stays in English.
 */
class SetSiteLanguage
{
    /** The languages the student side is translated into. */
    public const OFFERED = ['en' => 'English', 'bn' => 'বাংলা'];

    public function __construct(private readonly SiteSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $language = $request->session()->get('locale')
            ?? $request->user()?->locale
            ?? (string) $this->settings->get('site.language');

        if (array_key_exists($language, SiteSettings::LANGUAGES)) {
            app()->setLocale($language);
        }

        return $next($request);
    }
}
