<?php

/*
| One-time installer for hosting without a terminal (shared cPanel).
|
| Copied into public_html/ by the deploy zip. It runs what the terminal
| steps would — `cms:install` (key, tables, library, first admin, storage
| link) and, when .env names one in SITE_PACK_INSTALL, the site pack's own
| set-up command (e.g. nmc:install) — from a browser form.
|
| Locked three ways: it does nothing unless INSTALL_TOKEN is set in .env,
| the form must repeat that token, and once it has run it writes
| storage/app/installed.lock and refuses to run again.
|
| DELETE THIS FILE FROM public_html WHEN THE SITE IS UP.
*/

$appDir = __DIR__.'/../laravel';

if (! is_file($appDir.'/vendor/autoload.php')) {
    exit('The laravel folder was not found next to public_html.');
}

require $appDir.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once $appDir.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$h = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$token = (string) env('INSTALL_TOKEN', '');
$lock = storage_path('app/installed.lock');

// ── what the server has ────────────────────────────────────────────────
$checks = [
    'PHP 8.2 or newer ('.PHP_VERSION.')' => version_compare(PHP_VERSION, '8.2.0', '>='),
];
foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'gd', 'zip'] as $ext) {
    $checks["PHP extension: {$ext}"] = extension_loaded($ext);
}
$checks['.env file exists (laravel/.env)'] = is_file($appDir.'/.env');
$checks['.env can be written (for the app key)'] = is_writable($appDir.'/.env');
$checks['laravel/storage can be written'] = is_writable(storage_path()) && is_writable(storage_path('framework'));
$checks['laravel/bootstrap/cache can be written'] = is_writable($appDir.'/bootstrap/cache');
try {
    \Illuminate\Support\Facades\DB::connection()->getPdo();
    $checks['Database connection (DB_… in .env)'] = true;
} catch (\Throwable $e) {
    $checks['Database connection (DB_… in .env): '.$e->getMessage()] = false;
}
$ready = ! in_array(false, $checks, true);

