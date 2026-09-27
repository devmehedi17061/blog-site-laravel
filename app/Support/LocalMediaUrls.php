<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Post;
use App\Models\Section;
use App\Models\SectionTemplate;
use App\Models\Setting;

/**
 * Turns this site's own media addresses saved with a host
 * ("http://localhost:8080/storage/media/x.png") into paths
 * ("/storage/media/x.png"), in every place content keeps them.
 *
 * Only local development hosts and APP_URL are touched; an image on any
 * other site is left exactly as it is.
 */
class LocalMediaUrls
{
    /** @return array<string, int> how many records changed, by where */
    public static function fix(bool $dryRun = false): array
    {
        $changed = [];

        $count = function (string $where) use (&$changed) {
            $changed[$where] = ($changed[$where] ?? 0) + 1;
        };

        foreach (Post::query()->get(['id', 'body', 'og_image']) as $post) {
            $body = self::replace((string) $post->body);
            $image = self::replace((string) $post->og_image);
            if ($body !== (string) $post->body || $image !== (string) $post->og_image) {
                $count('posts');
                if (! $dryRun) {
                    $post->forceFill(['body' => $post->body === null ? null : $body, 'og_image' => $post->og_image === null ? null : $image])->saveQuietly();
                }
            }
        }

        foreach (Page::query()->get(['id', 'og_image']) as $page) {
            $image = self::replace((string) $page->og_image);
            if ($page->og_image !== null && $image !== $page->og_image) {
                $count('pages');
                $dryRun || $page->forceFill(['og_image' => $image])->saveQuietly();
            }
        }

        foreach (Section::query()->get() as $section) {
            $values = self::walk($section->values ?? []);
            $html = self::replace((string) $section->html);
            if ($values !== ($section->values ?? []) || $html !== (string) $section->html) {
                $count('page sections');
                $dryRun || $section->forceFill(['values' => $values, 'html' => $section->html === null ? null : $html])->saveQuietly();
            }
        }

        foreach (SectionTemplate::query()->get() as $template) {
            $html = self::replace((string) $template->html);
            if ($html !== (string) $template->html) {
                $count('section library');
                $dryRun || $template->forceFill(['html' => $html])->saveQuietly();
            }
        }

        foreach (Setting::query()->get() as $setting) {
            $value = self::replace((string) $setting->value);
            if ($value !== (string) $setting->value) {
                $count('settings');
                $dryRun || $setting->forceFill(['value' => $value])->save();
            }
        }

        if (! $dryRun) {
            app(SiteSettings::class)->forget();
        }

        return $changed;
    }

    public static function replace(string $text): string
    {
        return (string) preg_replace(self::pattern(), '', $text);
    }

    /**
     * "http://localhost:8080", "https://127.0.0.1", the APP_URL host — with
     * any port — but only when a /storage/ path follows.
     */
    private static function pattern(): string
    {
        $hosts = ['localhost', '127.0.0.1'];

        if ($app = parse_url((string) config('app.url'), PHP_URL_HOST)) {
            $hosts[] = $app;
        }

        $alternatives = implode('|', array_map(fn ($h) => preg_quote($h, '#'), array_unique($hosts)));

        return '#https?://(?:'.$alternatives.')(?::\d+)?(?=/storage/)#i';
    }

    private static function walk(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => self::walk($v), $value);
        }

        return is_string($value) ? self::replace($value) : $value;
    }
}
