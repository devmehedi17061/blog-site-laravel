<?php

use App\Models\Media;
use App\Models\User;
use App\Support\ImageVariants;
use App\Support\LocalMediaUrls;
use App\Support\Sessions;
use App\Support\SiteSettings;
use App\Support\Trash;
use App\Support\TwoFactor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Ways back in when Security settings lock the owner out. They need shell
| access to the server, which is exactly the point: nobody can use them
| from the web.
*/
Artisan::command('security:unlock', function () {
    $settings = app(SiteSettings::class);
    $settings->save('security', ['ip_rules_enabled' => false, 'force_https' => false, 'hsts' => false]);

    $this->info('Admin IP rules, forced HTTPS and HSTS are switched off. The rules themselves are kept.');
    $this->line('Note: a browser that already saw HSTS keeps insisting on HTTPS until its max-age passes.');
})->purpose('Switch off admin IP rules and forced HTTPS after a lock-out');

/*
| Media saved as "http://localhost:8080/storage/…" only loads while the site
| is reached through exactly that host. This rewrites the site's own media
| addresses to "/storage/…" everywhere content keeps them.
*/
Artisan::command('media:local-urls {--dry-run : Only report what would change}', function () {
    $changed = LocalMediaUrls::fix((bool) $this->option('dry-run'));

    if ($changed === []) {
        $this->info('Nothing to change: every local media address is already a path.');

        return 0;
    }

    foreach ($changed as $where => $n) {
        $this->line(($this->option('dry-run') ? 'Would update ' : 'Updated ')."{$n} {$where}");
    }

    return 0;
})->purpose('Make this site\'s own media addresses host-independent (/storage/…)');

/*
| Makes the smaller WebP copies for images uploaded before optimisation
| existed (new uploads get them straight away). Safe to run again.
*/
Artisan::command('media:optimise {--force : Remake copies that already exist}', function () {
    if (! ImageVariants::supported()) {
        $this->error('PHP\'s GD extension with WebP support is needed. Nothing was changed.');

        return 1;
    }

    $query = Media::query()->where('mime_type', 'like', 'image/%');
    if (! $this->option('force')) {
        $query->whereNull('variants');
    }

    $done = 0;
    $skipped = 0;

    $query->orderBy('id')->each(function (Media $media) use (&$done, &$skipped) {
        ImageVariants::generate($media) ? $done++ : $skipped++;
    });

    $this->info("Optimised {$done} image(s)".($skipped ? ", skipped {$skipped} (not a JPEG, PNG or WebP GD can read, or too large)." : '.'));

    return 0;
})->purpose('Make WebP copies of existing images for faster pages');

Artisan::command('security:reset-2fa {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("No account uses {$email}.");

        return 1;
    }

    TwoFactor::disable($user);
    Sessions::endAllFor($user);
    RateLimiter::clear('login:'.Str::transliterate(Str::lower($email)).'|127.0.0.1');

    $this->info("Two-factor is off for {$user->name}. They can sign in with their password and set it up again.");

    return 0;
})->purpose('Turn off two-factor for one account, e.g. after a lost phone with no recovery codes');

/*
| Deletes for good what has waited in the Trash longer than Trash::DAYS.
| The Trash screen does this too whenever it is opened, for hosts where
| nothing runs "schedule:run".
*/
Artisan::command('trash:purge', function () {
    $count = Trash::purge();
    $this->info($count ? "Deleted {$count} item(s) that had been in the trash for over ".Trash::DAYS.' days.' : 'Nothing in the trash is old enough to delete.');

    return 0;
})->purpose('Empty trash items older than '.Trash::DAYS.' days');

Schedule::command('trash:purge')->daily();
