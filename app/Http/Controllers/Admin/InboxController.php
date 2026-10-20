<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\InboxRequest;
use App\Http\Resources\FormSubmissionResource;
use App\Models\ActivityLog;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Everything every form has been sent, in one place.
 *
 * The per-form Submissions tab answers "what did this form get"; the inbox
 * answers "what has come in", which is the question someone actually opens
 * the panel to ask. Unread is tracked per submission so the sidebar can say
 * how much is waiting.
 */
class InboxController extends Controller
{
    public function index(InboxRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Form::class);

        $submissions = FormSubmission::query()
            ->with(['form:id,name,slug', 'form.fields'])
            ->forForm($request->input('form'))
            ->state($request->input('state'))
            ->search($request->input('search'))
            // Newest first, which for an inbox is the only sensible default.
            // The id breaks ties: two submissions can share a second, and
            // without it their order changes between requests.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return FormSubmissionResource::collection($submissions)->additional([
            'summary' => $this->summary(),
        ]);
    }

    /** Opening one is what marks it read, the way a mail client behaves. */
    public function show(FormSubmission $submission): FormSubmissionResource
    {
        $this->authorize('viewAny', Form::class);

        $submission->markRead();

        return FormSubmissionResource::make($submission->load(['form:id,name,slug', 'form.fields']));
    }

    /**
     * A file a visitor uploaded, as a download for someone allowed to read
     * the inbox. Always an attachment and never sniffed, so a file that is
     * not what it claims cannot run in the browser. Older uploads that were
     * kept on the public disk are still found.
     */
    public function file(FormSubmission $submission, string $field): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', Form::class);

        $path = (string) (($submission->payload ?? [])[$field] ?? '');
        abort_if($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'form-uploads/'), 404);

        $disk = collect(['local', 'public'])->first(fn ($d) => \Illuminate\Support\Facades\Storage::disk($d)->exists($path));
        abort_unless($disk, 404);

        return \Illuminate\Support\Facades\Storage::disk($disk)->download($path, basename($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    /** Read or unread. Nothing else about a submission can be changed. */
    public function update(Request $request, FormSubmission $submission): FormSubmissionResource
    {
        $this->authorize('viewAny', Form::class);

        $request->validate(['unread' => ['required', 'boolean']]);

        $request->boolean('unread') ? $submission->markUnread() : $submission->markRead();

        return FormSubmissionResource::make($submission->fresh()->load(['form:id,name,slug', 'form.fields']));
    }

    public function destroy(FormSubmission $submission): JsonResponse
    {
        $this->authorize('delete', $submission->form ?? new Form);

        $name = $submission->form?->name ?? 'a deleted form';
        $submission->delete();

        ActivityLog::record('Deleted submission', $name);

        return response()->json(['message' => 'Submission deleted.', 'summary' => $this->summary()]);
    }

    /**
     * One action over many rows. Ids are filtered through the query rather
     * than trusted, so a hand-made list cannot reach a row that does not
     * exist — or, once forms grow their own scoping, one that is not ours.
     */
    public function bulk(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Form::class);

        $data = $request->validate([
            'action' => ['required', 'in:read,unread,delete'],
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $submissions = FormSubmission::whereIn('id', $data['ids'])->get();

        if ($data['action'] === 'delete') {
            $this->authorize('delete', $submissions->first()?->form ?? new Form);
            FormSubmission::whereIn('id', $submissions->pluck('id'))->delete();

            ActivityLog::record('Deleted submissions', $submissions->count().' from the inbox');
        } else {
            FormSubmission::whereIn('id', $submissions->pluck('id'))
                ->update(['read_at' => $data['action'] === 'read' ? now() : null]);
        }

        return response()->json([
            'message' => $submissions->count().' submission(s) updated.',
            'summary' => $this->summary(),
        ]);
    }

    /** Marks everything the current filter would show as read. */
    public function readAll(InboxRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Form::class);

        $changed = FormSubmission::query()
            ->forForm($request->input('form'))
            ->search($request->input('search'))
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => $changed === 1 ? '1 submission marked read.' : "{$changed} submissions marked read.",
            'summary' => $this->summary(),
        ]);
    }

    /**
     * The counts the inbox header and the sidebar badge need, plus one row
     * per form so the filter can show where the unread ones are.
     */
    private function summary(): array
    {
        $unreadByForm = FormSubmission::unread()
            ->selectRaw('form_id, COUNT(*) as total')
            ->groupBy('form_id')
            ->pluck('total', 'form_id');

        return [
            'total' => FormSubmission::count(),
            'unread' => (int) $unreadByForm->sum(),
            'forms' => Form::orderBy('name')->get(['id', 'name'])->map(fn (Form $form) => [
                'id' => $form->id,
                'name' => $form->name,
                'unread' => (int) ($unreadByForm[$form->id] ?? 0),
            ])->all(),
        ];
    }
}
