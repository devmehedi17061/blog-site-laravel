<?php

namespace App\Providers;

use App\Support\FormRenderer;
use App\Support\Security;
use App\Support\Site;
use App\Support\SiteSettings;
use App\View\Composers\MenuComposer;
use App\View\Composers\StudentPanelComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Shared for the whole request: a page can hold the same form in more
        // than one section, and the renderer only builds each one once.
        $this->app->singleton(FormRenderer::class);

        // Read once per request, however many places ask.
        $this->app->singleton(SiteSettings::class);
        $this->app->singleton(Security::class);
        // Builds both menus once per request, for the header and the footer.
        $this->app->singleton(MenuComposer::class);
        $this->app->singleton(StudentPanelComposer::class);

        // The Email tab decides how mail leaves the site. Applied when the
        // mailer is first built, so requests that send nothing read nothing.
        $this->app->afterResolving('mail.manager', function () {
            $this->app->make(SiteSettings::class)->applyMailConfig();
        });
    }

    public function boot(): void
    {
        // The Site tab's name replaces APP_NAME everywhere, admin included.
        $this->app->make(SiteSettings::class)->applySiteConfig();

        // Settings → Security: how long an idle panel session lasts.
        config(['session.lifetime' => max(5, (int) $this->app->make(SiteSettings::class)->get('security.idle_minutes', 120))]);

        // `$site` in every view: the name, titles, dates, logo, contacts.
        View::share('site', $this->app->make(Site::class));

        // Submissions per minute from one address, from Settings → Spam protection.
        RateLimiter::for('forms', fn (Request $request) => Limit::perMinute(
            max(1, (int) $this->app->make(SiteSettings::class)->get('spam.rate_limit', 12))
        )->by($request->ip()));

        View::composer(
            ['frontend.partials.header', 'frontend.partials.footer'],
            MenuComposer::class
        );

        // The student panel's sidebar counts.
        View::composer('frontend.student.*', StudentPanelComposer::class);
    }
}
