<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Discussion;
use App\Notifications\StudentNotice;
use App\Support\Lms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * LMS → Q&A: every lesson question across the courses this account
 * teaches, unanswered first, with a reply box — so nothing waits unseen.
 */
class DiscussionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Course::class);

        $data = $request->validate([
            'status' => ['nullable', 'in:Open,Answered,All'],
            'course_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $status = $data['status'] ?? 'Open';
        $search = trim((string) ($data['search'] ?? ''));

        $questions = $this->scoped($request)
            ->with(['author:id,name,email', 'course:id,title,slug', 'lesson:id,title', 'replies.author:id,name,role_id', 'replies.author.role:id,slug'])
            ->when($status === 'Open', fn ($q) => $q->where('is_resolved', false))
            ->when($status === 'Answered', fn ($q) => $q->where('is_resolved', true))
            ->when($data['course_id'] ?? null, fn ($q, $id) => $q->where('course_id', $id))
            ->when($search !== '', fn ($q) => $q->where('body', 'like', "%{$search}%"))
            ->orderBy('is_resolved')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return response()->json([
            'data' => $questions->getCollection()->map(fn (Discussion $d) => $this->row($d))->values(),
            'meta' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'from' => $questions->firstItem() ?? 0,
                'to' => $questions->lastItem() ?? 0,
                'total' => $questions->total(),
            ],
            'summary' => [
                'Open' => $this->scoped($request)->where('is_resolved', false)->count(),
                'Answered' => $this->scoped($request)->where('is_resolved', true)->count(),
            ],
            'courses' => Course::query()
                ->when($request->user()->role?->scope === 'Own content', fn ($q) => $q->where('user_id', $request->user()->id))
                ->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function reply(Request $request, Discussion $discussion): JsonResponse
    {
        $this->authorizeFor($request, $discussion);
        abort_if($discussion->parent_id !== null, 404);

        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:3000']]);

        Discussion::create([
            'course_id' => $discussion->course_id,
            'lesson_id' => $discussion->lesson_id,
            'user_id' => $request->user()->id,
            'parent_id' => $discussion->id,
            'body' => $data['body'],
        ]);
        $discussion->update(['is_resolved' => true]);

        if ($discussion->user_id !== $request->user()->id && $discussion->course) {
            Lms::notify($discussion->author, new StudentNotice(
                'Your instructor replied to your question',
                '“:question”: :reply',
                $discussion->lesson_id
                    ? route('learn.show', [$discussion->course->slug, $discussion->lesson_id]).'?tab=qa#qa-'.$discussion->id
                    : route('courses.show', $discussion->course->slug),
                'info',
                'Read the reply',
                params: ['question' => Str::limit($discussion->body, 80), 'reply' => Str::limit($data['body'], 160)],
            ));
        }

        return response()->json(['message' => 'Reply sent — the student has been notified.', 'data' => $this->row($discussion->fresh(['author', 'course', 'lesson', 'replies.author.role']))]);
    }

    public function resolve(Request $request, Discussion $discussion): JsonResponse
    {
        $this->authorizeFor($request, $discussion);
        $discussion->update(['is_resolved' => ! $discussion->is_resolved]);

        return response()->json(['message' => $discussion->is_resolved ? 'Marked as answered.' : 'Marked as open.']);
    }

    public function destroy(Request $request, Discussion $discussion): JsonResponse
    {
        $this->authorizeFor($request, $discussion);
        $discussion->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    private function authorizeFor(Request $request, Discussion $discussion): void
    {
        abort_unless($discussion->course && $request->user()->can('update', $discussion->course), 403);
    }

    /** Questions only, on the courses this account may edit. */
    private function scoped(Request $request): Builder
    {
        return Discussion::questions()->when(
            $request->user()->role?->scope === 'Own content',
            fn (Builder $q) => $q->whereHas('course', fn (Builder $c) => $c->where('user_id', $request->user()->id))
        );
    }

    private function row(Discussion $d): array
    {
        return [
            'id' => $d->id,
            'body' => $d->body,
            'resolved' => $d->is_resolved,
            'author' => $d->author?->name ?? 'Former student',
            'email' => $d->author?->email,
            'course' => $d->course?->title,
            'lesson' => $d->lesson?->title,
            'url' => $d->course && $d->lesson_id ? route('learn.show', [$d->course->slug, $d->lesson_id]).'?tab=qa#qa-'.$d->id : null,
            'ago' => $d->created_at?->diffForHumans(),
            'replies' => $d->replies->map(fn (Discussion $r) => [
                'id' => $r->id,
                'body' => $r->body,
                'author' => $r->author?->name ?? 'Former student',
                'staff' => $r->byStaff(),
                'ago' => $r->created_at?->diffForHumans(),
            ])->values(),
        ];
    }
}
