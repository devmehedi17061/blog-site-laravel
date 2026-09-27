<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormSubmissionResource;
use App\Models\ActivityLog;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What visitors sent. Read and delete only — a submission is a record of
 * something that happened, so there is nothing here to edit.
 */
class FormSubmissionController extends Controller
{
    public function index(Request $request, Form $form): AnonymousResourceCollection
    {
        $this->authorize('view', $form);

        $submissions = $form->submissions()
            ->with('form.fields')
            ->paginate((int) ($request->query('per_page') ?: 20))
            ->withQueryString();

        return FormSubmissionResource::collection($submissions);
    }

    public function destroy(Form $form, FormSubmission $submission): JsonResponse
    {
        $this->authorize('delete', $form);
        abort_unless($submission->form_id === $form->id, 404);

        $submission->delete();

        ActivityLog::record('Deleted submission', $form->name, $form);

        return response()->json(['message' => 'Submission deleted.']);
    }

    /**
     * A CSV of everything, built row by row rather than in memory, because a
     * busy form is exactly the one someone wants to export.
     */
    public function export(Form $form): StreamedResponse
    {
        $this->authorize('view', $form);

        $form->loadMissing('fields');
        $columns = $form->fields->pluck('label', 'name');

        $filename = $form->slug.'-submissions-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($form, $columns) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Received', 'IP', ...$columns->values()->all()]);

            $form->submissions()->chunk(200, function ($chunk) use ($handle, $columns) {
                foreach ($chunk as $submission) {
                    $row = [
                        $submission->created_at?->toDateTimeString(),
                        $submission->ip_address,
                    ];

                    foreach ($columns->keys() as $name) {
                        $value = $submission->payload[$name] ?? '';
                        $row[] = is_array($value) ? implode(', ', $value) : (string) $value;
                    }

                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
