<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\MenuIcons;
use Illuminate\Http\JsonResponse;

/**
 * Everything the menu builder needs to fill its pickers, in one request —
 * including the icon geometry, so the panel draws exactly the icons the
 * public site will draw rather than a lookalike set of its own.
 */
class MenuOptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $this->authorize('viewAny', MenuItem::class);

        return response()->json([
            'locations' => MenuItem::LOCATIONS,
            'visibilities' => MenuItem::VISIBILITIES,
            'targets' => MenuItem::TARGETS,
            'max_depth' => MenuItem::MAX_DEPTH,

            'icons' => MenuIcons::all(),

            // Somewhere to link to, so nobody has to remember a slug.
            'links' => $this->links(),
        ]);
    }

    /** @return array<int, array{group: string, items: array}> */
    private function links(): array
    {
        $pages = Page::query()
            ->orderBy('title')
            ->get(['id', 'title', 'slug'])
            ->map(fn (Page $page) => [
                'label' => $page->isHome() ? $page->title.' (homepage)' : $page->title,
                // The homepage links as "/", never by its own slug.
                'url' => $page->path(),
            ])
            ->all();

        $categories = Category::query()
            ->orderBy('name')
            ->get(['name', 'slug'])
            ->map(fn (Category $category) => [
                'label' => $category->name,
                'url' => '/category/'.$category->slug,
            ])
            ->all();

        return array_values(array_filter([
            $pages ? ['group' => 'Pages', 'items' => $pages] : null,
            ['group' => 'Blog', 'items' => [['label' => 'Blog index', 'url' => '/blog']]],
            $categories ? ['group' => 'Categories', 'items' => $categories] : null,
        ]));
    }
}
