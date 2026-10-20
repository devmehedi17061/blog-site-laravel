<?php

namespace App\Providers;

use App\Support\SitePack;
use Illuminate\Support\ServiceProvider;

/**
 * Plugs this site's pack (sites/<name>/, SITE_PACK in .env) into the core:
 * its classes, its views ahead of the core's, and its artisan commands.
 * With no pack the core runs on its own.
 */
class SitePackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        SitePack::registerAutoloader();
    }

    public function boot(): void
    {
        if ($views = SitePack::path('resources/views')) {
            if (is_dir($views)) {
                $this->app['view']->getFinder()->prependLocation($views);
            }
        }

        if ($this->app->runningInConsole() && ($console = SitePack::path('routes/console.php')) && is_file($console)) {
            require $console;
        }
    }
}
