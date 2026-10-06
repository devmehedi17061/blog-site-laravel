<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;

/**
 * What can be in the Trash and how long it stays there.
 */
class Trash
{
    /** Days an item waits in the Trash before it is deleted for good. */
    public const DAYS = 30;

    /** The Trash tabs, keyed as they appear in the URL. */
    public const TYPES = [
        'posts' => ['model' => Post::class, 'label' => 'Posts', 'permission' => 'posts'],
        'pages' => ['model' => Page::class, 'label' => 'Pages', 'permission' => 'pages'],
        'media' => ['model' => Media::class, 'label' => 'Media', 'permission' => 'media'],
        'courses' => ['model' => Course::class, 'label' => 'Courses', 'permission' => 'courses'],
    ];

    /** @return class-string<Model> */
    public static function model(string $type): string
    {
        return self::TYPES[$type]['model'];
    }

    /**
     * Deletes for good whatever has waited out its days. One at a time, so
     * a file's own clean-up runs (Media::booted) and a page's history goes.
     */
    public static function purge(): int
    {
        $count = 0;

        foreach (self::TYPES as $type) {
            $type['model']::onlyTrashed()
                ->where('deleted_at', '<', now()->subDays(self::DAYS))
                ->chunkById(100, function ($items) use (&$count) {
                    foreach ($items as $item) {
                        $item->forceDelete();
                        $count++;
                    }
                });
        }

        return $count;
    }

    /** Whole days left before purge() takes it; 0 means it goes next time. */
    public static function daysLeft(Model $item): int
    {
        $due = $item->deleted_at->copy()->addDays(self::DAYS);

        return max(0, (int) ceil(now()->diffInSeconds($due, false) / 86400));
    }
}
