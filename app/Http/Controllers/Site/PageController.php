<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A page at its own slug, rendered server side so its sections are real HTML
 * that search engines can read.
 *
 * Two exceptions to "published pages only, at their slug":
 *
 *   the homepage is served at "/", so its own slug redirects there — one
 *   address per page, which is what search engines want to see;
 *
 *   ?preview=1 shows a draft or scheduled page to someone signed in to the
 *   panel who may read pages, marked so it is never indexed or cached.
 */
class PageController extends Controller
{
    public function show(Request $request, string $slug): Response|RedirectResponse
    {
        $page = Page::query()
            ->with(['sections.template', 'featuredImage', 'author:id,name'])
            ->where('slug', $this->normalise($slug))
            ->first();

        $previewing = $page && $this->mayPreview($request);

        abort_if(! $page || (! $page->isLive() && ! $previewing), 404);

        if ($page->isHome() && ! $previewing) {
            return redirect('/', 301);
        }

        return self::render($page, $previewing);
    }

    public static function render(Page $page, bool $previewing = false): Response
    {
        $response = response()->view('frontend.page', [
            'page' => $page,
            'showHeader' => $page->showsHeader(),
            'showFooter' => $page->showsFooter(),
            'previewing' => $previewing,
        ]);

        if ($previewing) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    private function mayPreview(Request $request): bool
    {
        $user = $request->user();

        return $request->boolean('preview')
            && $user !== null
            && $user->isActive()
            && $user->hasPermission('pages.view');
    }

    /** Pages are stored with a leading slash, the way the menu links them. */
    private function normalise(string $slug): string
    {
        return '/'.trim($slug, '/');
    }
}
