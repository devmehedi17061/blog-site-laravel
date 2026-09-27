<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Slug
{
    /**
     * Builds a slug that is unique for the given model, appending -2, -3 …
     * until it is free. Pass $ignoreId when updating so a record does not
     * collide with itself, and $prefix where the stored slug carries one —
     * pages keep a leading slash so they read like the URL they serve.
     */
    public static function unique(
        string $modelClass,
        string $value,
        ?int $ignoreId = null,
        string $column = 'slug',
        string $prefix = ''
    ): string {
        $base = Str::slug($value) ?: Str::random(8);
        $slug = $prefix.$base;
        $suffix = 1;

        while (self::taken($modelClass, $column, $slug, $ignoreId)) {
            $suffix++;
            $slug = $prefix.$base.'-'.$suffix;
        }

        return $slug;
    }

    private static function taken(string $modelClass, string $column, string $slug, ?int $ignoreId): bool
    {
        // A record in the Trash keeps its address, so restoring it never
        // collides with something made in the meantime.
        /** @var Model $modelClass */
        $query = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
            ? $modelClass::withTrashed()->where($column, $slug)
            : $modelClass::where($column, $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
