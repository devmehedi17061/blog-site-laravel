<?php

namespace App\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * This site's pack: sites/<name>/, named by SITE_PACK in .env.
 *
 *   sites/<name>/resources/views/     overrides any core view of the same name
 *   sites/<name>/resources/css|js/    the site's own stylesheet / script
 *                                     (built by `npm run build:site` into
 *                                     public/site-build/)
 *   sites/<name>/src/                 PHP classes, namespace Sites\<Name>\
 *   sites/<name>/routes/console.php   its artisan commands
 *
 * Nothing in sites/ or public/site-build/ belongs to the core, so a core
 * update never touches it.
 */
class SitePack
{
    public const BUILD_DIRECTORY = 'site-build';

    public static function name(): string
    {
        return Str::slug((string) config('cms.site_pack'));
    }

    public static function path(string $append = ''): ?string
    {
        $name = self::name();
        $root = base_path('sites/'.$name);

        if ($name === '' || ! is_dir($root)) {
            return null;
        }

        return $append === '' ? $root : $root.'/'.ltrim($append, '/');
    }

    public static function active(): bool
    {
        return self::path() !== null;
    }

    /** Sites\<Name>\ — the pack's PHP classes. */
    public static function namespace(): string
    {
        return 'Sites\\'.Str::studly(self::name()).'\\';
    }

    /** Loads Sites\<Name>\Foo from sites/<name>/src/Foo.php. */
    public static function registerAutoloader(): void
    {
        $src = self::path('src');
        if (! $src) {
            return;
        }
        $prefix = self::namespace();

        spl_autoload_register(function (string $class) use ($prefix, $src) {
            if (str_starts_with($class, $prefix)) {
                $file = $src.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        });
    }

    /** The pack's own entry points, as `npm run build:site` builds them. @return array<int, string> */
    public static function entries(): array
    {
        $root = self::path();
        if (! $root) {
            return [];
        }

        return collect([...File::glob($root.'/resources/css/*.css'), ...File::glob($root.'/resources/js/*.js')])
            // Partials start with an underscore and are imported, not built.
            ->reject(fn ($f) => str_starts_with(basename($f), '_'))
            ->map(fn ($f) => 'sites/'.self::name().'/'.Str::after(str_replace('\\', '/', $f), '/sites/'.self::name().'/'))
            ->values()
            ->all();
    }

    private static function vite(): ?Vite
    {
        if (! self::entries() || ! is_file(public_path(self::BUILD_DIRECTORY.'/manifest.json'))) {
            return null;
        }

        return (new Vite)->useBuildDirectory(self::BUILD_DIRECTORY);
    }

    /** The <link>/<script> tags for the pack's assets, after the core's. */
    public static function tags(): HtmlString
    {
        $vite = self::vite();

        return $vite ? $vite(self::entries()) : new HtmlString('');
    }

    /** Built URLs of the pack's stylesheets / scripts, for the builder previews. @return array{css: array<int, string>, js: array<int, string>} */
    public static function assetUrls(): array
    {
        $vite = self::vite();
        $out = ['css' => [], 'js' => []];
        if (! $vite) {
            return $out;
        }
        foreach (self::entries() as $entry) {
            try {
                $out[str_ends_with($entry, '.css') ? 'css' : 'js'][] = $vite->asset($entry);
            } catch (\Throwable) {
                // Not built yet: `npm run build:site`.
            }
        }

        return $out;
    }
}
