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
        // Only pages (stored with a leading slash) can be nested.
        $base = ($prefix === '/' ? self::path($value) : Str::slug($value)) ?: Str::random(8);
        $slug = $prefix.$base;
        $suffix = 1;

        while (self::taken($modelClass, $column, $slug, $ignoreId)) {
            $suffix++;
            $slug = $prefix.$base.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * A slug that may be a path: each part between slashes is made a slug of
     * its own and the slashes are kept, so a page at services/sap/training
     * stays nested. Text with no slash comes out exactly as Str::slug makes it.
     */
    public static function path(string $value): string
    {
        return implode('/', array_filter(array_map(fn ($part) => Str::slug($part), explode('/', $value)), fn ($p) => $p !== ''));
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
