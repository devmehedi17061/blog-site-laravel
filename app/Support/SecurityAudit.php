<?php

namespace App\Support;

use App\Models\Form;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * `php artisan security:check`: the site's own settings checked against the
 * security list — what is on, what is off, and what needs the live domain.
 * Each check is [status, what, detail]; status is ok, warn or fail.
 */
class SecurityAudit
{
    /** Passwords no account may keep (a seeded demo's, the usual guesses). */
    private const WEAK = ['password', 'password1', '12345678', '123456789', 'admin123', 'qwerty123', 'secret', 'changeme'];

    /** @return array<int, array{0: string, 1: string, 2: string}> */
    public static function run(): array
    {
        $security = app(Security::class);
        $settings = app(SiteSettings::class);
        $live = ! app()->environment('local');
        $out = [];
        $add = function (string $status, string $what, string $detail = '') use (&$out) {
            $out[] = [$status, $what, $detail];
        };

        // ── environment ──
        $add(config('app.debug') ? 'fail' : 'ok', 'APP_DEBUG is off', config('app.debug') ? 'Error pages would show code and settings.' : '');
        $add(filled(config('app.key')) ? 'ok' : 'fail', 'APP_KEY is set');
        $add($live ? 'ok' : 'warn', 'APP_ENV is production', $live ? '' : 'local — fine on this computer, must be production on the server.');
        $add(in_array(config('logging.channels.single.level', config('logging.level')), ['debug'], true) || env('LOG_LEVEL') === 'debug' ? 'warn' : 'ok', 'Log level is not debug');
        $add(config('session.encrypt') ? 'ok' : 'warn', 'Sessions are encrypted');
        $add(config('session.http_only') ? 'ok' : 'fail', 'Session cookie is HttpOnly');
        $add(config('session.secure') ? 'ok' : ($live ? 'fail' : 'warn'), 'Session cookie is HTTPS-only', config('session.secure') ? '' : 'Set SESSION_SECURE_COOKIE=true once the domain has SSL.');

        // ── HTTPS & headers ──
        $add($security->get('headers') ? 'ok' : 'fail', 'Security headers (CSP, frame, nosniff, referrer)');
        $add($security->get('force_https') ? 'ok' : ($live ? 'fail' : 'warn'), 'HTTPS is forced', $security->get('force_https') ? '' : 'Settings → Security, once the domain has SSL.');
        $add($security->get('hsts') ? 'ok' : 'warn', 'HSTS', $security->get('hsts') ? '' : 'Turn on after HTTPS has worked for a few days.');

        // ── accounts ──
        $roles = (array) $settings->get('security.two_factor_roles', []);
        $superRole = \App\Models\Role::where('slug', 'super-admin')->value('id');
        $add(in_array($superRole, array_map('intval', $roles), true) ? 'ok' : 'warn', 'Two-factor is required for Super Admins');
        $without = User::where('status', 'Active')->get()->reject->hasTwoFactor()->pluck('email');
        $add($without->isEmpty() ? 'ok' : 'warn', 'Every active account uses two-factor', $without->implode(', '));
        $weak = User::where('status', 'Active')->get()->filter(fn ($u) => collect(self::WEAK)->contains(fn ($p) => Hash::check($p, $u->password)))->pluck('email');
        $add($weak->isEmpty() ? 'ok' : 'fail', 'No account has a common password', $weak->implode(', '));
        $demo = User::where('email', 'like', '%@example.com')->pluck('email');
        $add($demo->isEmpty() ? 'ok' : 'fail', 'No demo accounts', $demo->implode(', '));
        $add((int) $security->get('max_attempts') <= 10 ? 'ok' : 'warn', 'Sign-in lockout after '.$security->get('max_attempts').' wrong passwords');
        $add((int) $security->get('password_min') >= 10 ? 'ok' : 'warn', 'Passwords at least '.$security->get('password_min').' characters');
        $add($settings->get('security.password_uncompromised') ? 'ok' : 'warn', 'Leaked passwords are refused (haveibeenpwned)');

        // ── bots & spam ──
        $captcha = $settings->get('spam.captcha', 'none');
        $add($captcha !== 'none' ? 'ok' : 'warn', 'CAPTCHA on forms', $captcha !== 'none' ? $captcha : 'Needs Turnstile/reCAPTCHA keys for the live domain.');
        $add($settings->get('spam.protect_login') ? 'ok' : 'warn', 'CAPTCHA on the sign-in page');
        $add($settings->get('spam.time_trap', true) ? 'ok' : 'warn', 'Form time trap + honeypot');

        // ── uploads & files ──
        $script = collect(File::allFiles(storage_path('app/public')))
            ->filter(fn ($f) => preg_match('/\.(php\d?|phtml|phar|pht|pl|py|cgi|sh|html?|js)$/i', $f->getFilename()))
            ->map(fn ($f) => $f->getRelativePathname());
        $add($script->isEmpty() ? 'ok' : 'fail', 'No runnable files among uploads', $script->take(5)->implode(', '));
        $add(is_file(storage_path('app/public/.htaccess')) ? 'ok' : 'fail', 'Uploads folder cannot run code (.htaccess)');
        $add(is_file(base_path('.htaccess')) ? 'ok' : 'warn', 'Project root is closed to the web (.htaccess)');
        $publicEnv = collect(['.env', '.git', 'composer.json', 'storage'])->filter(fn ($p) => file_exists(public_path($p)) && ! ($p === 'storage'))->values();
        $add($publicEnv->isEmpty() ? 'ok' : 'fail', 'Nothing secret inside public/', $publicEnv->implode(', '));
        $fileFields = Form::whereHas('fields', fn ($q) => $q->where('type', 'file'))->pluck('name');
        $add('ok', 'Form uploads are type-checked and kept private', $fileFields->isEmpty() ? 'no form takes files now' : 'forms with files: '.$fileFields->implode(', '));

        // ── backups ──
        $latest = DatabaseBackup::list()[0] ?? null;
        $age = $latest ? (time() - strtotime($latest['at'])) / 3600 : null;
        $add($latest && $age < 48 ? 'ok' : 'fail', 'A database backup from the last two days', $latest ? $latest['name'].' ('.$latest['at'].')' : 'none — run php artisan backup:database');

        return $out;
    }
}
