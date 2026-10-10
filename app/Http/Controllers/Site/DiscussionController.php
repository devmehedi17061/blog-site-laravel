<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\StudentNotice;
use App\Support\Lms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Lesson Q&A on the player's "Q&A" tab: students on the course ask, anyone
 * on the course or teaching it replies. Whoever asked hears about replies.
 */
class DiscussionController extends Controller
{
    public function store(Request $request, string $slug, int $lessonId): RedirectResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();
        $lesson = Lesson::where('course_id', $course->id)->findOrFail($lessonId);
        $user = $request->user();
        abort_unless($this->mayTake($user, $course), 403, __('Enroll in the course to join its Q&A.'));

        $data = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:3000'],
            'parent_id' => ['nullable', 'integer'],
        ], ['body.required' => __('Write something first.')]);

        $parent = isset($data['parent_id'])
            ? Discussion::questions()->where('lesson_id', $lesson->id)->findOrFail($data['parent_id'])
            : null;

        Discussion::create([
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'parent_id' => $parent?->id,
            'body' => $data['body'],
        ]);

        // A reply from someone who teaches answers the question.
        if ($parent && $user->can('update', $course)) {
            $parent->update(['is_resolved' => true]);
        }

        if ($parent && $parent->user_id !== $user->id) {
            Lms::notify($parent->author, new StudentNotice(
                $user->can('update', $course) ? 'Your instructor replied to your question' : ':name replied to your question',
                '“:question” — :lesson: :reply',
                route('learn.show', [$course->slug, $lesson->id]).'#qa-'.$parent->id,
                'info',
                'Read the reply',
                params: ['name' => $user->name, 'question' => Str::limit($parent->body, 80), 'lesson' => $lesson->title, 'reply' => Str::limit($data['body'], 160)],
            ));
        }

        return redirect()->to(route('learn.show', [$course->slug, $lesson->id]).'?tab=qa#qa-'.($parent?->id ?? ''))
            ->with('status', $parent ? __('Reply posted.') : __('Question posted — you will be notified when someone replies.'));
    }

    /** The asker (or staff) marks a question answered, or opens it again. */
    public function resolve(Request $request, Discussion $discussion): RedirectResponse
    {
        abort_if($discussion->parent_id !== null, 404);
        abort_unless($discussion->user_id === $request->user()->id || $request->user()->can('update', $discussion->course), 403);

        $discussion->update(['is_resolved' => ! $discussion->is_resolved]);

        return back();
    }

    /** Your own post — or, for staff, any post on their course. */
    public function destroy(Request $request, Discussion $discussion): RedirectResponse
    {
        abort_unless($discussion->user_id === $request->user()->id || $request->user()->can('update', $discussion->course), 403);
        $discussion->delete();

        return back()->with('status', __('Deleted.'));
    }

    private function mayTake(User $user, Course $course): bool
    {
        return $user->can('update', $course)
            || (bool) Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->where('status', 'Active')->exists();
    }
}
