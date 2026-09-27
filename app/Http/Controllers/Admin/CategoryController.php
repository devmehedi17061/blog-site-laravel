<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Post;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Categories, as a tree.
 *
 * Rows are created and edited one at a time — each carries a name, a slug,
 * SEO and an image, which is too much to save as a batch. Order and nesting
 * are their own endpoint, because dragging one row changes several.
 */
class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);

        return CategoryResource::collection($this->withCounts(Category::flat()));
    }

    public function show(Category $category): CategoryResource
    {
        $this->authorize('view', $category);

        return CategoryResource::make($category->load('image')->loadCount('posts'));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['parent_id'] = $this->legalParent($data['parent_id'] ?? null, null);
        $data['depth'] = $this->depthUnder($data['parent_id']);
        // The slug is optional: left out entirely, it is built from the name.
        $data['slug'] = Slug::unique(Category::class, trim((string) ($data['slug'] ?? '')) ?: $data['name']);
        // New categories land at the end of their branch.
        $data['position'] = (int) Category::where('parent_id', $data['parent_id'])->max('position') + 1;

        $category = Category::create($data);

        ActivityLog::record('Created category', $category->name, $category);

        return CategoryResource::make($category->load('image')->loadCount('posts'))
            ->response()->setStatusCode(201);
    }

    public function update(StoreCategoryRequest $request, Category $category): CategoryResource
    {
        $data = $request->validated();

        if (array_key_exists('parent_id', $data)) {
            $data['parent_id'] = $this->legalParent($data['parent_id'], $category);
            $data['depth'] = $this->depthUnder($data['parent_id']);
        }

        if (array_key_exists('slug', $data)) {
            $data['slug'] = Slug::unique(
                Category::class,
                trim((string) $data['slug']) ?: ($data['name'] ?? $category->name),
                $category->id
            );
        }

        $category->update($data);

        ActivityLog::record('Updated category', $category->name, $category);

        return CategoryResource::make($category->fresh()->load('image')->loadCount('posts'));
    }

    /**
     * Deleting one, and saying out loud what happens to everything attached.
     *
     * Posts go wherever the caller says — another category, or nowhere.
     * Children move up to this category's own parent, so a branch is never
     * silently orphaned or destroyed along with it.
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $data = $request->validate([
            'move_posts_to' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $moveTo = $data['move_posts_to'] ?? null;

        if ($moveTo === $category->id) {
            $moveTo = null;
        }

        // A post cannot be moved into a category that is about to go with it.
        if ($moveTo !== null && in_array($moveTo, $category->descendantIds(), true)) {
            return response()->json([
                'message' => 'Those posts cannot move into a category that is being deleted with this one.',
            ], 422);
        }

        $name = $category->name;
        $moved = 0;
        $promoted = 0;

        DB::transaction(function () use ($category, $moveTo, &$moved, &$promoted) {
            // Only posts filed *directly* here. The sub-categories survive —
            // they are promoted a level below — so their posts stay with
            // them rather than being swept out of a category that still
            // exists.
            $moved = Post::where('category_id', $category->id)
                ->update(['category_id' => $moveTo]);

            $promoted = Category::where('parent_id', $category->id)
                ->update(['parent_id' => $category->parent_id]);

            $category->delete();
        });

        ActivityLog::record('Deleted category', $name);

        return response()->json([
            'message' => $this->deletionMessage($name, $moved, $promoted),
            'data' => CategoryResource::collection($this->withCounts(Category::flat()))->resolve(),
        ]);
    }

    /**
     * The whole tree's order and nesting in one request.
     *
     * A flat list where each row carries its own depth — the shape a
     * drag-and-drop list has. Parents are rebuilt here by walking the list
     * with a stack of the last row seen at each depth, and depth is clamped
     * rather than trusted, so an impossible tree cannot be saved.
     */
    public function reorder(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission('categories.update'), 403);

        $data = $request->validate([
            'items' => ['present', 'array', 'max:500'],
            'items.*.id' => ['required', 'integer', 'exists:categories,id'],
            'items.*.depth' => ['nullable', 'integer', 'between:0,'.Category::MAX_DEPTH],
        ]);

        DB::transaction(function () use ($data) {
            $lastAtDepth = [];
            $previousDepth = -1;

            foreach (array_values($data['items']) as $position => $row) {
                $depth = min(
                    max(0, (int) ($row['depth'] ?? 0)),
                    Category::MAX_DEPTH,
                    $previousDepth + 1
                );

                $parentId = $depth === 0 ? null : ($lastAtDepth[$depth - 1] ?? null);

                if ($depth > 0 && $parentId === null) {
                    $depth = 0;
                }

                Category::whereKey($row['id'])->update([
                    'parent_id' => $parentId,
                    'depth' => $depth,
                    'position' => $position,
                ]);

                $lastAtDepth[$depth] = (int) $row['id'];
                $previousDepth = $depth;

                foreach (array_keys($lastAtDepth) as $known) {
                    if ($known > $depth) {
                        unset($lastAtDepth[$known]);
                    }
                }
            }
        });

        ActivityLog::record('Reordered categories', 'Category tree');

        return CategoryResource::collection($this->withCounts(Category::flat()));
    }

    // ── helpers ────────────────────────────────────────────────────────────

    /**
     * Refuses a parent that would make a loop or go too deep, falling back to
     * the top level. A category cannot be moved inside itself — the tree
     * would detach from the root and every one of its posts would vanish
     * from the site with no way back through the UI.
     */
    private function legalParent(?int $parentId, ?Category $moving): ?int
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Category::find($parentId);

        if (! $parent) {
            return null;
        }

        if ($moving && ($parent->id === $moving->id || in_array($parent->id, $moving->descendantIds(), true))) {
            return null;
        }

        return $parent->depth >= Category::MAX_DEPTH ? null : $parent->id;
    }

    private function depthUnder(?int $parentId): int
    {
        if ($parentId === null) {
            return 0;
        }

        return min((int) Category::whereKey($parentId)->value('depth') + 1, Category::MAX_DEPTH);
    }

    /**
     * Adds both counts every list needs: posts filed directly in a category,
     * and posts anywhere in its branch — which is what its archive shows.
     */
    private function withCounts($categories)
    {
        $direct = Post::selectRaw('category_id, COUNT(*) as total')
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        return $categories->each(function (Category $category) use ($direct, $categories) {
            $category->setAttribute('posts_count', (int) ($direct[$category->id] ?? 0));

            $branch = 0;

            foreach ($category->descendantIds() as $id) {
                $branch += (int) ($direct[$id] ?? 0);
            }

            $category->setAttribute('branch_posts_count', $branch);
        });
    }

    private function deletionMessage(string $name, int $moved, int $promoted): string
    {
        $parts = ["\"{$name}\" deleted."];

        if ($moved > 0) {
            $parts[] = $moved === 1 ? '1 post reassigned.' : "{$moved} posts reassigned.";
        }

        if ($promoted > 0) {
            $parts[] = $promoted === 1
                ? '1 sub-category moved up a level.'
                : "{$promoted} sub-categories moved up a level.";
        }

        return implode(' ', $parts);
    }
}
