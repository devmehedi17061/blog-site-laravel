<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\Security;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders the single admin page. Screens are swapped client side by Alpine
 * through the hash, so there is one route rather than one per screen.
 */
class PanelController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('role.permissions');
        $security = app(Security::class);

        // Hosts with no cron never run the scheduler: the daily database
        // backup is taken here instead, after the page has been sent.
        if (\App\Support\DatabaseBackup::due()) {
            \Illuminate\Support\Facades\Cache::put('backup.last_at', now()->timestamp, 86400);
            app()->terminating(function () {
                try {
                    \App\Support\DatabaseBackup::run();
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        // …and so is installing updates by itself (Settings → Updates), once a day.
        if (app(\App\Support\SiteSettings::class)->get('updates.auto', 'none') !== 'none'
            && \Illuminate\Support\Facades\Cache::add('cms.update.auto_ran', now()->timestamp, 86400)) {
            app()->terminating(function () {
                try {
                    app(\App\Support\Updater::class)->autoInstall();
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }

        return view('backend.panel', [
            'panel' => [
                'user' => UserResource::make($user)->resolve(),
                // What the account owes before the rest of the panel opens;
                // the API enforces the same (EnsureAccountSecure).
                'owes' => match (true) {
                    $security->requiresTwoFactor($user) && ! $user->hasTwoFactor() => 'two_factor_required',
                    $security->passwordExpired($user) => 'password_expired',
                    default => null,
                },
                'recovery_notice' => session('recovery_notice'),
            ],
        ]);
    }
}
