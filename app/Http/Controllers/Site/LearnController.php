<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Support\LessonVideos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The course player: one lesson at a time, the curriculum alongside, and a
 * button to mark it done. Free-preview lessons open for anyone; the rest
 * need an active enrollment (or the right to edit the course).
 */
class LearnController extends Controller
{
    public function show(Request $request, string $slug, ?int $lessonId = null): View|RedirectResponse
    {
        $course = CourseController::findForViewer($request, $slug);
        $course->load(['sections.lessons.attachment', 'instructor:id,name']);
        $lessons = $course->orderedLessons();

        if ($lessons->isEmpty()) {
            return redirect()->route('courses.show', $course->slug)
                ->with('status', __('This course has no lessons yet.'));
        }

        $user = $request->user();
        $enrollment = $user ? Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first() : null;
        $hasAccess = $this->hasAccess($user, $course, $enrollment);
        $done = $user
            ? LessonCompletion::where('user_id', $user->id)->where('course_id', $course->id)->pluck('lesson_id')->all()
            : [];

        // No lesson named: carry on from the first one not yet finished.
        $lesson = $lessonId
            ? $lessons->firstWhere('id', $lessonId)
            : ($lessons->first(fn (Lesson $l) => ! in_array($l->id, $done, true)) ?? $lessons->first());

        abort_if($lesson === null, 404);

        if (! $hasAccess && ! $lesson->is_preview) {
            if (! $user) {
                return redirect()->guest(route('student.login'))
                    ->with('status', __('Sign in to start :course.', ['course' => $course->title]));
            }

            return redirect()->route('courses.show', $course->slug)
                ->with('status', $enrollment?->status === 'Pending'
                    ? __('Your payment is being checked — the course opens as soon as it is confirmed.')
                    : __('Enroll to open this lesson.'));
        }

        // Lessons in order: one still waiting on an earlier lesson sends the
        // student to the lesson they have to finish first.
        $locked = $this->lockedFor($user, $course, $done);
        if (in_array($lesson->id, $locked, true)) {
            $due = $lessons->first(fn (Lesson $l) => ! in_array($l->id, $done, true) && ! in_array($l->id, $locked, true));

            return redirect()->route('learn.show', [$course->slug, $due?->id ?? $lessons->first()->id])
                ->with('status', __('Lessons open in order — complete “:lesson” to unlock the next one.', ['lesson' => $due?->title ?? __('this lesson')]));
        }

        // Where they are, for "continue where you left off" in their panel.
        if ($enrollment?->isActive()) {
            $enrollment->forceFill(['last_lesson_id' => $lesson->id, 'last_activity_at' => now()])->saveQuietly();
        }

        $index = $lessons->search(fn (Lesson $l) => $l->id === $lesson->id);

        // A quiz: its questions (shuffled per try, if set), the tries so
        // far, and the one just submitted.
        $quiz = null;
        if ($lesson->isQuiz()) {
            $attempts = $user ? QuizAttempt::where('user_id', $user->id)->where('lesson_id', $lesson->id)->latest()->get() : collect();
            $questions = $lesson->questions()->get();
            if ($lesson->shuffle_questions) {
                $questions = $questions->shuffle(crc32(($user?->id ?? 0).'-'.$attempts->count()));
            }
            $justTaken = session('quiz_attempt') ? $attempts->firstWhere('id', session('quiz_attempt')) : null;
            $passed = $attempts->contains('passed', true);
            $left = $lesson->max_attempts > 0 ? max(0, $lesson->max_attempts - $attempts->count()) : null;

            $quiz = [
                'questions' => $questions,
                'attempts' => $attempts,
                'result' => $justTaken,
                'best' => $attempts->max('score'),
                'passed' => $passed,
                'left' => $left,
                // Right answers are shown once they can no longer help guess:
                // after a pass, or when no tries are left.
                'reveal' => $passed || $left === 0,
            ];
        }

        // Questions under this lesson, newest first, with their replies.
        $discussions = Discussion::questions()
            ->where('lesson_id', $lesson->id)
            ->with(['author:id,name,role_id', 'author.role:id,slug', 'replies.author:id,name,role_id', 'replies.author.role:id,slug'])
            ->latest()
            ->limit(50)
            ->get();

        return view('frontend.learn.show', [
            'locked' => $locked,
            'quiz' => $quiz,
            'discussions' => $discussions,
            'canDiscuss' => $hasAccess && $user !== null,
            'course' => $course,
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            'hasAccess' => $hasAccess,
            'done' => $done,
            'previous' => $index > 0 ? $lessons[$index - 1] : null,
            'next' => $lessons[$index + 1] ?? null,
            'progress' => $lessons->count() ? (int) round(count(array_intersect($done, $lessons->pluck('id')->all())) / $lessons->count() * 100) : 0,
            'position' => $index + 1,
            'total' => $lessons->count(),
            'doneCount' => count(array_intersect($done, $lessons->pluck('id')->all())),
            'minutesLeft' => (int) $lessons->reject(fn (Lesson $l) => in_array($l->id, $done, true))->sum('duration_minutes'),
            // The player is a focused screen: the site header stays for
            // the way out, the footer would only push the lesson away.
            'showFooter' => false,
        ]);
    }

