<?php

namespace App\Actions\Posts;

use App\Models\ActivityLog;
use App\Models\Post;
use App\Support\Slug;

/**
 * Create and update share the same normalisation rules, so they live here
 * rather than being duplicated across two controller methods.
 */
class SavePost
{
    public function create(array $data, int $fallbackAuthorId): Post
    {
        $data['user_id'] = $data['user_id'] ?? $fallbackAuthorId;
        $data['slug'] = Slug::unique(Post::class, $this->slugSource($data, $data['title']));
        $data['published_at'] = $this->publishedAt($data);

        // refresh(): the first version should hold the column defaults too.
        $post = Post::create($data)->refresh();
        $post->recordRevision();

        ActivityLog::record('Created post', $post->title, $post);

        return $post;
    }

    public function update(Post $post, array $data): Post
    {
        // An untouched slug stays as it is, so published URLs do not move
        // every time someone fixes a typo in the title.
        if (array_key_exists('slug', $data)) {
            $source = $this->slugSource($data, $data['title'] ?? $post->title);
            $data['slug'] = Slug::unique(Post::class, $source, $post->id);
        }

        $data['published_at'] = $this->publishedAt($data, $post);

        $post->ensureBaseline();
        $post->update($data);
        $post->recordRevision();

        ActivityLog::record('Updated post', $post->title, $post);

        return $post;
    }

    /** Falls back to the title when no slug was supplied or it was left blank. */
    private function slugSource(array $data, string $title): string
    {
        $slug = trim((string) ($data['slug'] ?? ''));

        return $slug !== '' ? $slug : $title;
    }

    /**
     * A post that is published without an explicit date is stamped now;
     * one moved back to Draft loses its date so the listing stays honest.
     */
    private function publishedAt(array $data, ?Post $post = null): ?string
    {
        $status = $data['status'] ?? $post?->status ?? 'Draft';
        $given = $data['published_at'] ?? $post?->published_at;

        if ($status === 'Draft') {
            return null;
        }

        if ($given) {
            return $given instanceof \DateTimeInterface
                ? $given->format('Y-m-d H:i:s')
                : (string) $given;
        }

        return now()->toDateTimeString();
    }
}
