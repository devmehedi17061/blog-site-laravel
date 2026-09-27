<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * The SEO fields posts and pages share: their rules, and how a record's
 * values turn into what the <head> says.
 */
class Seo
{
    public const ROBOTS = [
        'index, follow' => 'Index, follow (default)',
        'noindex, follow' => 'Noindex, follow — hide this page, still follow its links',
        'index, nofollow' => 'Index, nofollow — list it, but do not follow its links',
        'noindex, nofollow' => 'Noindex, nofollow — hide it and ignore its links',
    ];

    /**
     * Validation for the shared fields.
     *
     * The title and description limits are generous on purpose: Google cuts
     * by pixel width, not a character count, and a Bengali or Arabic title
     * measures differently from an English one. The counters on screen
     * advise; only an unreasonable length is refused.
     */
    public static function rules(): array
    {
        return [
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:400'],
            'focus_keyword' => ['nullable', 'string', 'max:100'],
            'canonical_url' => ['nullable', 'url:http,https', 'max:255'],
            'robots' => ['nullable', Rule::in(array_keys(self::ROBOTS))],
            'og_title' => ['nullable', 'string', 'max:160'],
            'og_description' => ['nullable', 'string', 'max:400'],
            // A library path (/storage/…) or a full address — never javascript: or data:.
            'og_image' => ['nullable', 'string', 'max:500', 'regex:#^(/(?!/)|https?://)#i'],
        ];
    }

    public static function attributes(): array
    {
        return [
            'meta_title' => 'SEO title',
            'meta_description' => 'meta description',
            'canonical_url' => 'canonical URL',
            'og_title' => 'social title',
            'og_description' => 'social description',
            'og_image' => 'social image',
        ];
    }

    public static function messages(): array
    {
        return [
            'og_image.regex' => 'Choose an image from the library, or paste a full address starting with https://',
            'canonical_url.url' => 'Use a full address, starting with https://',
        ];
    }

    /**
     * One robots value for the page. The Settings → Site switch that hides
     * the whole site wins over anything a single page says.
     */
    public static function robots(?string $own, bool $siteHidden, bool $previewing = false): string
    {
        if ($siteHidden || $previewing) {
            return 'noindex, nofollow';
        }

        return array_key_exists((string) $own, self::ROBOTS) ? (string) $own : 'index, follow';
    }

    /** Social sites need a full address; library images are stored as paths. */
    public static function absolute(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        return str_starts_with($url, '/') ? url($url) : $url;
    }
}
