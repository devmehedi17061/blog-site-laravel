<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Payment;
use App\Models\QuizAttempt;
use App\Support\Lms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * LMS → Reports: how the school is doing. Money and sign-ups over the last
 * twelve months, each course's numbers, the quiz questions students get
 * wrong most, and which coupons earned their keep. An "Own content" role
 * (Instructor) sees its own courses only.
 *
 * Grouping happens here rather than in SQL, so it reads the same on MySQL
 * and on the SQLite the tests use.
 */
class ReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Course::class);

        $courses = Course::query()
            ->when($request->user()->role?->scope === 'Own content', fn ($q) => $q->where('user_id', $request->user()->id))
            ->get(['id', 'title', 'slug', 'status']);
        $ids = $courses->pluck('id');

        $from = now()->startOfMonth()->subMonths(11);
        $verified = Payment::whereIn('course_id', $ids)->where('status', 'Verified')->get(['course_id', 'amount', 'discount', 'coupon_id', 'method', 'created_at']);
        $enrollments = Enrollment::whereIn('course_id', $ids)->get(['user_id', 'course_id', 'status', 'completed_at', 'created_at']);

        $thisMonth = $verified->filter(fn ($p) => $p->created_at >= now()->startOfMonth())->sum('amount');
        $lastMonth = $verified->filter(fn ($p) => $p->created_at >= now()->subMonth()->startOfMonth() && $p->created_at < now()->startOfMonth())->sum('amount');
        $active = $enrollments->where('status', 'Active');

        return response()->json([
            'currency' => Lms::setting('currency'),
            'totals' => [
                'revenue' => Lms::money($verified->sum('amount')),
                'revenue_month' => Lms::money($thisMonth),
                'revenue_change' => $lastMonth > 0 ? (int) round(($thisMonth - $lastMonth) / $lastMonth * 100) : null,
                'students' => $active->unique('user_id')->count(),
                'enrollments_month' => $enrollments->filter(fn ($e) => $e->created_at >= now()->startOfMonth())->count(),
                'completion' => $active->count() ? (int) round($active->whereNotNull('completed_at')->count() / $active->count() * 100) : 0,
                'discounts' => Lms::money($verified->sum('discount')),
            ],
            'months' => $this->months($from, $verified, $enrollments),
            'courses' => $this->courses($courses, $verified, $enrollments),
            'methods' => $verified->groupBy('method')->map(fn ($g, $method) => [
                'method' => $method,
                'count' => $g->count(),
                'amount' => (float) $g->sum('amount'),
                'label' => Lms::money($g->sum('amount')),
            ])->sortByDesc('amount')->values(),
            // Coupons are site-wide: not for an instructor's own report.
            'coupons' => $request->user()->role?->scope === 'Own content' ? [] : $this->coupons($ids, $verified),
            'quizzes' => $this->quizzes($ids),
        ]);
    }

    /** Revenue and new enrollments for each of the last twelve months. */
    private function months(Carbon $from, Collection $verified, Collection $enrollments): array
    {
        $revenue = $verified->filter(fn ($p) => $p->created_at >= $from)->groupBy(fn ($p) => $p->created_at->format('Y-m'));
        $joined = $enrollments->filter(fn ($e) => $e->created_at >= $from)->groupBy(fn ($e) => $e->created_at->format('Y-m'));

        return collect(range(11, 0))->map(function ($ago) use ($revenue, $joined) {
            $month = now()->startOfMonth()->subMonths($ago);
            $key = $month->format('Y-m');

            return [
                'key' => $key,
                'label' => $month->format('M'),
                'long' => $month->format('F Y'),
                'revenue' => (float) ($revenue[$key] ?? collect())->sum('amount'),
                'revenue_label' => Lms::money(($revenue[$key] ?? collect())->sum('amount')),
                'enrollments' => ($joined[$key] ?? collect())->count(),
            ];
        })->values()->all();
    }

    private function courses(Collection $courses, Collection $verified, Collection $enrollments): array
    {
        $lessons = Lesson::whereIn('course_id', $courses->pluck('id'))->selectRaw('course_id, count(*) as n')->groupBy('course_id')->pluck('n', 'course_id');
        $done = LessonCompletion::whereIn('course_id', $courses->pluck('id'))->selectRaw('course_id, count(*) as n')->groupBy('course_id')->pluck('n', 'course_id');
        $ratings = CourseReview::whereIn('course_id', $courses->pluck('id'))->where('is_approved', true)
            ->selectRaw('course_id, avg(rating) as r, count(*) as n')->groupBy('course_id')->get()->keyBy('course_id');

        return $courses->map(function (Course $course) use ($verified, $enrollments, $lessons, $done, $ratings) {
            $active = $enrollments->where('course_id', $course->id)->where('status', 'Active');
            $students = $active->count();
            $possible = $students * (int) ($lessons[$course->id] ?? 0);

            return [
                'id' => $course->id,
                'title' => $course->title,
                'status' => $course->status,
                'students' => $students,
                'revenue' => (float) $verified->where('course_id', $course->id)->sum('amount'),
                'revenue_label' => Lms::money($verified->where('course_id', $course->id)->sum('amount')),
                'completion' => $students ? (int) round($active->whereNotNull('completed_at')->count() / $students * 100) : 0,
                // Lessons finished out of all the lessons all its students could finish.
                'progress' => $possible ? (int) min(100, round(($done[$course->id] ?? 0) / $possible * 100)) : 0,
                'rating' => isset($ratings[$course->id]) ? round((float) $ratings[$course->id]->r, 1) : null,
                'reviews' => isset($ratings[$course->id]) ? (int) $ratings[$course->id]->n : 0,
            ];
        })->sortByDesc('revenue')->values()->all();
    }

    private function coupons(Collection $courseIds, Collection $verified): array
    {
        $used = $verified->whereNotNull('coupon_id')->groupBy('coupon_id');

        return Coupon::withCount('redemptions')->withSum('redemptions', 'discount')
            ->whereHas('redemptions')
            ->orderByDesc('redemptions_count')->limit(10)->get()
            ->map(fn (Coupon $c) => [
                'code' => $c->code,
                'label' => $c->label(),
                'uses' => (int) $c->redemptions_count,
                'discount' => Lms::money((float) $c->redemptions_sum_discount),
                // What students paid when they used it (verified payments only).
                'revenue' => Lms::money(($used[$c->id] ?? collect())->sum('amount')),
            ])->values()->all();
    }

    /**
     * Each quiz's pass rate and average score, and the questions most often
     * answered wrong — usually the lesson before them needs another look.
     */
    private function quizzes(Collection $courseIds): array
    {
        $quizzes = Lesson::whereIn('course_id', $courseIds)->where('type', 'quiz')->with(['questions', 'course:id,title'])->get();
        $attempts = QuizAttempt::whereIn('lesson_id', $quizzes->pluck('id'))->get(['lesson_id', 'score', 'passed', 'answers'])->groupBy('lesson_id');

        $summary = [];
        $hardest = [];

        foreach ($quizzes as $quiz) {
            $tries = $attempts[$quiz->id] ?? collect();

            $summary[] = [
                'title' => $quiz->title,
                'course' => $quiz->course?->title,
                'attempts' => $tries->count(),
                'pass_rate' => $tries->count() ? (int) round($tries->where('passed', true)->count() / $tries->count() * 100) : null,
                'average' => $tries->count() ? (int) round($tries->avg('score')) : null,
                'pass_mark' => $quiz->pass_mark,
            ];

            foreach ($quiz->questions as $question) {
                $answered = $tries->filter(fn ($a) => isset($a->answers[$question->id]));
                if ($answered->count() < 1) {
                    continue;
                }
                $wrong = $answered->reject(fn ($a) => $question->isRight((array) $a->answers[$question->id]))->count();

                $hardest[] = [
                    'question' => $question->question,
                    'quiz' => $quiz->title,
                    'answered' => $answered->count(),
                    'wrong' => (int) round($wrong / $answered->count() * 100),
                ];
            }
        }

        return [
            'summary' => $summary,
            'hardest' => collect($hardest)->sortByDesc('wrong')->take(8)->values()->all(),
        ];
    }
}
