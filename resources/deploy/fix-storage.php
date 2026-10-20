<?php

/*
| Uploaded images (/storage/…) for hosting without a terminal (shared cPanel).
|
| Copied into public_html/ by the deploy zip, like install.php. It shows
| whether public_html/storage — the link to laravel/storage/app/public that
| `php artisan storage:link` makes — is in place, and makes it with one
| click. Where the host forbids symlinks, it says so: the site still shows
| the images through its own /storage route (PublicStorageController), just
| a little slower than a link.
|
| Locked like install.php: it does nothing unless INSTALL_TOKEN is set in
| .env, and the form must repeat that token.
|
| DELETE THIS FILE FROM public_html WHEN THE IMAGES SHOW.
*/

$appDir = __DIR__.'/../laravel';

if (! is_file($appDir.'/vendor/autoload.php')) {
    exit('The laravel folder was not found next to public_html.');
}

require $appDir.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once $appDir.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$h = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$token = (string) env('INSTALL_TOKEN', '');
$target = storage_path('app/public');
$link = __DIR__.'/storage';

// ── what is there now ──────────────────────────────────────────────────
$status = function () use ($link, $target) {
    $files = 0;
    $sample = null;
    if (is_dir($target.'/media')) {
        foreach (new FilesystemIterator($target.'/media', FilesystemIterator::SKIP_DOTS) as $f) {
            if ($f->isFile()) {
                $files++;
                $sample ??= 'media/'.$f->getFilename();
            }
        }
    }

    return [
        'is_link' => is_link($link),
        'link_to' => is_link($link) ? @readlink($link) : null,
        'is_dir' => ! is_link($link) && is_dir($link),
        'dir_empty' => ! is_link($link) && is_dir($link) && count(array_diff(scandir($link) ?: [], ['.', '..'])) === 0,
        'works' => is_file($link.'/.gitignore') || ($sample && is_file($link.'/'.$sample)),
        'target_ok' => is_dir($target),
        'files' => $files,
        'sample' => $sample,
        'symlink_fn' => function_exists('symlink') && ! in_array('symlink', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true),
    ];
};
$s = $status();

// ── the fix ────────────────────────────────────────────────────────────
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (strlen($token) < 12) {
        $error = 'Set INSTALL_TOKEN in laravel/.env first (at least 12 characters) — it can be removed again afterwards.';
    } elseif (! hash_equals($token, trim((string) ($_POST['token'] ?? '')))) {
        $error = 'The token does not match INSTALL_TOKEN in .env.';
    } elseif (! $s['target_ok']) {
        $error = 'laravel/storage/app/public does not exist. Upload the laravel folder completely first.';
    } elseif ($s['is_link'] && $s['works']) {
        $message = 'The link is already in place and working.';
    } elseif ($s['is_dir'] && ! $s['dir_empty']) {
        $error = 'public_html/storage is a normal folder with files in it, not a link. Move those files into laravel/storage/app/public, delete the folder, then try again.';
    } else {
        // A broken link, or an empty folder in the way: remove it first.
        if ($s['is_link']) {
            @unlink($link) || @rmdir($link);
        } elseif ($s['is_dir']) {
            @rmdir($link);
        }

        $made = $s['symlink_fn'] && @symlink($target, $link);
        // Some hosts only allow a link written relative to public_html.
        if (! $made && $s['symlink_fn']) {
            $made = @symlink('../laravel/storage/app/public', $link);
        }

        $s = $status();
        if ($made && $s['is_link']) {
            $message = 'Done — public_html/storage now links to laravel/storage/app/public. Uploaded images will show.';
        } else {
            $error = 'This host does not allow symlinks, so the link could not be made. The site still shows uploaded images through its own /storage route — check an image below. For the faster link, ask your host to create a symlink from public_html/storage to laravel/storage/app/public.';
        }
    }
}

