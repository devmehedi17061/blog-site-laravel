<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Core updates from GitHub Releases, without a terminal.
 *
 * Each release (built by .github/workflows/release.yml) carries
 * cms-vX.Y.Z.zip — the whole core, vendor/ and public/build/ included —
 * and cms-vX.Y.Z.zip.sha256. Installing one:
 *
 *   1. backs up the database (DatabaseBackup) and the current core code;
 *   2. downloads the zip and checks its SHA-256;
 *   3. copies the core files over this one — never .env, storage/, sites/
 *      (this site's pack), public/storage or public/site-build — and removes
 *      core files the previous release had that this one dropped;
 *   4. runs the database migrations and clears the compiled caches.
 *
 * If anything fails after the copy started, the previous code is put back.
 * The site's content (database), uploads and pack are never touched.
 */
class Updater
{
    /** Paths in this install that no update may write to or remove. */
    public const PROTECTED = [
        '.env', '.env.backup', '.env.production', '.htaccess-local', 'storage/', 'sites/', 'deploy/',
        'public/storage', 'public/site-build/', 'public/hot', 'public/site-hot', 'public/.user.ini',
        // The host's own: on cPanel index.php points at ../laravel and .htaccess
        // carries the PHP-version handler. Release notes say when to change them.
        'public/index.php', 'public/.htaccess', 'public/install.php', 'public/fix-storage.php',
        'bootstrap/cache/', 'node_modules/', '.git/',
    ];

    /** The file list a release ships, so the next one can remove what it dropped. */
    public const MANIFEST = '.cms-files';

    /** Core code backups kept (each is a whole core, vendor/ included). */
    private const KEEP_CODE_BACKUPS = 2;

    private ?\Closure $log = null;

    public function __construct(private readonly SiteSettings $settings) {}

    public static function current(): string
    {
        return (string) config('cms.version');
    }

    public function repository(): string
    {
        return (string) config('cms.updates.repository');
    }

    private function token(): string
    {
        return (string) (config('cms.updates.token') ?: $this->settings->get('updates.token', ''));
    }

    private function github(): \Illuminate\Http\Client\PendingRequest
    {
        $http = Http::timeout(30)->withHeaders([
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'cms-updater',
            'X-GitHub-Api-Version' => '2022-11-28',
        ]);

        return $this->token() !== '' ? $http->withToken($this->token()) : $http;
    }

    // ── what is there ───────────────────────────────────────────────────

    /**
     * The newest release on GitHub, remembered for a few hours.
     *
     * @return array{version: string, tag: string, name: string, notes: string, published_at: ?string, url: string, security: bool, zip: ?int, sha: ?int}|null
     */
    public function latest(bool $fresh = false): ?array
    {
        $key = 'cms.update.latest';
        if (! $fresh && Cache::has($key)) {
            return Cache::get($key);
        }

        $response = $this->github()->get('https://api.github.com/repos/'.$this->repository().'/releases/latest');
        if ($response->status() === 404) {
            Cache::put($key, null, now()->addHours((int) config('cms.updates.check_every', 6)));

            return null;
        }
        if (! $response->successful()) {
            throw new RuntimeException('GitHub answered '.$response->status().($response->status() === 401 ? ' — check the update token.' : '.'));
        }

        $release = $response->json();
        $tag = (string) $release['tag_name'];
        $assets = collect($release['assets'] ?? []);
        $zip = $assets->first(fn ($a) => str_ends_with($a['name'], '.zip') && str_starts_with($a['name'], 'cms-'));
        $sha = $zip ? $assets->firstWhere('name', $zip['name'].'.sha256') : null;
        $notes = (string) ($release['body'] ?? '');

        $info = [
            'version' => ltrim($tag, 'vV'),
            'tag' => $tag,
            'name' => (string) ($release['name'] ?: $tag),
            'notes' => $notes,
            'published_at' => $release['published_at'] ?? null,
            'url' => (string) ($release['html_url'] ?? ''),
            // Marked "[security]" in its title or notes.
            'security' => (bool) preg_match('/\[security\]|\bsecurity\b/i', $release['name'].' '.$notes),
            'zip' => $zip['id'] ?? null,
            'sha' => $sha['id'] ?? null,
        ];
        Cache::put($key, $info, now()->addHours((int) config('cms.updates.check_every', 6)));
        Cache::put('cms.update.checked_at', now()->toIso8601String(), now()->addDays(30));

        return $info;
    }

    public function available(?array $latest): bool
    {
        return $latest !== null && $latest['zip'] !== null && version_compare($latest['version'], self::current(), '>');
    }

    // ── installing ─────────────────────────────────────────────────────

    /**
     * Installs the newest release, or a zip already on disk (for a manual
     * upload or a test). Returns what it did, line by line.
     *
     * @return array<int, string>
     */
    public function install(?string $zipPath = null, ?\Closure $log = null): array
    {
        $this->log = $log;
        $lines = [];
        $say = function (string $line) use (&$lines) {
            $lines[] = $line;
            if ($this->log) {
                ($this->log)($line);
            }
        };

        $lock = Cache::lock('cms.update.running', 900);
        if (! $lock->get()) {
            throw new RuntimeException('An update is already running.');
        }

        @set_time_limit(0);
        @ignore_user_abort(true);
        $work = storage_path('app/private/updates/work-'.now()->format('YmdHis'));
        $codeBackup = null;
        $copied = false;
        $from = self::current();

        try {
            if ($zipPath === null) {
                $latest = $this->latest(true);
                if (! $this->available($latest)) {
                    throw new RuntimeException('This site is already on the newest version ('.$from.').');
                }
                $zipPath = $this->download($latest, $say);
            }

            File::ensureDirectoryExists($work);
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true || ! $zip->extractTo($work)) {
                throw new RuntimeException('The update zip could not be opened.');
            }
            $zip->close();
            foreach (['VERSION', 'artisan', 'vendor/autoload.php', 'app/Support/Updater.php', 'public/build/manifest.json', self::MANIFEST] as $must) {
                if (! is_file($work.'/'.$must)) {
                    throw new RuntimeException("The update zip is not a CMS release (no {$must}).");
                }
            }
            $to = trim((string) file_get_contents($work.'/VERSION'));
            $say("Unpacked version {$to}.");

            $say('Backing up the database …');
            $say('Database backup: '.basename(DatabaseBackup::run()));
            $say('Backing up the current code …');
            $codeBackup = $this->backupCode($from);
            $say('Code backup: '.basename($codeBackup));

            $say('Copying the new files …');
            $before = is_file(base_path(self::MANIFEST)) ? file(base_path(self::MANIFEST), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
            $copied = true;
            $count = $this->copyTree($work);
            $removed = $this->removeDropped($work, $before);
            $say("{$count} files written, {$removed} old files removed.");

            $this->afterCopy($say);

            ActivityLog::record('Updated the CMS', "From {$from} to {$to}");
            config(['cms.version' => $to]);
            $say("Done: the CMS is now version {$to}.");

            return $lines;
        } catch (Throwable $e) {
            report($e);
            $say('Stopped: '.$e->getMessage());
            if ($copied && $codeBackup) {
                $say('Putting the previous code back …');
                try {
                    $this->restoreCode($codeBackup);
                    $this->clearCompiled();
                    $say('The previous version ('.$from.') is back.');
                } catch (Throwable $restore) {
                    report($restore);
                    $say('Could not put the previous code back: '.$restore->getMessage().' — restore '.basename($codeBackup).' by hand.');
                }
            }
            throw new RuntimeException(implode("\n", $lines), 0, $e);
        } finally {
            File::deleteDirectory($work);
            $lock->release();
        }
    }

    private function download(array $latest, \Closure $say): string
    {
        $dir = storage_path('app/private/updates');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/cms-'.$latest['tag'].'.zip';
        $asset = fn (int $id) => $this->github()->withHeaders(['Accept' => 'application/octet-stream'])->timeout(600)
            ->get('https://api.github.com/repos/'.$this->repository().'/releases/assets/'.$id);

        $say('Downloading '.$latest['tag'].' …');
        // Straight to disk: the zip is tens of MB, more than some hosts let PHP hold.
        $response = $this->github()->withHeaders(['Accept' => 'application/octet-stream'])->timeout(600)
            ->withOptions(['sink' => $path])
            ->get('https://api.github.com/repos/'.$this->repository().'/releases/assets/'.$latest['zip']);
        if (! $response->successful()) {
            File::delete($path);
            throw new RuntimeException('The download failed ('.$response->status().').');
        }

        if ($latest['sha']) {
            $expected = strtolower(strtok(trim($asset($latest['sha'])->body()), " \t"));
            if (! hash_equals($expected, hash_file('sha256', $path))) {
                File::delete($path);
                throw new RuntimeException('The download is damaged or was altered (SHA-256 does not match). Nothing was changed.');
            }
            $say('Checksum verified.');
        }

        return $path;
    }

    private static function isProtected(string $relative): bool
    {
        foreach (self::PROTECTED as $p) {
            if ($relative === rtrim($p, '/') || (str_ends_with($p, '/') && str_starts_with($relative, $p))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Where a file of the release goes here: public/… into this site's real
     * web root (public_html on shared hosting), everything else beside artisan.
     */
    public static function target(string $relative): string
    {
        return str_starts_with($relative, 'public/') ? public_path(substr($relative, 7)) : base_path($relative);
    }

    /** Copies every core file from $from over this install. */
    private function copyTree(string $from): int
    {
        $count = 0;
        foreach (File::allFiles($from, true) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if (self::isProtected($relative)) {
                continue;
            }
            $target = self::target($relative);
            File::ensureDirectoryExists(dirname($target));
            if (! @copy($file->getPathname(), $target)) {
                throw new RuntimeException("Could not write {$relative} — check the folder's permissions.");
            }
            $count++;
        }

        return $count;
    }

    /**
     * Core files the installed release listed but the new one does not
     * (a class that was deleted, an old asset) are removed. Only files a
     * release put there are ever candidates.
     */
    private function removeDropped(string $from, array $old): int
    {
        $new = file($from.'/'.self::MANIFEST, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $removed = 0;
        foreach (array_diff($old, $new) as $relative) {
            if (! self::isProtected($relative) && ! str_contains($relative, '..') && is_file(self::target($relative))) {
                @unlink(self::target($relative)) && $removed++;
            }
        }
        // public/build is rebuilt whole each release: stale hashed files go.
        $keep = array_flip(array_filter($new, fn ($f) => str_starts_with($f, 'public/build/')));
        foreach (is_dir(public_path('build')) ? File::allFiles(public_path('build')) : [] as $file) {
            $relative = 'public/build/'.str_replace('\\', '/', $file->getRelativePathname());
            if (! isset($keep[$relative])) {
                @unlink($file->getPathname()) && $removed++;
            }
        }
        return $removed;
    }

    private function afterCopy(\Closure $say): void
    {
        $this->clearCompiled();
        $say('Running database migrations …');
        $code = Artisan::call('migrate', ['--force' => true]);
        $say(trim(Artisan::output()) ?: 'Nothing to migrate.');
        if ($code !== 0) {
            throw new RuntimeException('The database migrations failed.');
        }
        foreach (['view:clear', 'config:clear', 'route:clear', 'event:clear'] as $command) {
            Artisan::call($command);
        }
        $this->settings->forget();
        Cache::forget('cms.update.latest');
        $say('Caches cleared.');
    }

    /** The framework's compiled lists (packages, services, config …) are rebuilt from the new vendor/. */
    private function clearCompiled(): void
    {
        foreach (File::glob(base_path('bootstrap/cache/*.php')) as $file) {
            @unlink($file);
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    // ── code backups ───────────────────────────────────────────────────

    public static function backupDirectory(): string
    {
        return storage_path('app/private/update-backups');
    }

    /**
     * A copy of the current core (what an update would replace) as a plain
     * folder: core-<version>-<time>/ with the same layout as a release, and
     * meta.json beside it. A folder rather than a zip, because writing a
     * large zip fails on some hosts (and on Windows while a virus scanner
     * looks at it); copying files never does.
     */
    public function backupCode(?string $version = null): string
    {
        File::ensureDirectoryExists(self::backupDirectory(), 0700);
        $dir = self::backupDirectory().'/core-'.($version ?? self::current()).'-'.now()->format('Ymd-His');
        File::ensureDirectoryExists($dir, 0700);

        $roots = ['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'vendor'];
        $files = ['artisan', 'composer.json', 'composer.lock', 'package.json', 'VERSION', self::MANIFEST];
        $count = 0;
        $bytes = 0;
        $copy = function (string $from, string $relative) use ($dir, &$count, &$bytes) {
            $to = $dir.'/'.$relative;
            File::ensureDirectoryExists(dirname($to));
            if (! @copy($from, $to)) {
                throw new RuntimeException("Could not back up {$relative}.");
            }
            $count++;
            $bytes += (int) @filesize($from);
        };

        foreach ($roots as $root) {
            $source = $root === 'public' ? public_path() : base_path($root);
            if (! is_dir($source)) {
                continue;
            }
            foreach (File::allFiles($source, true) as $file) {
                $relative = $root.'/'.str_replace('\\', '/', $file->getRelativePathname());
                if (! self::isProtected($relative)) {
                    $copy($file->getPathname(), $relative);
                }
            }
        }
        foreach ($files as $file) {
            if (is_file(base_path($file))) {
                $copy(base_path($file), $file);
            }
        }
        File::put($dir.'/meta.json', json_encode(['version' => $version ?? self::current(), 'files' => $count, 'bytes' => $bytes, 'at' => now()->toIso8601String()]));

        // Each copy is a whole core with vendor/: keep only the newest few.
        collect(File::directories(self::backupDirectory()))->filter(fn ($d) => str_starts_with(basename($d), 'core-'))
            ->sortDesc()->slice(self::KEEP_CODE_BACKUPS)->each(fn ($d) => File::deleteDirectory($d));

        return $dir;
    }

    /** Puts a code backup back (the database is left as it is). */
    public function restoreCode(string $backup): void
    {
        if (! is_file($backup.'/meta.json')) {
            throw new RuntimeException('That code backup is incomplete.');
        }
        foreach (File::allFiles($backup, true) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if ($relative === 'meta.json' || self::isProtected($relative)) {
                continue;
            }
            $target = self::target($relative);
            File::ensureDirectoryExists(dirname($target));
            if (! @copy($file->getPathname(), $target)) {
                throw new RuntimeException("Could not write {$relative}.");
            }
        }
    }

    /** Rolls back to the newest code backup. @return string the version restored */
    public function rollback(): string
    {
        $latest = collect(self::codeBackups())->first();
        if (! $latest) {
            throw new RuntimeException('There is no earlier version to go back to.');
        }
        $this->restoreCode(self::backupDirectory().'/'.$latest['name']);
        $this->clearCompiled();
        foreach (['view:clear', 'config:clear', 'route:clear', 'event:clear'] as $command) {
            Artisan::call($command);
        }
        Cache::forget('cms.update.latest');
        ActivityLog::record('Rolled the CMS back', 'To '.$latest['version'].' ('.$latest['name'].')');
        config(['cms.version' => $latest['version']]);

        return $latest['version'];
    }

    /** @return array<int, array{name: string, version: string, size: int, at: string}> */
    public static function codeBackups(): array
    {
        if (! is_dir(self::backupDirectory())) {
            return [];
        }

        return collect(File::directories(self::backupDirectory()))
            ->filter(fn ($d) => str_starts_with(basename($d), 'core-') && is_file($d.'/meta.json'))
            ->sortDesc()->values()
            ->map(function ($d) {
                $meta = json_decode((string) file_get_contents($d.'/meta.json'), true) ?: [];

                return ['name' => basename($d), 'version' => (string) ($meta['version'] ?? '?'), 'size' => (int) ($meta['bytes'] ?? 0), 'at' => date('Y-m-d H:i', filemtime($d))];
            })
            ->all();
    }

    // ── installing by itself ───────────────────────────────────────────

    /**
     * Settings → Updates → "Install by itself": security releases, or every
     * release. Run daily by the scheduler, and from the panel on hosts
     * without cron. Returns the version installed, or null.
     */
    public function autoInstall(): ?string
    {
        $mode = (string) $this->settings->get('updates.auto', 'none');
        if ($mode === 'none') {
            return null;
        }
        $latest = $this->latest(true);
        if (! $this->available($latest) || ($mode === 'security' && ! $latest['security'])) {
            return null;
        }
        $this->install();

        return $latest['version'];
    }
}