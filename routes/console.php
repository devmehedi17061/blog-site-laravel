<?php

use App\Actions\Menu\SaveMenu;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Support\ImageVariants;
use App\Support\LocalMediaUrls;
use App\Support\Sessions;
use App\Support\SiteSettings;
use App\Support\Trash;
use App\Support\TwoFactor;
use Database\Seeders\FormSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SectionLibrarySeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
| Sets up a fresh copy of the site: the tables, the section library, the
| header and footer designs, the contact form, a Home/Courses/Blog menu
| (with "My courses" and "Sign in" for students) and a first
| admin of your choosing. --demo adds the sample pages and posts and the four
| demo accounts — and the sample courses, an instructor and two students —
| which all use the password "password", so it refuses to run
| on a live site. Safe to run again: nothing already there is replaced.
*/
Artisan::command('cms:install
    {--demo : Add the sample content and demo accounts (local use only)}
    {--site= : The site name}
    {--name= : The admin\'s name}
    {--email= : The admin\'s email}
    {--password= : The admin\'s password (asked for when left out)}', function () {
    if ($this->option('demo') && app()->isProduction()) {
        $this->error('The demo accounts all use the password "password". Leave out --demo on a live site.');

        return 1;
    }

    try {
        DB::connection()->getPdo();
    } catch (Throwable $e) {
        $this->error('Cannot reach the database: '.$e->getMessage());
        $this->line('Create an empty database, then fill in DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env.');

        return 1;
    }

    if (blank(config('app.key'))) {
        $this->call('key:generate', ['--force' => true]);
    }

    $this->call('migrate', ['--force' => true]);

    if ($this->option('demo')) {
        $this->call('db:seed', ['--force' => true]);
    } else {
        foreach ([RolePermissionSeeder::class, SectionLibrarySeeder::class, FormSeeder::class] as $seeder) {
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
        }
        foreach (MenuItem::LOCATIONS as $location) {
            if (MenuItem::where('location', $location)->doesntExist()) {
                app(SaveMenu::class)->handle($location, [
                    ['label' => 'Home', 'url' => '/', 'depth' => 0],
                    ['label' => 'Courses', 'url' => '/courses', 'depth' => 0],
                    ['label' => 'Blog', 'url' => '/blog', 'depth' => 0],
                    ['label' => 'My courses', 'url' => '/my-courses', 'visibility' => 'Authenticated', 'depth' => 0],
                    ['label' => 'Sign in', 'url' => '/login', 'visibility' => 'Guests', 'depth' => 0],
                ]);
            }
        }
    }

    $site = $this->option('site') ?? ($this->option('demo') ? null : $this->ask('Site name', config('app.name')));
    if (filled($site)) {
        app(SiteSettings::class)->save('site', ['name' => $site]);
    }

    $email = $this->option('email') ?? $this->ask('Admin email (blank to skip)');
    if (blank($email)) {
        $this->warn('No admin account was made.'.($this->option('demo') ? ' Sign in as arif@example.com / password.' : ' Run this again with --email to make one.'));
    } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error("\"{$email}\" is not an email address.");

        return 1;
    } else {
        $password = $this->option('password') ?? $this->secret('Password (blank = make a strong one)');
        $made = blank($password);
        $password = $made ? Str::password(16) : $password;

        if (mb_strlen($password) < 8) {
            $this->error('Use at least 8 characters.');

            return 1;
        }

        User::updateOrCreate(['email' => Str::lower($email)], [
            'name' => $this->option('name') ?? (($this->option('email') ? null : $this->ask('Admin name', 'Admin')) ?? 'Admin'),
            'password' => $password,
            'role_id' => Role::where('slug', 'super-admin')->value('id'),
            'status' => 'Active',
            'email_verified_at' => now(),
        ]);
        $this->info("Super Admin ready: {$email}".($made ? "  password: {$password}  (write it down, it is shown only once)" : ''));
    }

    if (! file_exists(public_path('storage'))) {
        $this->call('storage:link');
    }

    $this->newLine();
    $this->info('Done. Sign in at '.rtrim(config('app.url'), '/').'/admin');
    if (! file_exists(public_path('build/manifest.json'))) {
        $this->warn('The CSS and JavaScript are not built yet: run "npm install" and "npm run build".');
    }

    return 0;
})->purpose('Set up a fresh copy of the site, with a first admin');

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

/*
| Scheduled pages and posts are already public once their date comes; this
| only updates their status so the admin lists say "Published".
*/
Schedule::call(fn () => Page::publishDue() + Post::publishDue())->everyMinute()->name('publish-due');
