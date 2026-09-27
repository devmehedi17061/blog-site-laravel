<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\Post;
use App\Models\Revision;
use App\Support\RevisionView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * The saved versions of a post or page: listed, compared with the current
 * one, and brought back.
 */
class RevisionController extends Controller
{
    public function forPost(Post $post): JsonResponse
    {
        return $this->list($post);
    }

    public function forPage(Page $page): JsonResponse
    {
        return $this->list($page);
    }

    /** One version, next to the record as it is now, field by field. */
    public function show(Revision $revision): JsonResponse
    {
        $record = $this->recordOf($revision);
        $this->authorize('view', $record);

        return response()->json([
            'revision' => $this->summary($revision),
            'fields' => RevisionView::fields($record, $revision->data),
            'current' => RevisionView::fields($record, $record->revisionData()),
            'missing_sections' => $record instanceof Page ? Page::missingSectionsIn($revision->data) : 0,
        ]);
    }

    public function restore(Revision $revision): JsonResponse
    {
        $record = $this->recordOf($revision);
        $this->authorize('update', $record);

        $missing = $record instanceof Page ? Page::missingSectionsIn($revision->data) : 0;
        $when = $revision->created_at->format('d M Y, H:i');

        DB::transaction(function () use ($record, $revision, $when) {
            $record->ensureBaseline();
            $record->applyRevision($revision->data);
            $record->recordRevision("Restored the version from {$when}");
        });

        ActivityLog::record('Restored a version', "{$record->title} — {$when}", $record);

        $message = "Restored the version from {$when}.";
        if ($missing) {
            $message .= " {$missing} section(s) used a library section that no longer exists and were left out.";
        }

        return response()->json(['message' => $message]);
    }

    private function list(Model $record): JsonResponse
    {
        $this->authorize('view', $record);

        $revisions = $record->revisions()->with('user:id,name')->get();
        $currentHash = Revision::hashOf($record->revisionData());
        $currentFound = false;
        $rows = [];

        foreach ($revisions as $i => $revision) {
            $older = $revisions[$i + 1] ?? null;
            $isCurrent = ! $currentFound && $revision->hash === $currentHash;
            $currentFound = $currentFound || $isCurrent;

            $rows[] = $this->summary($revision) + [
                'changes' => $older ? RevisionView::changed($record, $older->data, $revision->data) : [],
                'is_first' => $older === null,
                'is_current' => $isCurrent,
            ];
        }

        return response()->json([
            'data' => $rows,
            'limit' => Post::REVISION_LIMIT,
            'can_restore' => request()->user()->can('update', $record),
        ]);
    }

    private function summary(Revision $revision): array
    {
        return [
            'id' => $revision->id,
            'label' => $revision->label,
            'user' => $revision->user?->name,
            'created_at' => $revision->created_at->toIso8601String(),
            'when' => $revision->created_at->format('d M Y, H:i'),
            'ago' => $revision->created_at->diffForHumans(),
        ];
    }

    /** A version of something now in the Trash cannot be opened: restore that first. */
    private function recordOf(Revision $revision): Model
    {
        $record = $revision->revisionable;
        abort_if($record === null, 404, 'This version belongs to something that is in the trash or was deleted.');

        return $record;
    }
}
