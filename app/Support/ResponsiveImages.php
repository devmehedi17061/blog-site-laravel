<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Support\Collection;

/**
 * Makes the site's own images responsive wherever they appear in HTML — a
 * post body, a section — by adding what the browser needs to pick the
 * smallest copy that still looks sharp:
 *
 *   srcset / sizes   the WebP copies from ImageVariants, by width
 *   width / height   so the page does not jump as images arrive
 *   loading / decoding  lazy, so images below the fold wait their turn
 *
 * Only <img> tags pointing at /storage/media/ are touched, and nothing an
 * author already set is overwritten.
 */
class ResponsiveImages
{
    /** A post column: full width on a phone, about 720px beside nothing. */
    public const POST_SIZES = '(max-width: 760px) 100vw, 720px';

    public static function apply(?string $html, string $sizes = '100vw'): string
    {
        $html = (string) $html;

        if (! str_contains($html, '/storage/media/')) {
            return $html;
        }

        preg_match_all('#<img\b[^>]*>#i', $html, $tags);
        $media = self::lookup($tags[0]);

        if ($media->isEmpty()) {
            return $html;
        }

        return preg_replace_callback('#<img\b[^>]*>#i', function ($match) use ($media, $sizes) {
            $tag = $match[0];
            $path = self::pathIn($tag);
            $file = $path ? $media->get($path) : null;

            return $file ? self::enhance($tag, $file, $sizes) : $tag;
        }, $html);
    }

    /** Attributes for an <img> built in Blade from a Media model. */
    public static function attributes(?Media $media, string $sizes = '100vw', bool $lazy = true): string
    {
        if (! $media) {
            return '';
        }

        $parts = [];

        if ($srcset = $media->srcset()) {
            $parts[] = 'srcset="'.e($srcset).'"';
            $parts[] = 'sizes="'.e($sizes).'"';
        }
        if ($media->width && $media->height) {
            $parts[] = 'width="'.$media->width.'" height="'.$media->height.'"';
        }
        $parts[] = $lazy ? 'loading="lazy" decoding="async"' : 'decoding="async"';

        return implode(' ', $parts);
    }

    private static function enhance(string $tag, Media $media, string $sizes): string
    {
        $add = [];

        if (! preg_match('/\ssrcset=/i', $tag) && ($srcset = $media->srcset())) {
            $add[] = 'srcset="'.e($srcset).'"';
            if (! preg_match('/\ssizes=/i', $tag)) {
                $add[] = 'sizes="'.e($sizes).'"';
            }
        }
        if (! preg_match('/\swidth=/i', $tag) && ! preg_match('/\sheight=/i', $tag) && $media->width && $media->height) {
            $add[] = 'width="'.$media->width.'" height="'.$media->height.'"';
        }
        if (! preg_match('/\sloading=/i', $tag)) {
            $add[] = 'loading="lazy"';
        }
        if (! preg_match('/\sdecoding=/i', $tag)) {
            $add[] = 'decoding="async"';
        }

        if (! $add) {
            return $tag;
        }

        $selfClosing = str_ends_with($tag, '/>');
        $opening = rtrim(substr($tag, 0, $selfClosing ? -2 : -1));

        return $opening.' '.implode(' ', $add).($selfClosing ? ' />' : '>');
    }

    /** "media/abc.jpg" from a src of /storage/media/abc.jpg or http://host/storage/media/abc.jpg. */
    private static function pathIn(string $tag): ?string
    {
        if (! preg_match('#\ssrc=["\']([^"\']+)["\']#i', $tag, $m)) {
            return null;
        }

        $path = parse_url(html_entity_decode($m[1]), PHP_URL_PATH) ?? '';

        return str_starts_with($path, '/storage/media/') ? substr($path, strlen('/storage/')) : null;
    }

    /** One query for every image on the page. @return Collection<string, Media> */
    private static function lookup(array $tags): Collection
    {
        $paths = collect($tags)->map(fn ($t) => self::pathIn($t))->filter()->unique()->values();

        return $paths->isEmpty()
            ? collect()
            : Media::withTrashed()->whereIn('path', $paths)->get(['id', 'path', 'disk', 'width', 'height', 'variants'])->keyBy('path');
    }
}
