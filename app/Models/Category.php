<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use App\Models\Concerns\HasRedirects;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use Filterable, HasFactory, HasRedirects;

    /** Depth is 0-based, so 2 means three levels: category → sub → sub-sub. */
    public const MAX_DEPTH = 2;

    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'media_id',
        'meta_title', 'meta_description', 'depth', 'position',
    ];

    protected array $searchable = ['name', 'slug', 'description'];

    protected array $sortable = ['name', 'created_at'];

    protected function casts(): array
    {
        return ['depth' => 'integer', 'position' => 'integer'];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('position');
    }

    public function image(): BelongsTo
    {
        // withTrashed: a file in the Trash is still on disk and keeps showing
        // until it is deleted for good.
        return $this->belongsTo(Media::class, 'media_id')->withTrashed();
    }

    // ── the tree ───────────────────────────────────────────────────────────

    /**
     * Every category, flat and in render order — parents immediately followed
     * by their children.
     *
     * The table stores parent_id, but the builder edits a flat list where
     * each row carries its own depth, and the public side wants them in
     * reading order. Both come from this, sorted in memory from one query
     * rather than with a recursive one.
     *
     * @return Collection<int, Category>
     */
    public static function flat(): Collection
    {
        $rows = static::query()->orderBy('position')->orderBy('name')->get();

        $byParent = [];

        foreach ($rows as $row) {
            $byParent[$row->parent_id ?? 0][] = $row;
        }

        $ordered = new Collection;

        $walk = function (int $parentId, int $depth) use (&$walk, $byParent, $ordered) {
            foreach ($byParent[$parentId] ?? [] as $row) {
                // Trusted from the tree it is actually in, not from the
                // column — a row moved by hand cannot lie about its level.
                $row->depth = $depth;
                $ordered->push($row);
                $walk($row->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $ordered;
    }

    /**
     * This category and everything under it.
     *
     * A parent's archive lists its children's posts too, so the query needs
     * every id in the branch rather than just this one.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        // Iterative rather than recursive: one query per level, and the depth
        // limit means that is at most three.
        while ($frontier !== []) {
            $frontier = static::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /** The trail from the top down to this category, for a breadcrumb. */
    public function ancestors(): Collection
    {
        $trail = new Collection;
        $current = $this->parent;

        while ($current) {
            $trail->prepend($current);
            $current = $current->parent;
        }

        return $trail;
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /** "Engineering › Backend", for a dropdown or a list. */
    public function path(): string
    {
        return $this->ancestors()->push($this)->pluck('name')->implode(' › ');
    }

    // ── redirects when the slug changes (HasRedirects) ─────────────────────

    public function pathForSlug(string $slug): string
    {
        return '/category/'.ltrim($slug, '/');
    }

    /** A category page exists whether or not anyone has linked to it yet. */
    protected function wasPublic(): bool
    {
        return true;
    }
}
