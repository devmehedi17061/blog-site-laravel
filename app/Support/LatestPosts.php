<?php

namespace App\Support;

use App\Models\Post;

/**
 * The newest published posts, shaped as the items of a section group, so a
 * template repeats them with {{#latest_posts}} … {{/latest_posts}} exactly as
 * it would a group someone filled in by hand:
 *
 *   {{ title }} {{ url }} {{ date }} {{ category }} {{ excerpt }} {{ image }}
 *
 * A section sets how many with a `posts_count` field, and what shows for a
 * post with no featured image with `fallback_image` (and `fallback_image_2`,
 * `fallback_image_3` … which take turns, so the cards do not all match).
 */
class LatestPosts
{
    public const MAX = 12;

    /**
     * @param  array<int, string>  $fallbacks
     * @return array<int, array<string, string>>
     */
    public static function items(int $count = 3, array $fallbacks = []): array
    {
        $site = app(Site::class);

        return Post::query()
            ->published()
            ->with(['category:id,name', 'featuredImage'])
            ->latest('published_at')
            ->limit(max(1, min(self::MAX, $count ?: 3)))
            ->get()
            ->values()
            ->map(fn (Post $post, int $i) => [
                'title' => (string) $post->title,
                'url' => '/blog/'.$post->slug,
                'date' => $site->date($post->published_at),
                'category' => (string) ($post->category?->name ?? ''),
                'excerpt' => (string) ($post->excerpt ?? ''),
                'image' => (string) ($post->featuredImage?->url ?: ($fallbacks ? $fallbacks[$i % count($fallbacks)] : '')),
            ])
            ->all();
    }
}
