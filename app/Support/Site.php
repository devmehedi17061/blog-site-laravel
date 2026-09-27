<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * What the public views ask of the Site tab, shared to them as `$site`.
 *
 *   <title>{{ $site->title($post->title) }}</title>
 *   {{ $site->date($post->published_at) }}
 *
 * Nothing here queries until a view asks, and the settings behind it are
 * read once per request.
 */
class Site
{
    public function __construct(private readonly SiteSettings $settings) {}

    public function name(): string
    {
        return $this->settings->siteName();
    }

    /** Settings that hold a media-library image. */
    private const IMAGES = ['logo', 'logo_dark', 'favicon', 'share_image'];

    public function get(string $key): mixed
    {
        $value = $this->settings->get('site.'.$key);

        return in_array($key, self::IMAGES, true) ? self::localPath($value) : $value;
    }

    /**
     * A library image saved as a full address on this site's own APP_URL
     * ("http://localhost:8080/storage/…") only works while the site is
     * reached through exactly that host. As a path it works on any host the
     * site is served from. Other sites' addresses are left alone.
     */
    public static function localPath(mixed $url): string
    {
        $url = (string) $url;
        $base = rtrim((string) config('app.url'), '/');

        if ($base !== '' && str_starts_with($url, $base.'/')) {
            return substr($url, strlen($base));
        }

        return $url;
    }

    /** "{title} — {site}" with the blanks filled; the site name alone for none. */
    public function title(?string $title = null): string
    {
        $title = trim((string) $title);

        if ($title === '') {
            return $this->homeTitle();
        }

        $format = (string) ($this->get('title_format') ?: '{title} — {site}');

        return strtr($format, ['{title}' => $title, '{site}' => $this->name()]);
    }

    /** The front door reads "Site — Tagline", the way browsers bookmark it. */
    public function homeTitle(): string
    {
        $tagline = trim((string) $this->get('tagline'));

        return $tagline !== '' ? $this->name().' — '.$tagline : $this->name();
    }

    public function description(?string $specific = null): string
    {
        return trim((string) $specific) ?: (string) $this->get('default_description');
    }

    /**
     * A date as the Site tab says to show it, in the site's timezone. Times
     * are stored in UTC; only the display moves.
     */
    public function date(?DateTimeInterface $date): string
    {
        if (! $date) {
            return '';
        }

        return Carbon::instance($date)
            ->copy()
            ->setTimezone($this->timezone())
            ->translatedFormat((string) ($this->get('date_format') ?: 'd M Y'));
    }

    public function language(): string
    {
        $language = (string) $this->get('language');

        return array_key_exists($language, SiteSettings::LANGUAGES) ? $language : 'en';
    }

    public function isRtl(): bool
    {
        return SiteSettings::LANGUAGES[$this->language()]['rtl'];
    }

    public function ogLocale(): string
    {
        return SiteSettings::LANGUAGES[$this->language()]['og'];
    }

    public function timezone(): string
    {
        $zone = (string) $this->get('timezone');

        return in_array($zone, timezone_identifiers_list(), true) ? $zone : 'UTC';
    }

    /** @return array<int, array{key: string, label: string, url: string}> */
    public function socialLinks(): array
    {
        $links = [];

        foreach (SiteSettings::SOCIAL_NETWORKS as $key => $label) {
            if ($url = trim((string) $this->get('social_'.$key))) {
                $links[] = ['key' => $key, 'label' => $label, 'url' => $url];
            }
        }

        return $links;
    }

    public function hasContact(): bool
    {
        return filled($this->get('contact_email')) || filled($this->get('phone')) || filled($this->get('address'));
    }

    public function inMaintenance(): bool
    {
        return (bool) $this->get('maintenance');
    }

    public function hiddenFromSearch(): bool
    {
        return (bool) $this->get('noindex');
    }

    // ── front end ──────────────────────────────────────────────────────────

    public function frontend(string $key): mixed
    {
        return $this->settings->get('frontend.'.$key);
    }

    /**
     * The content protections that apply to this visitor: none for someone
     * signed in to the panel, unless the Front end tab says otherwise.
     *
     * @return array<int, string>
     */
    public function protections(): array
    {
        if ($this->frontend('exempt_signed_in') && auth()->user()?->isActive()) {
            return [];
        }

        return array_values(array_filter(
            ['disable_right_click', 'block_devtools_keys', 'disable_selection', 'disable_image_drag'],
            fn (string $key) => (bool) $this->frontend($key)
        ));
    }

    /** Settings → Front end → External files, each checked (CustomCode::externals). */
    public function externals(): array
    {
        return CustomCode::externals($this->frontend('external_assets'));
    }

    /** Custom CSS, made safe to sit inside a <style> element. */
    public function customCss(): string
    {
        // "</style>" typed into the box would otherwise end the element early
        // and let the rest run as HTML.
        return str_ireplace('</style', '<\/style', (string) $this->frontend('custom_css'));
    }

    /** Three letters for the mark shown when there is no logo. */
    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->name(), 0, 3));
    }
}
