<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Everything the Settings screen edits, in one place.
 *
 * Every key has a default here, so a site that has never opened the screen —
 * or a key added in a later release — still gets a sensible value. Stored
 * values live in the `settings` table, read once and cached until the next
 * save.
 *
 * Secret values (passwords, API secrets) are encrypted with the app key
 * before they reach the database and are never sent back to the browser:
 * the screen is only told whether one is set.
 */
class SiteSettings
{
    private const CACHE_KEY = 'site-settings.v1';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /** Every key, with its default. Grouped by the tab that edits it. */
    public static function defaults(): array
    {
        return [
            // ── Site: brand ──────────────────────────────────────────────
            // Blank name falls back to APP_NAME, so a fresh install is named.
            'site.name' => '',
            'site.tagline' => '',
            'site.logo' => '',
            'site.logo_dark' => '',
            'site.show_name' => true,
            'site.favicon' => '',

            // ── Site: contact & social ───────────────────────────────────
            'site.contact_email' => '',
            'site.phone' => '',
            'site.address' => '',
            'site.social_facebook' => '',
            'site.social_x' => '',
            'site.social_linkedin' => '',
            'site.social_instagram' => '',
            'site.social_youtube' => '',

            // ── Site: SEO defaults ───────────────────────────────────────
            'site.title_format' => '{title} — {site}',
            'site.default_description' => '',
            'site.share_image' => '',
            'site.google_verification' => '',
            'site.bing_verification' => '',
            'site.noindex' => false,
            // Served at /robots.txt, with the Sitemap line added below it.
            'site.robots_txt' => "User-agent: *\nDisallow: /admin\n",

            // ── Site: locale & maintenance ───────────────────────────────
            // The public site's language: <html lang>, text direction,
            // og:locale and the language dates are written in.
            'site.language' => 'en',
            'site.timezone' => 'UTC',
            'site.date_format' => 'd M Y',
            'site.maintenance' => false,
            'site.maintenance_message' => 'We are making some improvements and will be back shortly.',

            // ── Design: colours, fonts and widths (App\Support\Design) ────
            'design.primary' => '#ec3013',
            'design.text' => '#1c1a19',
            'design.background' => '#f2f1f1',
            'design.body_font' => 'Archivo',
            'design.heading_font' => 'Archivo',
            'design.site_width' => 1100,
            'design.content_width' => 720,
            'design.text_size' => 16,
            'design.radius' => 0,

            // ── Spam protection ──────────────────────────────────────────
            'spam.captcha' => 'none',
            'spam.turnstile_site_key' => '',
            'spam.turnstile_secret' => '',
            'spam.recaptcha_site_key' => '',
            'spam.recaptcha_secret' => '',
            // Out of 100: reCAPTCHA v3 scores 0.0 (bot) to 1.0 (person).
            'spam.recaptcha_min_score' => 50,
            'spam.protect_login' => false,

            'spam.time_trap' => true,
            'spam.min_seconds' => 3,
            'spam.limit_links' => true,
            'spam.max_links' => 2,
            'spam.blocked_words' => '',
            'spam.blocked_emails' => '',
            'spam.rate_limit' => 12,

            // ── Security ─────────────────────────────────────────────────
            // Sign-in: failed tries per email + IP before a pause.
            'security.max_attempts' => 5,
            'security.lockout_minutes' => 15,

            // Password rules, applied whenever a password is set.
            'security.password_min' => 10,
            'security.password_mixed_case' => true,
            'security.password_numbers' => true,
            'security.password_symbols' => false,
            'security.password_uncompromised' => false,
            // 0 = never. Current guidance (NIST) is not to force rotation.
            'security.password_expiry_days' => 0,

            // Role ids whose members must use two-factor authentication.
            'security.two_factor_roles' => [],

            // Minutes of inactivity before the panel signs someone out.
            'security.idle_minutes' => 120,
            'security.single_session' => false,

            // Admin-only IP rules (the ip_rules table). Off until switched on.
            'security.ip_rules_enabled' => false,

            // Never enforced on localhost — see SecurityHeaders.
            'security.force_https' => false,
            'security.hsts' => false,
            'security.headers' => true,

            // ── Front end ────────────────────────────────────────────────
            'frontend.disable_right_click' => false,
            'frontend.block_devtools_keys' => false,
            'frontend.disable_selection' => false,
            'frontend.disable_image_drag' => false,
            // The people who run the site can still inspect and copy it.
            'frontend.exempt_signed_in' => true,

            'frontend.custom_css' => '',
            // Raw HTML on every public page: Super Admin only.
            'frontend.head_code' => '',
            'frontend.body_code' => '',
            // External CSS and JS files for every public page (CustomCode::externals).
            'frontend.external_assets' => [],

            // ── Email ────────────────────────────────────────────────────
            // "log" writes mail to storage/logs instead of sending it — the
            // honest state of a site nobody has set up yet.
            'mail.provider' => 'log',
            'mail.from_address' => '',
            'mail.from_name' => '',
            'mail.reply_to' => '',

            'mail.gmail_address' => '',
            'mail.gmail_app_password' => '',

            'mail.zoho_address' => '',
            'mail.zoho_password' => '',
            'mail.zoho_region' => 'com',
            'mail.zoho_account' => 'personal',
            'mail.zoho_security' => 'ssl',

            'mail.webmail_host' => '',
            'mail.webmail_port' => 465,
            'mail.webmail_username' => '',
            'mail.webmail_password' => '',

            'mail.smtp_host' => '',
            'mail.smtp_port' => 587,
            'mail.smtp_security' => 'tls',
            'mail.smtp_username' => '',
            'mail.smtp_password' => '',

            // ── Updates (App\Support\Updater) ─────────────────────────────
            // none: only tell · security: install security releases by itself
            // · all: install every release by itself.
            'updates.auto' => 'none',
            // GitHub token for a private repository (read-only is enough).
            'updates.token' => '',
        ];
    }

