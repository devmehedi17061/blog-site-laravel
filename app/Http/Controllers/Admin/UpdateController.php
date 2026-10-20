<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DatabaseBackup;
use App\Support\SitePack;
use App\Support\SiteSettings;
use App\Support\Updater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Settings → Updates: which core version this site runs, whether GitHub has
 * a newer one, and installing it (or going back) from the browser.
 * Seeing it needs settings.view; changing anything is Super Admin only.
 */
class UpdateController extends Controller
{
    public function __construct(private readonly Updater $updater, private readonly SiteSettings $settings) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.view'), 403);

        return response()->json($this->status());
    }

    public function check(Request $request): JsonResponse
    {
        $this->superAdmin($request);

        try {
            $this->updater->latest(true);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Could not reach GitHub: '.$e->getMessage()] + $this->status(), 502);
        }

        return response()->json($this->status());
    }

    public function install(Request $request): JsonResponse
    {
        $this->superAdmin($request);

        try {
            $log = $this->updater->install();
        } catch (Throwable $e) {
            return response()->json(['message' => 'The update did not finish — nothing of the site\'s content was changed.', 'errors' => ['log' => explode("\n", $e->getMessage())]] + $this->status(), 500);
        }

        Cache::forever('cms.update.last_log', $log);

        return response()->json(['message' => end($log), 'log' => $log] + $this->status());
    }

    public function rollback(Request $request): JsonResponse
    {
        $this->superAdmin($request);

        try {
            $version = $this->updater->rollback();
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()] + $this->status(), 422);
        }

        return response()->json(['message' => "The code of version {$version} is back. The database was left as it is."] + $this->status());
    }

    public function settings(Request $request): JsonResponse
    {
        $this->superAdmin($request);

        $data = $request->validate([
            'auto' => ['required', 'in:none,security,all'],
            'token' => ['nullable', 'string', 'max:255'],
            'token_clear' => ['nullable', 'boolean'],
        ]);
        $this->settings->save('updates', $data);
        Cache::forget('cms.update.latest');

        return response()->json(['message' => 'Update settings saved.'] + $this->status());
    }

    private function superAdmin(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only a Super Admin can change the CMS version.');
    }

    private function status(): array
    {
        $latest = null;
        $error = null;
        try {
            $latest = $this->updater->latest();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return [
            'current' => Updater::current(),
            'latest' => $latest,
            'available' => $this->updater->available($latest),
            'checked_at' => Cache::get('cms.update.checked_at'),
            'error' => $error,
            'repository' => $this->updater->repository(),
            'auto' => (string) $this->settings->get('updates.auto', 'none'),
            'token_set' => filled($this->settings->get('updates.token')) || filled(config('cms.updates.token')),
            'site_pack' => SitePack::name() ?: null,
            'code_backups' => Updater::codeBackups(),
            'database_backups' => array_slice(DatabaseBackup::list(), 0, 5),
            'last_log' => Cache::get('cms.update.last_log', []),
            'zip_available' => class_exists(\ZipArchive::class),
        ];
    }
}
