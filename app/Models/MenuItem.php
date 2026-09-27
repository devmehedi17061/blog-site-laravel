<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasFactory;

    public const LOCATIONS = ['Header', 'Footer'];

    public const VISIBILITIES = ['Public', 'Authenticated', 'Admin only'];

    public const TARGETS = ['_self', '_blank'];

    /** Depth is 0-based, so 2 means three levels: item → sub → sub-sub. */
    public const MAX_DEPTH = 2;

    protected $fillable = [
        'location', 'parent_id', 'label', 'icon', 'url',
        'target', 'visibility', 'depth', 'position',
    ];

    protected function casts(): array
    {
        return ['depth' => 'integer', 'position' => 'integer'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('position');
    }

    public function scopeLocation(Builder $query, ?string $location): Builder
    {
        return in_array($location, self::LOCATIONS, true)
            ? $query->where('location', $location)
            : $query;
    }

    /**
     * Whether this item should be shown to whoever is looking.
     *
     * "Admin only" means someone who can open the panel at all — the same
     * test the panel itself makes — rather than a named role, so a new role
     * with panel access does not silently lose menu items.
     */
    public function visibleTo(?User $user): bool
    {
        return match ($this->visibility) {
            'Authenticated' => $user !== null,
            'Admin only' => (bool) $user?->hasPermission('pages.view'),
            default => true,
        };
    }

    /**
     * The whole menu for one location, flat and in render order.
     *
     * Flat rather than nested because that is how the builder edits it: a
     * list where each row carries its own depth, which makes reordering a
     * matter of moving one element and nesting a matter of changing one
     * number. tree() turns it back into parents and children for rendering.
     *
     * @return Collection<int, MenuItem>
     */
    public static function flat(string $location): Collection
    {
        return static::query()
            ->where('location', $location)
            ->orderBy('position')
            ->get();
    }

    /**
     * The same items nested, for a renderer that walks parents and children.
     * Built in memory from one query rather than with eager loading, so the
     * depth limit is the only thing deciding how deep it goes.
     *
     * @return array<int, array{item: MenuItem, children: array}>
     */
    public static function tree(string $location, ?User $user = null): array
    {
        $rows = static::flat($location)->filter(fn (MenuItem $item) => $item->visibleTo($user));

        // A link to a draft or not-yet-due page would only 404 for visitors:
        // on its own it waits until the page goes live; as a drop-down's
        // parent it stays, as a plain label.
        $hidden = $user?->hasPermission('pages.view') ? [] : static::unpublishedPaths();

        $byParent = [];

        foreach ($rows as $item) {
            $byParent[$item->parent_id ?? 0][] = $item;
        }

        $build = function (int $parentId) use (&$build, $byParent, $hidden): array {
            $nodes = [];

            foreach ($byParent[$parentId] ?? [] as $item) {
                $children = $build($item->id);

                if ($hidden && in_array(rtrim((string) strtok((string) $item->url, '?#'), '/') ?: '/', $hidden, true)) {
                    if (! $children) {
                        continue;
                    }
                    $item->url = null;
                }

                $nodes[] = ['item' => $item, 'children' => $children];
            }

            return $nodes;
        };

        return $build(0);
    }

    /**
     * Paths of this site's drafts and not-yet-due pages and posts. Visitors
     * would only get a 404 there, so their menu links wait until they go live.
     *
     * @return list<string>
     */
    public static function unpublishedPaths(): array
    {
        $pages = Page::withTrashed()->whereNotIn('id', Page::query()->published()->select('id'))->get(['id', 'slug'])
            ->map(fn (Page $page) => $page->path());
        $posts = Post::withTrashed()->whereNotIn('id', Post::query()->published()->select('id'))->get(['id', 'slug'])
            ->map(fn (Post $post) => $post->pathForSlug((string) $post->slug));

        return $pages->concat($posts)->map(fn (string $path) => rtrim($path, '/') ?: '/')->values()->all();
    }
}
