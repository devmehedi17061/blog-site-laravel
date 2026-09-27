<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * The header's "Clear cache" button.
 *
 * Clears what can go stale — compiled Blade views, cached config, routes
 * and events, and the settings cache — so a change on disk or in Settings
 * shows at once. It deliberately does not flush the whole cache store: that
 * also holds sign-in lockout counters, spam tallies and emailed sign-in
 * codes, and wiping those would let a password guesser start over.
 */
class CacheController extends Controller
{
    private const STEPS = [
        'view:clear' => 'Compiled page templates',
        'config:clear' => 'Cached configuration',
        'route:clear' => 'Cached routes',
        'event:clear' => 'Cached events',
    ];

    public function clear(Request $request, SiteSettings $settings): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.update'), 403);

        $cleared = [];
        $failed = [];

        foreach (self::STEPS as $command => $label) {
            try {
                Artisan::call($command);
                $cleared[] = $label;
            } catch (Throwable $e) {
                report($e);
                $failed[] = $label;
            }
        }

        $settings->forget();
        $cleared[] = 'Settings';

        ActivityLog::record('Cleared cache', implode(', ', $cleared));

        return response()->json([
            'message' => $failed
                ? 'Cache partly cleared — could not clear: '.implode(', ', $failed).'.'
                : 'Cache cleared: '.strtolower(implode(', ', $cleared)).'.',
            'cleared' => $cleared,
            'failed' => $failed,
        ], $failed ? 500 : 200);
    }
}
