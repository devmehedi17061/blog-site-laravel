<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What the site shows at "/": one published page, or the built-in course homepage.
 *
 * A site-wide choice, so it needs pages.update — the same permission that
 * could otherwise rewrite that page's content anyway.
 */
class HomepageController extends Controller
{
    public function show(): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        return response()->json($this->summary());
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('pages.update'), 403);

        $data = $request->validate([
            'page_id' => ['present', 'nullable', 'integer', Rule::exists('pages', 'id')],
        ], [
            'page_id.exists' => 'That page no longer exists.',
        ]);

        $page = isset($data['page_id']) ? Page::find($data['page_id']) : null;

        // The homepage is what everyone lands on: a draft or a page dated
        // next week would put a 404-shaped hole at the front door.
        if ($page && ! $page->isLive()) {
            return response()->json([
                'message' => "\"{$page->title}\" is not live yet. Publish it before making it the homepage.",
                'errors' => ['page_id' => ['Only a published page can be the homepage.']],
            ], 422);
        }

        Page::setHome($page);

        ActivityLog::record(
            'Changed homepage',
            $page ? "Homepage is now \"{$page->title}\"" : 'Homepage now shows the course homepage',
            $page
        );

        return response()->json([
            'message' => $page ? "\"{$page->title}\" is now the homepage." : 'The homepage now shows the built-in course homepage (featured courses and latest posts).',
        ] + $this->summary());
    }

    private function summary(): array
    {
        $page = Page::homeId() ? Page::find(Page::homeId()) : null;

        return [
            'page_id' => $page?->id,
            'title' => $page?->title,
            // What "/" shows right now, in words, for the pages list.
            'shows' => $page ? $page->title : 'Latest blog posts',
            'candidates' => Page::query()->published()->orderBy('title')->get(['id', 'title', 'slug'])
                ->map(fn (Page $p) => ['id' => $p->id, 'title' => $p->title, 'slug' => $p->slug]),
        ];
    }
}
