<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // routes/web.php requires routes/admin.php at the bottom.
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.panel'));
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureAccountIsActive::class,
            'site.open' => \App\Http\Middleware\EnsureSiteIsOpen::class,
            'admin.ip' => \App\Http\Middleware\RestrictAdminIp::class,
            'account.secure' => \App\Http\Middleware\EnsureAccountSecure::class,
            'site.language' => \App\Http\Middleware\SetSiteLanguage::class,
        ]);

        // Settings → Security: HTTPS and the protective headers, on every page.
        $middleware->web(append: [\App\Http\Middleware\SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Before showing "page not found", see whether the address moved
        // (Settings → Redirects). Live content always wins: this only runs
        // where nothing else answered.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
                return null;
            }

            $path = \App\Models\Redirect::normalise($request->getPathInfo());

            if ($path === '/admin' || str_starts_with($path, '/admin/')) {
                return null;
            }

            try {
                $redirect = \App\Models\Redirect::where('from_path', $path)->first();
            } catch (\Throwable) {
                return null;
            }

            if (! $redirect) {
                return null;
            }

            $redirect->forceFill(['hits' => $redirect->hits + 1, 'last_hit_at' => now()])->saveQuietly();

            $to = $redirect->to_path;
            if ($query = $request->getQueryString()) {
                $to .= (str_contains($to, '?') ? '&' : '?').$query;
            }

            return redirect($to, $redirect->status);
        });
    })->create();