    /**
     * An uploaded lesson video, streamed to someone allowed to watch it: an
     * active student, staff who can edit the course, or anyone for a free
     * preview. There is no other way to reach the file — it sits on the
     * private "lessons" disk. Range requests are answered, so the player
     * can seek and the browser never has to load the whole file at once.
     */
    public function video(Request $request, string $slug, int $lessonId): BinaryFileResponse
    {
        $course = CourseController::findForViewer($request, $slug);
        $lesson = Lesson::where('course_id', $course->id)->findOrFail($lessonId);

        abort_unless($lesson->hasUpload() && LessonVideos::disk()->exists($lesson->video_path), 404);

        $user = $request->user();
        $enrollment = $user ? Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first() : null;
        abort_unless($lesson->is_preview || $this->hasAccess($user, $course, $enrollment), 403);
        abort_if(in_array($lesson->id, $this->lockedFor($user, $course, $this->doneBy($user, $course)), true), 403,
            __('Complete the earlier lessons first.'));

        return response()->file(LessonVideos::disk()->path($lesson->video_path), [
            'Content-Type' => LessonVideos::mime($lesson->video_path),
            // Not kept by shared caches, and not offered as a download.
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /** "Mark as complete" — and, pressed again, "mark as not complete". */
    public function complete(Request $request, string $slug, int $lessonId): RedirectResponse|JsonResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();
        $lesson = Lesson::where('course_id', $course->id)->findOrFail($lessonId);
        $user = $request->user();
        $enrollment = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first();

        abort_unless($enrollment?->isActive(), 403, __('Enroll in the course to track your progress.'));

        // A quiz is complete only once it is passed (QuizController).
        abort_if($lesson->isQuiz(), 403, __('Pass the quiz to complete this lesson.'));

        // A lesson still locked cannot be ticked off from outside the page.
        if (! $request->boolean('undo') && in_array($lesson->id, $this->lockedFor($user, $course, $this->doneBy($user, $course)), true)) {
            return redirect()->route('learn.show', $course->slug)
                ->with('status', __('Lessons open in order — finish the earlier ones first.'));
        }

        $existing = LessonCompletion::where('user_id', $user->id)->where('lesson_id', $lesson->id)->first();

        if ($existing && $request->boolean('undo')) {
            $existing->delete();
        } elseif (! $existing) {
            LessonCompletion::create(['user_id' => $user->id, 'lesson_id' => $lesson->id, 'course_id' => $course->id]);
        }

        $wasComplete = $enrollment->completed_at !== null;
        $enrollment->syncCompletion();

        // The player marks a watched video done in the background, without
        // leaving the page: it gets the new progress back instead.
        if ($request->expectsJson()) {
            $total = $course->lessons()->count();
            $done = LessonCompletion::where('user_id', $user->id)->where('course_id', $course->id)->count();

            return response()->json([
                'done' => ! $request->boolean('undo'),
                'progress' => $total ? (int) min(100, round($done / $total * 100)) : 0,
                'done_count' => $done,
                'total' => $total,
                'course_complete' => $enrollment->completed_at !== null,
                'just_finished' => ! $wasComplete && $enrollment->completed_at !== null,
                'certificate_url' => $enrollment->completed_at ? route('student.certificate', $enrollment) : null,
            ]);
        }

        if (! $wasComplete && $enrollment->completed_at) {
            return redirect()->route('learn.show', [$course->slug, $lesson->id])
                ->with('status', __('Course complete — congratulations! Your certificate is ready.'))
                ->with('celebrate', true);
        }

        // Done: on to the next lesson. Undone: stay here.
        if (! $request->boolean('undo') && $request->input('next')) {
            return redirect()->route('learn.show', [$course->slug, (int) $request->input('next')]);
        }

        return redirect()->route('learn.show', [$course->slug, $lesson->id]);
    }

    /**
     * Lessons this person must not open yet (Settings → LMS → Lessons in
     * order). Staff who can edit the course are never held back.
     *
     * @param  array<int, int>  $done
     * @return array<int, int>
     */
    private function lockedFor(?User $user, Course $course, array $done): array
    {
        if (! $user || $user->can('update', $course)) {
            return [];
        }

        return $course->lockedLessonIds($done);
    }

    /** @return array<int, int> */
    private function doneBy(?User $user, Course $course): array
    {
        return $user
            ? LessonCompletion::where('user_id', $user->id)->where('course_id', $course->id)->pluck('lesson_id')->all()
            : [];
    }

    private function hasAccess(?User $user, Course $course, ?Enrollment $enrollment): bool
    {
        return (bool) ($enrollment?->isActive() || $user?->can('update', $course));
    }
}
