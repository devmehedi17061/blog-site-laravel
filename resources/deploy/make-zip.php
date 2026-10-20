<?php

/*
| Builds the upload for shared (cPanel) hosting:
|
|   php resources/deploy/make-zip.php [out.zip] [--media] [--sql=dump.sql]
|                                        (default deploy/site-deploy.zip)
|
| Run it after `composer install --no-dev` and `npm run build`. The zip holds
|
|   laravel/       the app — outside the web root, so .env, vendor and
|                  storage can never be downloaded
|   public_html/   the web root: index.php (pointing at ../laravel), build,
|                  images, and install.php for hosts without a terminal
|   DEPLOY-GUIDE.txt
|
| For moving this site's own content to the server (instead of a fresh
| install), --media adds the uploaded files (storage/app/public, with their
| WebP copies) and --sql adds a database export as database.sql beside the
| two folders — never inside public_html, where it could be downloaded.
|
| Never shipped: .env and other secrets, .git, node_modules, tests, the
| design source, logs, caches and backups.
*/

$root = str_replace('\\', '/', dirname(__DIR__, 2));
$args = array_slice($argv, 1);
$withMedia = in_array('--media', $args, true);
$sql = null;
foreach ($args as $a) {
    if (str_starts_with($a, '--sql=')) {
        $sql = substr($a, 6);
    }
}
$plain = array_values(array_filter($args, fn ($a) => ! str_starts_with($a, '--')));
$out = $plain[0] ?? $root.'/deploy/site-deploy.zip';
if ($sql !== null && ! is_file($sql)) {
    fwrite(STDERR, "No such SQL file: {$sql}\n");
    exit(1);
}

$skipTop = ['deploy', '.git', '.github', 'node_modules', 'tests', 'design', 'SRS', 'frontend', 'backend', '.env', '.env.backup', 'recovery-codes.txt', 'phpunit.xml', '.editorconfig'];
// This site's own pack goes with it (SITE_PACK in .env); other packs do not.
$pack = '';
if (is_file($root.'/.env') && preg_match('/^SITE_PACK=([a-z0-9-]+)/mi', (string) file_get_contents($root.'/.env'), $m)) {
    $pack = strtolower($m[1]);
}
$skipUnder = [
    'storage/logs/', 'storage/framework/cache/', 'storage/framework/sessions/', 'storage/framework/views/', 'storage/framework/testing/',
    'storage/app/private/', 'bootstrap/cache/', 'resources/deploy/',
    ...($withMedia ? [] : ['storage/app/public/']),
];
// Folders that must exist (empty) on the server.
$keepDirs = [
    'storage/logs', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views',
    'storage/app/public', 'storage/app/private', 'bootstrap/cache',
];

if (! is_file($root.'/public/build/manifest.json')) {
    fwrite(STDERR, "public/build is missing: run npm run build first.\n");
    exit(1);
}
if (is_dir($root.'/vendor/phpunit')) {
    fwrite(STDERR, "vendor has dev packages: run composer install --no-dev first.\n");
    exit(1);
}

@mkdir(dirname($out), 0777, true);
@unlink($out);
$zip = new ZipArchive;
if ($zip->open($out, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot write {$out}\n");
    exit(1);
}

$count = ['laravel' => 0, 'public_html' => 0];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

foreach ($it as $file) {
    $rel = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);

    if (in_array(explode('/', $rel)[0], $skipTop, true) || $file->isLink()) {
        continue;
    }
    // Exports and archives lying in the project root (a database dump holds
    // password hashes and mail passwords) never go in the upload.
    if (! str_contains($rel, '/') && preg_match('/\.(sql|zip|gz|tar|bak|log)$/i', $rel)) {
        continue;
    }
    if (str_starts_with($rel, 'sites/') && ($pack === '' || ! str_starts_with($rel.'/', 'sites/'.$pack.'/'))) {
        continue;
    }
    foreach ($skipUnder as $prefix) {
        if (str_starts_with($rel.'/', $prefix) && $rel.'/' !== $prefix && basename($rel) !== '.gitignore') {
            continue 2;
        }
    }
    // public/storage is the local link to storage/app/public (on Windows PHP
    // does not see it as one); the server makes its own.
    if (in_array($rel, ['public/hot', 'public/index.php', 'public/storage'], true) || str_starts_with($rel, 'public/storage/')) {
        continue;
    }
    if ($file->isDir()) {
        continue;
    }

    // libzip holds every added file open until close(); on Windows that
    // runs out of handles, so write out every 500 files.
    if ((array_sum($count) + 1) % 500 === 0 && (! $zip->close() || $zip->open($out) !== true)) {
        fwrite(STDERR, "Could not write {$out}\n");
        exit(1);
    }

    $name = str_starts_with($rel, 'public/') ? 'public_html/'.substr($rel, 7) : 'laravel/'.$rel;
    $zip->addFile($file->getPathname(), $name);
    $count[explode('/', $name)[0]]++;
}

foreach ($keepDirs as $dir) {
    $zip->addEmptyDir('laravel/'.$dir);
}

$zip->addFile(__DIR__.'/install.php', 'public_html/install.php');
$zip->addFile(__DIR__.'/fix-storage.php', 'public_html/fix-storage.php');
$zip->addFile(__DIR__.'/DEPLOY-GUIDE.txt', 'DEPLOY-GUIDE.txt');
if ($sql !== null) {
    $zip->addFile($sql, 'database.sql');
}

// The web entry point, reaching into ../laravel.
$zip->addFromString('public_html/index.php', <<<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// The app lives in ../laravel, outside the web root (shared hosting).
$app_dir = __DIR__.'/../laravel';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $app_dir.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $app_dir.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $app_dir.'/bootstrap/app.php';

// This folder is the public one, not ../laravel/public.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());

PHP);

$zip->addFromString('laravel/.env.production.example', <<<'ENV'
# Copy to .env on the server and fill in. Never upload your local .env.
APP_NAME="My site"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://your-domain.com

# public_html sits beside this folder
APP_PUBLIC_PATH=../public_html

# Only while using public_html/install.php (no terminal). Any long random
# text, at least 12 characters. Remove it once the site is installed.
INSTALL_TOKEN=

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

# Mail is set in the admin: Settings → Mail

ENV);

if (! $zip->close()) {
    fwrite(STDERR, "Could not write {$out}\n");
    exit(1);
}

echo "Wrote {$out}\n";
echo "  laravel/: {$count['laravel']} files, public_html/: ".($count['public_html'] + 2)." files\n";
echo '  uploaded media: '.($withMedia ? 'included' : 'not included').', database.sql: '.($sql !== null ? 'included' : 'not included')."\n";
