<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAccountSecure;
use App\Http\Middleware\EnsureSiteIsOpen;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\RestrictAdminIp;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetSiteLanguage;
use App\Models\Redirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // routes/web.php requires routes/admin.php at the bottom.
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // One sign-in system, two doors: the panel's, and the students' on
        // the public site. Each sends people back through its own.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*')
            ? route('admin.login')
            : route('student.login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('admin', 'admin/*') && $request->user()?->isStaff()
            ? route('admin.panel')
            : route('student.dashboard'));
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'staff' => EnsureStaff::class,
            'site.open' => EnsureSiteIsOpen::class,
            'admin.ip' => RestrictAdminIp::class,
            'account.secure' => EnsureAccountSecure::class,
            'site.language' => SetSiteLanguage::class,
        ]);

        // Settings → Security: HTTPS and the protective headers, on every page.
        $middleware->web(append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Before showing "page not found", see whether the address moved
        // (Settings → Redirects). Live content always wins: this only runs
        // where nothing else answered.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
                return null;
            }

            $path = Redirect::normalise($request->getPathInfo());

            if ($path === '/admin' || str_starts_with($path, '/admin/')) {
                return null;
            }

            try {
                $redirect = Redirect::where('from_path', $path)->first();
            } catch (Throwable) {
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
