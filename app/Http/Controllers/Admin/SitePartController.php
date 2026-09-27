<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SitePart;
use App\Support\FieldKeys;
use App\Support\SitePartRenderer;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Builders → Header & footer: a library of headers and footers built like
 * sections — HTML, CSS, fields — with one of each in use on the site.
 */
class SitePartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->allow($request, 'layout.view');

        return response()->json([
            'data' => SitePart::orderBy('kind')->orderByDesc('is_active')->orderBy('name')->get()->map(fn ($p) => $this->row($p)),
            'tokens' => collect(SitePartRenderer::TOKENS)->map(fn ($t, $key) => ['key' => $key] + $t)->values(),
            'can' => [
                'create' => $request->user()->hasPermission('layout.create'),
                'update' => $request->user()->hasPermission('layout.update'),
                'delete' => $request->user()->hasPermission('layout.delete'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow($request, 'layout.create');

        $data = $this->validated($request, true);
        $data['slug'] = Slug::unique(SitePart::class, $data['kind'].'-'.$data['name']);
        $data['user_id'] = $request->user()->id;

        $part = SitePart::create($data);

        // The first of its kind is used straight away; there is nothing else.
        if (! SitePart::where('kind', $part->kind)->where('is_active', true)->exists()) {
            $part->activate();
        }

        ActivityLog::record('Created '.$part->kind, $part->name, $part);

        return response()->json(['data' => $this->row($part->fresh())], 201);
    }

    public function update(Request $request, SitePart $part): JsonResponse
    {
        $this->allow($request, 'layout.update');

        $part->update($this->validated($request, false));

        ActivityLog::record('Updated '.$part->kind, $part->name, $part);

        return response()->json(['data' => $this->row($part->fresh())]);
    }

    /** Puts it on the site; the one in use before goes back to the library. */
    public function activate(Request $request, SitePart $part): JsonResponse
    {
        $this->allow($request, 'layout.update');

        $part->activate();

        ActivityLog::record('Put '.$part->kind.' on the site', $part->name, $part);

        return response()->json(['message' => "“{$part->name}” is now the site's {$part->kind}."]);
    }

    public function destroy(Request $request, SitePart $part): JsonResponse
    {
        $this->allow($request, 'layout.delete');

        abort_if($part->is_active, 422, "“{$part->name}” is the {$part->kind} in use. Put another {$part->kind} on the site first.");

        $part->delete();

        ActivityLog::record('Deleted '.$part->kind, $part->name);

        return response()->json(['message' => ucfirst($part->kind).' deleted.']);
    }

    /**
     * The site with this draft in place: the real layout, real menus and
     * real logo, around a stand-in page. Nothing is saved.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->allow($request, 'layout.view');

        $draft = new SitePart($this->validated($request, true));

        View::share('sitePartDraft', [$draft->kind => $draft]);

        return response()->json(['html' => view('frontend.site-part-preview', ['previewKind' => $draft->kind])->render()]);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'kind' => [$creating ? 'required' : 'prohibited', 'in:'.implode(',', SitePart::KINDS)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'html' => ['nullable', 'string', 'max:100000'],
            'css' => ['nullable', 'string', 'max:100000'],
            'fields' => ['nullable', 'array', 'max:40'],
            'values' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'settings.sticky' => ['nullable', 'boolean'],
            'settings.caret' => ['nullable', 'in:'.implode(',', SitePart::CARETS)],
        ]);

        $data['fields'] = FieldKeys::normalise($data['fields'] ?? []);
        $data['values'] = $data['values'] ?? [];
        $data['settings'] = [
            'sticky' => (bool) ($data['settings']['sticky'] ?? false),
            'caret' => $data['settings']['caret'] ?? 'triangle',
        ];

        return $data;
    }

    private function row(SitePart $part): array
    {
        return [
            'id' => $part->id,
            'kind' => $part->kind,
            'name' => $part->name,
            'description' => $part->description,
            'html' => (string) $part->html,
            'css' => (string) $part->css,
            'fields' => $part->fields ?? [],
            'values' => (object) ($part->values ?? []),
            'settings' => ['sticky' => (bool) ($part->settings['sticky'] ?? false), 'caret' => $part->caret()],
            'is_active' => $part->is_active,
            'updated' => $part->updated_at?->diffForHumans(),
        ];
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403, 'Your role cannot do that here.');
    }
}