    /** Encrypted at rest, write-only from the browser. */
    public const SECRETS = [
        'mail.gmail_app_password',
        'mail.zoho_password',
        'mail.webmail_password',
        'mail.smtp_password',
        'spam.turnstile_secret',
        'spam.recaptcha_secret',
        'updates.token',
    ];

    // ── reading ────────────────────────────────────────────────────────────

    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $stored = [];

        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
                ->whereIn('key', array_keys(self::defaults()))
                ->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
                ->all());
        } catch (Throwable) {
            // No table yet (before the first migration), or the database is
            // down: the defaults stand in. Settings must never take the
            // site down with them.
        }

        return $this->values = array_merge(self::defaults(), $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? $default;

        if (in_array($key, self::SECRETS, true) && is_string($value) && $value !== '') {
            try {
                return Crypt::decryptString($value);
            } catch (Throwable) {
                // Encrypted under a different APP_KEY: treat as unset.
                return '';
            }
        }

        return $value;
    }

    /**
     * One tab's values for the browser, prefix stripped, secrets replaced by
     * whether they are set: `smtp_password_set: true`, never the password.
     */
    public function forScreen(string $group): array
    {
        $out = [];

        foreach ($this->all() as $key => $value) {
            if (! str_starts_with($key, $group.'.')) {
                continue;
            }

            $short = substr($key, strlen($group) + 1);

            if (in_array($key, self::SECRETS, true)) {
                $out[$short.'_set'] = filled($value);

                continue;
            }

            $out[$short] = $value;
        }

        return $out;
    }

    // ── writing ────────────────────────────────────────────────────────────

    /**
     * Saves one tab. A blank secret means "keep what is stored" — the
     * browser never had it, so it cannot send it back. To remove one, the
     * screen sends `<name>_clear: true`.
     */
    public function save(string $group, array $input): void
    {
        foreach (self::defaults() as $key => $default) {
            if (! str_starts_with($key, $group.'.')) {
                continue;
            }

            $short = substr($key, strlen($group) + 1);

            if (in_array($key, self::SECRETS, true)) {
                if (! empty($input[$short.'_clear'])) {
                    Setting::put($key, '', $group);
                } elseif (filled($input[$short] ?? null)) {
                    Setting::put($key, Crypt::encryptString((string) $input[$short]), $group);
                }

                continue;
            }

            if (! array_key_exists($short, $input)) {
                continue;
            }

            $value = $input[$short];
            // Keep the type the default declares, so "587" stays an integer.
            $value = match (true) {
                is_int($default) => (int) $value,
                is_bool($default) => (bool) $value,
                is_array($default) => array_values((array) $value),
                default => (string) ($value ?? ''),
            };

            Setting::put($key, $value, $group);
        }

        $this->forget();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }

    // ── site ───────────────────────────────────────────────────────────────

    /** Languages the public site can be marked as. Right-to-left ones flip the page. */
    public const LANGUAGES = [
        'en' => ['label' => 'English', 'og' => 'en_US', 'rtl' => false],
        'bn' => ['label' => 'বাংলা (Bengali)', 'og' => 'bn_BD', 'rtl' => false],
        'hi' => ['label' => 'हिन्दी (Hindi)', 'og' => 'hi_IN', 'rtl' => false],
        'ur' => ['label' => 'اردو (Urdu)', 'og' => 'ur_PK', 'rtl' => true],
        'ar' => ['label' => 'العربية (Arabic)', 'og' => 'ar_AR', 'rtl' => true],
        'es' => ['label' => 'Español (Spanish)', 'og' => 'es_ES', 'rtl' => false],
        'fr' => ['label' => 'Français (French)', 'og' => 'fr_FR', 'rtl' => false],
        'de' => ['label' => 'Deutsch (German)', 'og' => 'de_DE', 'rtl' => false],
        'pt' => ['label' => 'Português (Portuguese)', 'og' => 'pt_PT', 'rtl' => false],
        'id' => ['label' => 'Bahasa Indonesia', 'og' => 'id_ID', 'rtl' => false],
        'tr' => ['label' => 'Türkçe (Turkish)', 'og' => 'tr_TR', 'rtl' => false],
    ];

    /** Offered on the Site tab; the value is a PHP date() format. */
    public const DATE_FORMATS = ['d M Y', 'j F Y', 'F j, Y', 'd/m/Y', 'm/d/Y', 'Y-m-d'];

    public const SOCIAL_NETWORKS = [
        'facebook' => 'Facebook',
        'x' => 'X (Twitter)',
        'linkedin' => 'LinkedIn',
        'instagram' => 'Instagram',
        'youtube' => 'YouTube',
    ];

    /** The site's name: the Site tab's, or APP_NAME until someone sets it. */
    public function siteName(): string
    {
        return trim((string) $this->get('site.name')) ?: (string) config('app.name');
    }

    /**
     * Applied on every request, so the rest of the code — and anything that
     * reads config('app.name') — sees the name set on the Site tab.
     */
    public function applySiteConfig(): void
    {
        if ($name = trim((string) $this->get('site.name'))) {
            config(['app.name' => $name]);
        }
    }

    // ── email ──────────────────────────────────────────────────────────────

    public const MAIL_PROVIDERS = ['log', 'gmail', 'zoho', 'webmail', 'smtp'];

    /** Zoho's data centres: the account's region decides its mail server. */
    public const ZOHO_REGIONS = [
        'com' => 'United States (zoho.com)',
        'eu' => 'Europe (zoho.eu)',
        'in' => 'India (zoho.in)',
        'com.au' => 'Australia (zoho.com.au)',
        'jp' => 'Japan (zoho.jp)',
        'ca' => 'Canada (zohocloud.ca)',
        'sa' => 'Saudi Arabia (zoho.sa)',
    ];

    /**
     * The SMTP connection a provider's fields describe. Takes the values to
     * use rather than reading the stored ones, so "Send test email" can try
     * what is on screen before it is saved.
     *
     * @return array<string, mixed>|null null for the "log" provider
     */
    public static function smtpConfig(array $v): ?array
    {
        $provider = $v['provider'] ?? 'log';

        [$host, $port, $security, $username, $password] = match ($provider) {
            'gmail' => ['smtp.gmail.com', 587, 'tls', $v['gmail_address'] ?? '',
                // Google shows app passwords in four groups of four.
                str_replace(' ', '', (string) ($v['gmail_app_password'] ?? ''))],
            'zoho' => [
                self::zohoHost($v['zoho_region'] ?? 'com', $v['zoho_account'] ?? 'personal'),
                ($v['zoho_security'] ?? 'ssl') === 'tls' ? 587 : 465,
                $v['zoho_security'] ?? 'ssl',
                $v['zoho_address'] ?? '',
                $v['zoho_password'] ?? '',
            ],
            'webmail' => [
                $v['webmail_host'] ?? '',
                (int) ($v['webmail_port'] ?? 465),
                (int) ($v['webmail_port'] ?? 465) === 465 ? 'ssl' : 'tls',
                $v['webmail_username'] ?? '',
                $v['webmail_password'] ?? '',
            ],
            'smtp' => [
                $v['smtp_host'] ?? '',
                (int) ($v['smtp_port'] ?? 587),
                $v['smtp_security'] ?? 'tls',
                $v['smtp_username'] ?? '',
                $v['smtp_password'] ?? '',
            ],
            default => [null, null, null, null, null],
        };

        if ($host === null) {
            return null;
        }

        return [
            'transport' => 'smtp',
            // smtps = TLS from the first byte (465); smtp upgrades with
            // STARTTLS when the server offers it (587).
            'scheme' => $security === 'ssl' ? 'smtps' : 'smtp',
            'host' => $host,
            'port' => $port,
            'username' => $username !== '' ? $username : null,
            'password' => $password !== '' ? $password : null,
            // "None" really means none: do not upgrade even if offered.
            'auto_tls' => $security !== 'none',
            'timeout' => 20,
        ];
    }

    /**
     * The address mail is sent from. Left blank, it is the account's own
     * address — which Gmail and Zoho require anyway: they reject or rewrite
     * a From that is not the account or one of its verified aliases.
     */
    public static function senderAddress(array $v): string
    {
        $account = match ($v['provider'] ?? 'log') {
            'gmail' => $v['gmail_address'] ?? '',
            'zoho' => $v['zoho_address'] ?? '',
            'webmail' => $v['webmail_username'] ?? '',
            'smtp' => $v['smtp_username'] ?? '',
            default => '',
        };

        foreach ([$v['from_address'] ?? '', $account] as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return '';
    }

    public static function zohoHost(string $region, string $account): string
    {
        $prefix = $account === 'organization' ? 'smtppro' : 'smtp';

        return match ($region) {
            'ca' => "{$prefix}.zohocloud.ca",
            default => "{$prefix}.zoho.{$region}",
        };
    }

    /** The stored Email tab, secrets decrypted — what the site sends with. */
    public function mailValues(): array
    {
        $values = [];

        foreach (array_keys(self::defaults()) as $key) {
            if (str_starts_with($key, 'mail.')) {
                $values[substr($key, 5)] = $this->get($key);
            }
        }

        return $values;
    }

    /**
     * Points Laravel's mailer at whatever the Email tab says. Called when
     * the mail manager is first resolved, so nothing is read on a request
     * that never sends mail.
     */
    public function applyMailConfig(): void
    {
        $values = $this->mailValues();
        $smtp = self::smtpConfig($values);

        if ($smtp) {
            config(['mail.default' => 'smtp', 'mail.mailers.smtp' => $smtp]);
        } elseif (($values['provider'] ?? 'log') === 'log') {
            config(['mail.default' => 'log']);
        }

        if ($from = self::senderAddress($values)) {
            config(['mail.from.address' => $from]);
        }

        if (filled($values['from_name'] ?? null)) {
            config(['mail.from.name' => $values['from_name']]);
        }

        if (filled($values['reply_to'] ?? null)) {
            config(['mail.reply_to' => ['address' => $values['reply_to'], 'name' => $values['from_name'] ?: null]]);
        }
    }
}