$sampleUrl = $s['sample'] ? '/storage/'.$s['sample'] : null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Fix images</title>
<style>
  body { margin: 0; font: 15px/1.6 system-ui, sans-serif; color: #0B2415; background: #F4F9EC; }
  main { max-width: 720px; margin: 40px auto; padding: 32px; background: #fff; border-top: 5px solid #4CB944; border-radius: 16px; box-shadow: 0 14px 40px rgba(15, 107, 50, .1); }
  h1 { margin: 0 0 4px; font-size: 26px; }
  h2 { margin: 26px 0 8px; font-size: 18px; }
  .muted { color: #56675B; margin: 0 0 20px; }
  ul.checks { list-style: none; padding: 0; margin: 0 0 20px; }
  ul.checks li { padding: 7px 0; border-bottom: 1px solid #EEF3E6; }
  .ok { color: #0F6B32; } .bad { color: #B42318; font-weight: 600; } .warn { color: #8A5A00; font-weight: 600; }
  label { display: block; margin: 14px 0 4px; font-weight: 600; }
  input { box-sizing: border-box; width: 100%; padding: 11px 14px; font: inherit; border: 1px solid #E1EBD6; border-radius: 10px; }
  button { margin-top: 18px; padding: 13px 28px; font: 600 15px system-ui; color: #0B2415; background: linear-gradient(90deg, #E4F55E, #4CB944); border: 0; border-radius: 999px; cursor: pointer; }
  .alert { padding: 14px 18px; border-radius: 10px; margin: 0 0 20px; }
  .alert.err { color: #8A2E1C; background: #FCE8E3; } .alert.win { color: #0F6B32; background: #EEF8DF; }
  .sample { display: flex; gap: 16px; align-items: center; padding: 14px; background: #F4F9EC; border-radius: 12px; }
  .sample img { width: 140px; height: 90px; object-fit: contain; background: #fff; border-radius: 8px; border: 1px solid #E1EBD6; }
  code { padding: 2px 6px; background: #EEF8DF; border-radius: 6px; }
</style>
</head>
<body>
<main>
  <h1>Fix uploaded images</h1>
  <p class="muted">Makes <code>public_html/storage</code>, the link through which uploaded images are shown. Use it once, then delete <b>public_html/fix-storage.php</b>.</p>

  <?php if ($error): ?><p class="alert err"><?= $h($error) ?></p><?php endif; ?>
  <?php if ($message): ?><p class="alert win"><?= $h($message) ?></p><?php endif; ?>

  <h2>Now</h2>
  <ul class="checks">
    <?php if ($s['is_link']): ?>
      <li class="<?= $s['works'] ? 'ok' : 'bad' ?>"><?= $s['works'] ? '✔' : '✖' ?> public_html/storage is a link<?= $s['link_to'] ? ' → '.$h($s['link_to']) : '' ?><?= $s['works'] ? '' : ' — but it points to the wrong place' ?></li>
    <?php elseif ($s['is_dir']): ?>
      <li class="bad">✖ public_html/storage is a normal folder, not a link<?= $s['dir_empty'] ? ' (empty — the button below replaces it)' : ' with files in it' ?></li>
    <?php else: ?>
      <li class="bad">✖ public_html/storage does not exist</li>
    <?php endif; ?>
    <li class="<?= $s['target_ok'] ? 'ok' : 'bad' ?>"><?= $s['target_ok'] ? '✔' : '✖' ?> laravel/storage/app/public exists</li>
    <li class="<?= $s['files'] ? 'ok' : 'warn' ?>"><?= $s['files'] ? '✔ '.$s['files'].' uploaded file(s) in laravel/storage/app/public/media' : '! No uploaded files on this server yet — upload your storage/app/public/media folder, or upload the images again in the admin' ?></li>
    <li class="<?= $s['symlink_fn'] ? 'ok' : 'warn' ?>"><?= $s['symlink_fn'] ? '✔ This host allows symlink()' : '! This host has switched symlink() off — the site will use its own /storage route instead' ?></li>
    <li class="<?= strlen($token) >= 12 ? 'ok' : 'bad' ?>"><?= strlen($token) >= 12 ? '✔' : '✖' ?> INSTALL_TOKEN is set in .env</li>
  </ul>

  <?php if ($sampleUrl): ?>
    <h2>Check an image</h2>
    <div class="sample">
      <img src="<?= $h($sampleUrl) ?>" alt="" onerror="this.nextElementSibling.querySelector('b').textContent='✖ Not showing'">
      <span><b>✔ Showing</b><br><small><?= $h($sampleUrl) ?></small></span>
    </div>
  <?php endif; ?>

  <?php if (! ($s['is_link'] && $s['works'])): ?>
    <form method="post" autocomplete="off">
      <label for="token">Token (INSTALL_TOKEN from laravel/.env)</label>
      <input id="token" name="token" type="password" required>
      <button type="submit">Make the storage link</button>
    </form>
  <?php else: ?>
    <p class="alert win"><b>All good.</b> Now delete <b>public_html/fix-storage.php</b> (and INSTALL_TOKEN from .env if you set it only for this).</p>
  <?php endif; ?>
</main>
</body>
</html>
