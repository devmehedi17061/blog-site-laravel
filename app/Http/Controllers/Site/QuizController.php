<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Taking a quiz lesson. Answers are graded here, never in the browser; a
 * pass marks the lesson complete (which is the only way a quiz lesson
 * becomes complete), and the attempt limit is enforced here too.
 */
class QuizController extends Controller
{
    public function submit(Request $request, string $slug, int $lessonId): RedirectResponse
    {
        $course = Course::where('slug', $slug)->firstOrFail();
        $lesson = Lesson::where('course_id', $course->id)->with('questions')->findOrFail($lessonId);
        abort_unless($lesson->isQuiz(), 404);

        $user = $request->user();
        $enrollment = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first();
        $back = redirect()->route('learn.show', [$course->slug, $lesson->id]);

        // Staff who can edit the course can try it, but nothing is recorded.
        $preview = ! $enrollment?->isActive() && $user->can('update', $course);
        abort_unless($enrollment?->isActive() || $preview, 403, __('Enroll in the course to take its quizzes.'));

        if (! $preview && in_array($lesson->id, $course->lockedLessonIds($this->done($user->id, $course->id)), true)) {
            return redirect()->route('learn.show', $course->slug)->with('status', __('Lessons open in order — finish the earlier ones first.'));
        }

        $attempts = QuizAttempt::where('user_id', $user->id)->where('lesson_id', $lesson->id)->count();
        if (! $preview && $lesson->max_attempts > 0 && $attempts >= $lesson->max_attempts) {
            return $back->with('status', __('You have used all :n attempts on this quiz.', ['n' => $lesson->max_attempts]));
        }

        abort_if($lesson->questions->isEmpty(), 422, __('This quiz has no questions yet.'));

        $request->validate(['answers' => ['nullable', 'array']]);
        $answers = (array) $request->input('answers', []);

        // Every question must be answered: an empty answer is usually a slip.
        $missing = $lesson->questions->filter(fn (QuizQuestion $q) => empty($answers[$q->id]))->count();
        if ($missing > 0) {
            return $back->withInput()->with('quiz_error', $missing === 1 ? __('Answer the last question too.') : __('Answer all the questions — :n are still empty.', ['n' => $missing]));
        }

        $earned = 0;
        $possible = 0;
        $chosen = [];
        foreach ($lesson->questions as $question) {
            $picked = array_values(array_map('intval', (array) $answers[$question->id]));
            $chosen[$question->id] = $picked;
            $possible += $question->points;
            $earned += $question->isRight($picked) ? $question->points : 0;
        }

        $score = $possible ? (int) round($earned / $possible * 100) : 0;
        $passed = $score >= $lesson->pass_mark;

        if ($preview) {
            return $back->with('quiz_preview', compact('score', 'passed', 'chosen'));
        }

        $attempt = QuizAttempt::create([
            'user_id' => $user->id, 'lesson_id' => $lesson->id,
            'score' => $score, 'earned' => $earned, 'possible' => $possible,
            'passed' => $passed, 'answers' => $chosen,
        ]);

        if ($passed) {
            LessonCompletion::firstOrCreate(['user_id' => $user->id, 'lesson_id' => $lesson->id], ['course_id' => $course->id]);
            $wasComplete = $enrollment->completed_at !== null;
            $enrollment->syncCompletion();

            if (! $wasComplete && $enrollment->completed_at) {
                return $back->with('quiz_attempt', $attempt->id)
                    ->with('status', __('Course complete — congratulations! Your certificate is ready.'))
                    ->with('celebrate', true);
            }
        }

        return $back->with('quiz_attempt', $attempt->id);
    }

    /** @return array<int, int> */
    private function done(int $userId, int $courseId): array
    {
        return LessonCompletion::where('user_id', $userId)->where('course_id', $courseId)->pluck('lesson_id')->all();
    }
}
