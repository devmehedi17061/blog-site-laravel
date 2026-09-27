<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Pages\SaveSections;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSectionsRequest;
use App\Http\Resources\SectionResource;
use App\Models\ActivityLog;
use App\Models\Page;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The builder edits the whole tree client side and saves once, so this
 * replaces every section for a page in a single transaction.
 */
class PageSectionsController extends Controller
{
    public function __construct(private readonly SaveSections $saveSections) {}

    public function index(Page $page): AnonymousResourceCollection
    {
        $this->authorize('view', $page);

        return SectionResource::collection($page->sections()->with('template')->get());
    }

    public function update(SaveSectionsRequest $request, Page $page): AnonymousResourceCollection
    {
        $this->authorize('update', $page);

        $page->ensureBaseline();
        $page = $this->saveSections->handle($page, $request->input('sections', []));
        $page->recordRevision();

        ActivityLog::record('Updated sections', $page->title, $page);

        return SectionResource::collection($page->sections()->with('template')->get());
    }
}
