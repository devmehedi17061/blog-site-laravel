<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Pages\SaveSections;
use App\Http\Controllers\Controller;
use App\Http\Requests\PageIndexRequest;
use App\Http\Requests\StorePageRequest;
use App\Http\Requests\UpdatePageRequest;
use App\Http\Resources\PageResource;
use App\Models\ActivityLog;
use App\Models\Page;
use App\Support\PageTemplates;
use App\Support\Slug;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PageController extends Controller
{
    public function __construct(private readonly SaveSections $saveSections) {}

    public function index(PageIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Page::class);
        Page::publishDue();

        $pages = Page::query()
            ->with('author:id,name')
            ->withCount('sections')
            ->search($request->input('search'))
            ->status($request->input('status'))
            ->sortBy($request->input('sort'), $request->input('direction'))
            ->paginate($request->perPage())
            ->withQueryString();

        return PageResource::collection($pages);
    }

    public function show(Page $page): PageResource
    {
        $this->authorize('view', $page);

        return PageResource::make(
            $page->load(['author:id,name', 'featuredImage', 'sections.template'])->loadCount('sections')
        );
    }

    public function store(StorePageRequest $request): JsonResponse
    {
        $this->authorize('create', Page::class);

        $data = $request->validated();
        $template = $data['template'] ?? 'blank';
        $slugSource = trim((string) ($data['slug'] ?? '')) ?: $data['title'];

        // Assigned, not merged with +: that operator keeps the left-hand key,
        // so a null slug from the request would win over the one computed here.
        $data['slug'] = $this->pageSlug($slugSource, $data['title']);
        $data['layout'] = $data['layout'] ?? PageTemplates::layoutFor($template);
        $data['user_id'] = ($data['user_id'] ?? null) ?: $request->user()->id;
        $data['published_at'] = $this->publishedAt($data);

        $page = Page::create($data);

        // A new page starts as something editable, not an empty canvas.
        $this->saveSections->fromTemplate($page, $template);
        $page->refresh()->recordRevision();

        ActivityLog::record('Created page', $page->title, $page);

        return PageResource::make(
            $page->fresh()->load(['author:id,name', 'sections.template'])->loadCount('sections')
        )->response()->setStatusCode(201);
    }

    public function update(UpdatePageRequest $request, Page $page): PageResource
    {
        $this->authorize('update', $page);

        $data = $request->validated();

        if (array_key_exists('slug', $data)) {
            $source = trim((string) $data['slug']) ?: ($data['title'] ?? $page->title);
            $data['slug'] = $this->pageSlug($source, $data['title'] ?? $page->title, $page->id);
        }

        $data['published_at'] = $this->publishedAt($data, $page);

        $page->ensureBaseline();
        $page->update($data);
        $page->recordRevision();

        ActivityLog::record('Updated page', $page->title, $page);

        return PageResource::make(
            $page->fresh()->load(['author:id,name', 'featuredImage', 'sections.template'])->loadCount('sections')
        );
    }

    public function destroy(Page $page): JsonResponse
    {
        $this->authorize('delete', $page);

        if ($page->isHome()) {
            return response()->json([
                'message' => "\"{$page->title}\" is the homepage. Choose another homepage before deleting it.",
            ], 422);
        }

        // To the Trash: restorable from there until it is emptied.
        $page->delete();

        ActivityLog::record('Moved page to trash', $page->title, $page);

        return response()->json(['message' => 'Page moved to the trash.']);
    }

    /**
     * A copy of the page and every one of its sections, as a draft. The copy
     * never inherits "homepage" — that is a setting about one page, not a
     * property a duplicate should carry.
     */
    public function duplicate(Request $request, Page $page): JsonResponse
    {
        $this->authorize('view', $page);
        $this->authorize('create', Page::class);

        $copy = DB::transaction(function () use ($request, $page) {
            $copy = $page->replicate(['slug', 'status', 'published_at']);
            $copy->title = $page->title.' (copy)';
            $copy->slug = $this->pageSlug($copy->title, $copy->title);
            $copy->status = 'Draft';
            $copy->published_at = null;
            $copy->user_id = $request->user()->id;
            $copy->save();

            // replicate() + save, not create(getAttributes()): the raw
            // attributes are already JSON, and create() would encode them twice.
            foreach ($page->sections as $section) {
                $clone = $section->replicate();
                $clone->page_id = $copy->id;
                $clone->save();
            }

            $copy->recordRevision();

            return $copy;
        });

        ActivityLog::record('Duplicated page', "{$page->title} → {$copy->title}", $copy);

        return PageResource::make(
            $copy->load(['author:id,name', 'featuredImage', 'sections.template'])->loadCount('sections')
        )->response()->setStatusCode(201);
    }

    /**
     * Page slugs are stored with a leading slash, so they read exactly like the
     * URL they serve and match how the menu links to them. Nothing is ever
     * stored as "/" itself: the site root is the homepage *setting*, so a
     * blank slug is built from the title instead.
     */
    private function pageSlug(string $source, string $title, ?int $ignoreId = null): string
    {
        $trimmed = trim($source, '/ ') ?: trim($title, '/ ') ?: 'page';

        return Slug::unique(Page::class, $trimmed, $ignoreId, 'slug', '/');
    }

    /**
     * A page published without a date is stamped now; one moved back to Draft
     * loses its date so the public site stops serving it.
     */
    private function publishedAt(array $data, ?Page $page = null): ?string
    {
        $status = $data['status'] ?? $page?->status ?? 'Draft';
        $given = $data['published_at'] ?? $page?->published_at;

        if ($status === 'Draft') {
            return null;
        }

        if ($given) {
            return $given instanceof DateTimeInterface
                ? $given->format('Y-m-d H:i:s')
                : (string) $given;
        }

        return now()->toDateTimeString();
    }
}
