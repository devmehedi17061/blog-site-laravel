<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Support\Site;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * /sitemap.xml and /robots.txt — what search engines read first.
 *
 * The sitemap lists what should be in search results and nothing else:
 * live pages and posts, minus anything set to noindex, minus anything whose
 * canonical address is somewhere else (that address is the one to list).
 * The homepage appears once, as "/", never also under its own slug.
 */
class SitemapController extends Controller
{
    public function __construct(private readonly Site $site) {}

    public function sitemap(): Response
    {
        $urls = $this->site->hiddenFromSearch() ? [] : $this->urls();

        return response()
            ->view('frontend.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex');
    }

    public function robots(): Response
    {
        // The whole site hidden (Settings → Site): say so here as well as in
        // every page's head, and do not advertise a sitemap.
        if ($this->site->hiddenFromSearch()) {
            return response("User-agent: *\nDisallow: /\n")->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $rules = trim(str_replace("\r\n", "\n", (string) $this->site->get('robots_txt')));

        return response($rules."\n\nSitemap: ".url('/sitemap.xml')."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * /feed — the latest posts for feed readers (RSS 2.0). The same posts a
     * visitor can find: published, and not hidden from search. The author
     * goes in <dc:creator>: RSS's own <author> is meant for an email address.
     */
    public function feed(): Response
    {
        $posts = Post::published()
            ->with(['author:id,name', 'category:id,name'])
            ->where(fn ($q) => $q->whereNull('robots')->orWhere('robots', 'like', 'index%'))
            ->latest('published_at')
            ->take(20)
            ->get();

        return response()
            ->view('frontend.feed', ['posts' => $posts])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    /** @return array<int, array{loc: string, lastmod: ?string}> */
    private function urls(): array
    {
        $urls = [['loc' => url('/'), 'lastmod' => null]];
        $homeId = Page::homeId();

        if ($homeId) {
            $urls[0]['lastmod'] = Page::find($homeId)?->updated_at?->toAtomString();
        }

        $indexable = fn ($q) => $q->where(fn ($r) => $r->whereNull('robots')->orWhere('robots', 'like', 'index%'))
            ->where(fn ($c) => $c->whereNull('canonical_url')->orWhere('canonical_url', ''));

        $latestPost = Post::published()->max('updated_at');
        $urls[] = ['loc' => route('blog.index'), 'lastmod' => $latestPost ? Carbon::parse($latestPost)->toAtomString() : null];

        foreach (Page::published()->tap($indexable)->orderBy('title')->get(['id', 'slug', 'updated_at']) as $page) {
            if ($page->id === $homeId) {
                continue;
            }
            $urls[] = ['loc' => url($page->path()), 'lastmod' => $page->updated_at?->toAtomString()];
        }

        foreach (Post::published()->tap($indexable)->latest('published_at')->get(['id', 'slug', 'updated_at']) as $post) {
            $urls[] = ['loc' => route('blog.show', $post->slug), 'lastmod' => $post->updated_at?->toAtomString()];
        }

        $withPosts = Post::published()->whereNotNull('category_id')->distinct()->pluck('category_id');
        foreach (Category::whereIn('id', $withPosts)->orderBy('name')->get(['slug', 'updated_at']) as $category) {
            $urls[] = ['loc' => route('blog.category', $category->slug), 'lastmod' => $category->updated_at?->toAtomString()];
        }

        return $urls;
    }
}
