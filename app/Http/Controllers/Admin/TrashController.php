<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Support\Trash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Posts, pages and files that were deleted: listed here until they are
 * restored, deleted for good, or have waited out Trash::DAYS.
 */
class TrashController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(Trash::TYPES))],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
        ]);

        $type = $data['type'];
        $model = Trash::model($type);
        $this->authorize('viewAny', $model);

        // No scheduler runs on many hosts, so the Trash also tidies itself
        // whenever someone looks at it.
        Trash::purge();

        $search = trim((string) ($data['search'] ?? ''));
        $column = $type === 'media' ? 'name' : 'title';

        $items = $model::onlyTrashed()
            ->with('deletedBy:id,name')
            ->when($search !== '', fn ($q) => $q->where($column, 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->latest('deleted_at')
            ->paginate($data['per_page'] ?? 25);

        return response()->json([
            'data' => $items->getCollection()->map(fn (Model $item) => $this->row($request, $type, $item))->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'from' => $items->firstItem() ?? 0,
                'to' => $items->lastItem() ?? 0,
                'total' => $items->total(),
            ],
            'counts' => $this->counts($request),
            'days' => Trash::DAYS,
        ]);
    }

    public function restore(Request $request, string $type): JsonResponse
    {
        $items = $this->selected($request, $type);

        foreach ($items as $item) {
            $this->authorize('restore', $item);
        }

        foreach ($items as $item) {
            $item->restore();
            ActivityLog::record('Restored from trash', $this->title($type, $item), $item);
        }

        $n = $items->count();

        return response()->json([
            'message' => $n === 1 ? '"'.$this->title($type, $items->first()).'" is back.' : "{$n} items restored.",
            'counts' => $this->counts($request),
        ]);
    }

    public function destroy(Request $request, string $type): JsonResponse
    {
        $items = $this->selected($request, $type);

        foreach ($items as $item) {
            $this->authorize('forceDelete', $item);
        }

        $n = $this->forceDelete($type, $items);

        return response()->json([
            'message' => $n === 1 ? 'Deleted for good.' : "{$n} items deleted for good.",
            'counts' => $this->counts($request),
        ]);
    }

    /** Everything in this tab that the signed-in account may delete. */
    public function empty(Request $request, string $type): JsonResponse
    {
        $model = Trash::model($type);
        $this->authorize('viewAny', $model);

        $items = $model::onlyTrashed()->get()->filter(fn (Model $item) => $request->user()->can('forceDelete', $item));
        $n = $this->forceDelete($type, $items);
        $left = $model::onlyTrashed()->count();

        return response()->json([
            'message' => $left
                ? "{$n} deleted for good. {$left} belong to other people and were left for them."
                : ($n === 1 ? '1 item deleted for good.' : "{$n} items deleted for good."),
            'counts' => $this->counts($request),
        ]);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function selected(Request $request, string $type)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $items = Trash::model($type)::onlyTrashed()->whereIn('id', $data['ids'])->get();

        abort_if($items->isEmpty(), 404, 'Those items are no longer in the trash.');

        return $items;
    }

    private function forceDelete(string $type, $items): int
    {
        foreach ($items as $item) {
            $title = $this->title($type, $item);
            $item->forceDelete();
            ActivityLog::record('Deleted for good', $title);
        }

        return $items->count();
    }

    /** How many items wait in each tab this account can see. */
    private function counts(Request $request): array
    {
        $counts = [];

        foreach (Trash::TYPES as $key => $type) {
            if ($request->user()->can('viewAny', $type['model'])) {
                $counts[$key] = $type['model']::onlyTrashed()->count();
            }
        }

        return $counts;
    }

    private function title(string $type, Model $item): string
    {
        return (string) ($type === 'media' ? $item->name : $item->title);
    }

    private function row(Request $request, string $type, Model $item): array
    {
        $details = match (true) {
            $item instanceof Post => array_filter([$item->status, $item->category?->name]),
            $item instanceof Page => [$item->status, $item->slug],
            $item instanceof Media => array_filter([ucfirst((string) $item->kind), $item->human_size]),
        };

        return [
            'id' => $item->id,
            'title' => $this->title($type, $item) ?: 'Untitled',
            'details' => implode(' · ', $details),
            'thumb' => $item instanceof Media && $item->kind === 'image' ? $item->url : null,
            'deleted_at' => $item->deleted_at->toIso8601String(),
            'deleted_on' => $item->deleted_at->format('d M Y, H:i'),
            'deleted_by' => $item->deletedBy?->name,
            'days_left' => Trash::daysLeft($item),
            'can' => [
                'restore' => $request->user()->can('restore', $item),
                'delete' => $request->user()->can('forceDelete', $item),
            ],
        ];
    }
}
