<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSectionTemplateRequest;
use App\Http\Resources\SectionTemplateResource;
use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\SectionTemplate;
use App\Support\FieldKeys;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The section library. Pages reference these, so an edit here reaches every
 * page using the section while leaving each page's own values alone.
 */
class SectionTemplateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Page::class);

        return SectionTemplateResource::collection(
            SectionTemplate::query()
                ->withCount('sections')
                ->search($request->query('search'))
                ->orderBy('name')
                ->get()
        );
    }

    public function show(SectionTemplate $template): SectionTemplateResource
    {
        $this->authorize('viewAny', Page::class);

        return SectionTemplateResource::make($template->loadCount('sections'));
    }

    public function store(StoreSectionTemplateRequest $request): JsonResponse
    {
        // Permission is checked by the form request, before validation.
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['slug'] = Slug::unique(SectionTemplate::class, $data['name']);
        $data['fields'] = FieldKeys::normalise($data['fields'] ?? []);

        $template = SectionTemplate::create($data);

        ActivityLog::record('Created section', $template->name, $template);

        return SectionTemplateResource::make($template->loadCount('sections'))
            ->response()->setStatusCode(201);
    }

    public function update(StoreSectionTemplateRequest $request, SectionTemplate $template): SectionTemplateResource
    {
        $data = $request->validated();
        $data['fields'] = FieldKeys::normalise($data['fields'] ?? []);

        $template->update($data);

        ActivityLog::record('Updated section', $template->name, $template);

        return SectionTemplateResource::make($template->fresh()->loadCount('sections'));
    }

    /** Pages keep their values; they simply lose the shared markup. */
    public function destroy(Request $request, SectionTemplate $template): JsonResponse
    {
        abort_unless($request->user()->hasPermission('pages.delete'), 403);

        $used = $template->sections()->count();
        $name = $template->name;

        $template->delete();

        ActivityLog::record('Deleted section', $name);

        return response()->json([
            'message' => $used > 0
                ? "Section deleted. {$used} page section(s) now have nothing to render."
                : 'Section deleted.',
        ]);
    }
}
