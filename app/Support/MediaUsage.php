<?php

namespace App\Support;

use App\Models\Media;
use App\Models\Post;

/**
 * Where a file is used, so the library can warn before deleting something a
 * published post still points at.
 */
class MediaUsage
{
    /** @return array<int, array{type: string, id: int, title: string}> */
    public static function for(Media $media): array
    {
        $used = [];

        // Featured images hold a real foreign key.
        foreach (Post::where('media_id', $media->id)->get(['id', 'title']) as $post) {
            $used[$post->id] = ['type' => 'Featured image', 'id' => $post->id, 'title' => $post->title];
        }

        // Body images are only referenced by URL, so match on the stored path.
        foreach (Post::where('body', 'like', '%'.$media->file_name.'%')->get(['id', 'title']) as $post) {
            $used[$post->id] ??= ['type' => 'In the post body', 'id' => $post->id, 'title' => $post->title];
        }

        return array_values($used);
    }

    public static function countFor(Media $media): int
    {
        return count(self::for($media));
    }
}