// ── the run ────────────────────────────────────────────────────────────
$log = null;
$error = null;
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = fn ($key) => trim((string) ($_POST[$key] ?? ''));

    if (strlen($token) < 12) {
        $error = 'Set INSTALL_TOKEN in laravel/.env first (at least 12 characters).';
    } elseif (! hash_equals($token, $in('token'))) {
        $error = 'The install token does not match INSTALL_TOKEN in .env.';
    } elseif (is_file($lock)) {
        $error = 'This site is already installed. Delete install.php.';
    } elseif (! $ready) {
        $error = 'Fix the red items first.';
    } elseif (! filter_var($in('email'), FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a real email address for the admin.';
    } elseif (strlen($in('password')) < 10) {
        $error = 'Use an admin password of at least 10 characters.';
    } else {
        @set_time_limit(600);
        $log = '';
        $steps = [
            ['cms:install', ['--site' => $in('site') ?: 'My site', '--name' => $in('name') ?: 'Admin', '--email' => $in('email'), '--password' => $in('password')]],
        ];
        // The site pack's own set-up, when .env asks for one (SITE_PACK_INSTALL=nmc:install).
        if (preg_match('/^[a-z0-9-]+:[a-z0-9:-]+$/', $packInstall = (string) env('SITE_PACK_INSTALL', ''))) {
            $steps[] = [$packInstall, []];
        }
        $ok = true;
        foreach ($steps as [$command, $options]) {
            $log .= "▶ php artisan {$command}\n";
            try {
                $code = $kernel->call($command, $options);
                $log .= $kernel->output()."\n";
                if ($code !== 0) {
                    $ok = false;
                    $log .= "✖ {$command} stopped (exit {$code}).\n\n";
                    break;
                }
            } catch (\Throwable $e) {
                $ok = false;
                $log .= '✖ '.$e->getMessage()."\n\n";
                break;
            }
        }

        // cms:install makes the link; say so plainly if the host forbids it.
        $linked = file_exists(public_path('storage'));
        $log .= $linked
            ? "✔ public_html/storage link is in place.\n"
            : "✖ Could not make public_html/storage (the host may block symlinks). Uploaded images will not show until it exists — ask your host to enable symlink(), or to create the link.\n";

        if ($ok) {
            file_put_contents($lock, date('c'));
            $done = true;
        } else {
            $error = 'Something failed — read the log below. You can fix it and run this again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Install the site</title>
<style>
  body { margin: 0; font: 15px/1.6 system-ui, sans-serif; color: #141A2E; background: #EEF3FC; }
  main { max-width: 720px; margin: 40px auto; padding: 32px; background: #fff; border-top: 4px solid #2F5FC4; border-radius: 16px; box-shadow: 0 14px 40px rgba(34, 38, 107, .08); }
  h1 { margin: 0 0 4px; color: #22266B; font-size: 26px; }
  .muted { color: #5A6178; margin: 0 0 24px; }
  ul.checks { list-style: none; padding: 0; margin: 0 0 24px; }
  ul.checks li { padding: 6px 0; border-bottom: 1px solid #EEF0F4; }
  .ok { color: #0B6B3A; } .bad { color: #B42318; font-weight: 600; }
  label { display: block; margin: 14px 0 4px; font-weight: 600; color: #22266B; }
  input { box-sizing: border-box; width: 100%; padding: 11px 14px; font: inherit; border: 1px solid #DDE3EE; border-radius: 10px; }
  button { margin-top: 22px; padding: 13px 28px; font: 600 15px system-ui; color: #fff; background: linear-gradient(90deg, #22266B, #2F5FC4); border: 0; border-radius: 999px; cursor: pointer; }
  .alert { padding: 14px 18px; border-radius: 10px; margin: 0 0 20px; }
  .alert.err { color: #9A3412; background: #FDEBC8; } .alert.win { color: #0B6B3A; background: #E4F6EC; }
  pre { white-space: pre-wrap; padding: 16px; font-size: 13px; background: #0A1026; color: #DCE3F5; border-radius: 10px; }
</style>
</head>
<body>
<main>
  <h1>Install the site</h1>
  <p class="muted">Runs the setup that would otherwise need a terminal. Use it once, then delete <b>public_html/install.php</b>.</p>

  <?php if ($error): ?><p class="alert err"><?= $h($error) ?></p><?php endif; ?>

  <?php if ($done): ?>
    <p class="alert win"><b>Done.</b> The site is installed. Now <b>delete public_html/install.php</b> and remove INSTALL_TOKEN from .env.
      Then sign in at <a href="/admin">/admin</a> with the email and password you chose.</p>
  <?php endif; ?>

  <?php if ($log !== null): ?><pre><?= $h($log) ?></pre><?php endif; ?>

  <?php if (! $done): ?>
    <h2>Server check</h2>
    <ul class="checks">
      <?php foreach ($checks as $label => $pass): ?>
        <li class="<?= $pass ? 'ok' : 'bad' ?>"><?= $pass ? '✔' : '✖' ?> <?= $h($label) ?></li>
      <?php endforeach; ?>
      <li class="<?= strlen($token) >= 12 ? 'ok' : 'bad' ?>"><?= strlen($token) >= 12 ? '✔' : '✖' ?> INSTALL_TOKEN is set in .env</li>
      <li class="<?= is_file($lock) ? 'bad' : 'ok' ?>"><?= is_file($lock) ? '✖ Already installed' : '✔ Not installed yet' ?></li>
    </ul>

    <form method="post" autocomplete="off">
      <label for="token">Install token (INSTALL_TOKEN from .env)</label>
      <input id="token" name="token" type="password" required>
      <label for="site">Site name</label>
      <input id="site" name="site" value="" placeholder="My site">
      <label for="name">Admin name</label>
      <input id="name" name="name" value="Admin">
      <label for="email">Admin email</label>
      <input id="email" name="email" type="email" required>
      <label for="password">Admin password (at least 10 characters)</label>
      <input id="password" name="password" type="password" minlength="10" required>
      <button type="submit">Install</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
