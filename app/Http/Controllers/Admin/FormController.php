<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Forms\SaveFields;
use App\Http\Controllers\Controller;
use App\Http\Requests\FormIndexRequest;
use App\Http\Requests\StoreFormRequest;
use App\Http\Resources\FormResource;
use App\Models\ActivityLog;
use App\Models\Form;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FormController extends Controller
{
    public function __construct(private readonly SaveFields $saveFields) {}

    public function index(FormIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Form::class);

        $forms = Form::query()
            ->withCount(['fields', 'submissions'])
            ->search($request->input('search'))
            ->status($request->input('status'))
            ->sortBy($request->input('sort'), $request->input('direction'))
            ->paginate($request->perPage())
            ->withQueryString();

        return FormResource::collection($forms);
    }

    public function show(Form $form): FormResource
    {
        $this->authorize('view', $form);

        return FormResource::make($form->load('fields')->loadCount(['fields', 'submissions']));
    }

    public function store(StoreFormRequest $request): JsonResponse
    {
        $data = $request->validated();
        $fields = $data['fields'] ?? [];
        unset($data['fields']);

        $data['slug'] = $this->formSlug($data);

        $form = Form::create($data);
        $this->saveFields->handle($form, $fields);

        ActivityLog::record('Created form', $form->name, $form);

        return FormResource::make($form->fresh()->load('fields')->loadCount(['fields', 'submissions']))
            ->response()->setStatusCode(201);
    }

    public function update(StoreFormRequest $request, Form $form): FormResource
    {
        $data = $request->validated();

        // Sent only by the builder's Fields tab. A settings-only save must
        // not be read as "this form now has no fields".
        $fields = $data['fields'] ?? null;
        unset($data['fields']);

        $data['slug'] = $this->formSlug($data, $form);

        $form->update($data);

        if (is_array($fields)) {
            $this->saveFields->handle($form, $fields);
        }

        ActivityLog::record('Updated form', $form->name, $form);

        return FormResource::make($form->fresh()->load('fields')->loadCount(['fields', 'submissions']));
    }

    public function destroy(Form $form): JsonResponse
    {
        $this->authorize('delete', $form);

        $name = $form->name;
        $submissions = $form->submissions()->count();

        // The fields and submissions go with it — the foreign keys cascade.
        $form->delete();

        ActivityLog::record('Deleted form', $name);

        return response()->json([
            'message' => $submissions > 0
                ? "Form deleted, along with {$submissions} submission(s)."
                : 'Form deleted.',
        ]);
    }

    /**
     * The slug is the form's public address, so it is derived from the name
     * when nothing was typed and kept unique either way.
     */
    private function formSlug(array $data, ?Form $form = null): string
    {
        $source = trim((string) ($data['slug'] ?? '')) ?: ($data['name'] ?? $form?->name ?? 'form');

        return Slug::unique(Form::class, $source, $form?->id);
    }
}
