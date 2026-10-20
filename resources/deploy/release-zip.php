<?php

/*
| Builds a core release for the in-panel updater (App\Support\Updater):
|
|   php resources/deploy/release-zip.php <version> [out-dir]
|
| Run after `composer install --no-dev --optimize-autoloader` and
| `npm run build` (the GitHub workflow .github/workflows/release.yml does
| all of it on every tag "v*"). Writes out-dir/cms-v<version>.zip and its
| .sha256, with the version in VERSION and the file list in .cms-files.
|
| The zip is the core only: never .env, storage/, sites/ (site packs),
| public/storage, public/site-build, tests, node_modules or anything that
| only lives on one machine.
*/

$root = str_replace('\\', '/', dirname(__DIR__, 2));
$version = ltrim((string) ($argv[1] ?? ''), 'vV');
$outDir = rtrim(str_replace('\\', '/', $argv[2] ?? $root.'/build-release'), '/');

if (! preg_match('/^\d+\.\d+\.\d+([.-][0-9A-Za-z.-]+)?$/', $version)) {
    fwrite(STDERR, "Usage: php resources/deploy/release-zip.php 1.2.3 [out-dir]\n");
    exit(1);
}
if (! is_file($root.'/public/build/manifest.json')) {
    fwrite(STDERR, "public/build is missing: run npm run build first.\n");
    exit(1);
}
// RELEASE_ALLOW_DEV=1 only for trying the updater locally, never for a real release.
if (! is_file($root.'/vendor/autoload.php') || (is_dir($root.'/vendor/phpunit') && ! getenv('RELEASE_ALLOW_DEV'))) {
    fwrite(STDERR, "vendor must be installed without dev packages: composer install --no-dev --optimize-autoloader\n");
    exit(1);
}

// Whole top-level folders / files that are never part of the core.
$skipTop = ['.git', '.github', 'node_modules', 'tests', 'storage', 'sites', 'deploy', 'design', 'SRS', 'backend', 'frontend',
    'build-release', '.env', '.env.backup', '.env.production', '.idea', '.vscode', '.fleet', '.zed', '.phpunit.cache',
    'phpunit.xml', '.phpunit.result.cache', 'Homestead.json', 'Homestead.yaml', '.cms-files'];
// Paths below the top level that stay on each machine.
$skipUnder = ['public/storage', 'public/site-build/', 'public/hot', 'public/site-hot', 'bootstrap/cache/'];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if ($file->isDir()) {
        continue;
    }
    $rel = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);
    $top = explode('/', $rel)[0];
    if (in_array($top, $skipTop, true) || $file->isLink()) {
        continue;
    }
    // Loose exports, archives and secrets in the root never ship.
    if (! str_contains($rel, '/') && preg_match('/\.(sql|zip|gz|tar|bak|log|txt)$/i', $rel) && $rel !== 'robots.txt') {
        continue;
    }
    foreach ($skipUnder as $prefix) {
        if ($rel === rtrim($prefix, '/') || str_starts_with($rel, $prefix) || str_starts_with($rel, rtrim($prefix, '/').'/')) {
            continue 2;
        }
    }
    $files[] = $rel;
}
// bootstrap/cache must exist on the server (empty).
sort($files);

@mkdir($outDir, 0777, true);
$zipPath = $outDir.'/cms-v'.$version.'.zip';
@unlink($zipPath);
$zip = new ZipArchive;
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot write {$zipPath}\n");
    exit(1);
}
$n = 0;
foreach ($files as $rel) {
    if ($rel === 'VERSION') {
        continue;
    }
    $zip->addFile($root.'/'.$rel, $rel);
    // libzip holds every added file open until close(); on Windows that runs out.
    if (++$n % 500 === 0 && (! $zip->close() || $zip->open($zipPath) !== true)) {
        fwrite(STDERR, "Could not write {$zipPath}\n");
        exit(1);
    }
}
$list = array_values(array_unique([...array_diff($files, ['VERSION']), 'VERSION', '.cms-files']));
sort($list);
$zip->addFromString('VERSION', $version);
$zip->addFromString('.cms-files', implode("\n", $list)."\n");
$zip->addEmptyDir('bootstrap/cache');
$zip->close();

$hash = hash_file('sha256', $zipPath);
file_put_contents($zipPath.'.sha256', $hash.'  '.basename($zipPath)."\n");

printf("%s — %d files, %.1f MB, sha256 %s\n", $zipPath, count($list), filesize($zipPath) / 1048576, $hash);
