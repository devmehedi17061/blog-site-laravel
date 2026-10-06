<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use App\Notifications\StudentNotice;
use App\Support\Lms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Who is on which course. Paid sign-ups arrive here as Pending with the
 * payment details the student typed; approving one opens the course.
 */
class EnrollmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Enrollment::class);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string'],
            'course_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($data['search'] ?? ''));

        $enrollments = $this->scoped($request)
            ->with(['student:id,name,email', 'course:id,title,slug'])
            ->status($data['status'] ?? null)
            ->when($data['course_id'] ?? null, fn (Builder $q, $id) => $q->where('course_id', $id))
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $q->whereHas('student', fn (Builder $s) => $s->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhere('transaction_id', 'like', "%{$search}%");
            }))
            // Pending first: they are the ones waiting on someone.
            ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 10))
            ->withQueryString();

        $progress = $this->progressFor($enrollments->getCollection());

        return response()->json([
            'data' => $enrollments->getCollection()->map(fn (Enrollment $e) => $this->row($request, $e, $progress))->values(),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'from' => $enrollments->firstItem() ?? 0,
                'to' => $enrollments->lastItem() ?? 0,
                'total' => $enrollments->total(),
            ],
            'summary' => collect(Enrollment::STATUSES)->mapWithKeys(fn ($s) => [$s => $this->scoped($request)->where('status', $s)->count()]),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Enrollment::class);

        $courses = Course::query()->orderBy('title')
            ->when($request->user()->role?->scope === 'Own content', fn ($q) => $q->where('user_id', $request->user()->id))
            ->get(['id', 'title', 'price', 'sale_price']);

        return response()->json([
            'statuses' => Enrollment::STATUSES,
            'courses' => $courses->map(fn (Course $c) => ['id' => $c->id, 'title' => $c->title, 'price' => $c->currentPrice()]),
            'payment_methods' => Lms::paymentMethods(),
            'currency' => Lms::setting('currency'),
        ]);
    }

    /**
     * "Enroll a student": by email. Someone without an account gets a
     * student account made for them, and its password is shown once.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Enrollment::class);

        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'email' => ['required', 'email', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'transaction_id' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $course = Course::findOrFail($data['course_id']);
        abort_unless($request->user()->role?->scope !== 'Own content' || $course->user_id === $request->user()->id, 403);

        $student = User::where('email', Str::lower($data['email']))->first();
        $password = null;

        if (! $student) {
            if (blank($data['name'] ?? null)) {
                return response()->json([
                    'message' => 'No account uses that email yet. Add their name and one will be made.',
                    'errors' => ['name' => ['Needed for a new student account.']],
                ], 422);
            }
            $password = Str::password(12, symbols: false);
            $student = Lms::createStudent($data['name'], $data['email'], $password);
        }

        $enrollment = Lms::enroll($student, $course, [
            'amount' => $data['amount'] ?? 0,
            'payment_method' => $data['payment_method'] ?? 'Added by admin',
            'transaction_id' => $data['transaction_id'] ?? null,
            'note' => $data['note'] ?? null,
        ], active: true);

        Lms::notify($student, new StudentNotice(
            'You are enrolled in '.$course->title,
            'The course is open in your student panel — start whenever you are ready.',
            route('learn.show', $course->slug),
            'success',
            'Start learning',
        ));

        return response()->json([
            'message' => $password
                ? "{$student->name} is enrolled. Their new account's password is {$password} — share it with them; it is shown only once."
                : "{$student->name} is enrolled on {$course->title}.",
            'password' => $password,
            'data' => $this->row($request, $enrollment->load(['student', 'course']), $this->progressFor(collect([$enrollment]))),
        ], 201);
    }

    /** Approve, cancel, or change the payment details and note. */
    public function update(Request $request, Enrollment $enrollment): JsonResponse
    {
        $this->authorize('update', $enrollment);

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(Enrollment::STATUSES)],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:60'],
            'transaction_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $before = $enrollment->status;
        $enrollment->fill($data);

        if ($enrollment->status === 'Active' && ! $enrollment->enrolled_at) {
            $enrollment->enrolled_at = now();
        }

        $enrollment->save();

        // The payment behind it follows: approving verifies it, cancelling
        // rejects it — and the student hears about either.
        $waiting = $enrollment->payments()->where('status', 'Pending')->get();
        if ($before !== 'Active' && $enrollment->status === 'Active') {
            $waiting->each(fn ($payment) => Lms::verifyPayment($payment, $request->user()));
        } elseif ($before !== 'Cancelled' && $enrollment->status === 'Cancelled') {
            $waiting->each(fn ($payment) => Lms::rejectPayment($payment, $request->user(), 'The enrollment was cancelled. Contact us if you think this is a mistake.'));
        }

        $enrollment->load(['student', 'course']);
        ActivityLog::record(match (true) {
            $before !== 'Active' && $enrollment->status === 'Active' => 'Approved enrollment',
            $enrollment->status === 'Cancelled' && $before !== 'Cancelled' => 'Cancelled enrollment',
            default => 'Updated enrollment',
        }, $enrollment->student?->name.' → '.$enrollment->course?->title, $enrollment);

        return response()->json([
            'message' => match ($enrollment->status) {
                'Active' => $before === 'Active' ? 'Enrollment saved.' : 'Approved — the course is open to them now.',
                'Cancelled' => 'Enrollment cancelled.',
                default => 'Enrollment saved.',
            },
            'data' => $this->row($request, $enrollment, $this->progressFor(collect([$enrollment]))),
        ]);
    }

    public function destroy(Enrollment $enrollment): JsonResponse
    {
        $this->authorize('delete', $enrollment);

        $enrollment->load(['student', 'course']);
        $enrollment->delete();
        // Progress is kept: enrolling again picks up where they left off.
        ActivityLog::record('Removed enrollment', $enrollment->student?->name.' → '.$enrollment->course?->title);

        return response()->json(['message' => 'Enrollment removed.']);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    /** An "Own content" role only ever sees the students on its own courses. */
    private function scoped(Request $request): Builder
    {
        return Enrollment::query()->when(
            $request->user()->role?->scope === 'Own content',
            fn (Builder $q) => $q->whereHas('course', fn (Builder $c) => $c->where('user_id', $request->user()->id))
        );
    }

    /**
     * Percent complete for a page of enrollments, in two queries rather
     * than two per row.
     *
     * @return array<string, int> "userId:courseId" => percent
     */
    private function progressFor($enrollments): array
    {
        $courseIds = $enrollments->pluck('course_id')->unique()->all();
        $userIds = $enrollments->pluck('user_id')->unique()->all();

        $totals = Lesson::whereIn('course_id', $courseIds)
            ->selectRaw('course_id, count(*) as n')->groupBy('course_id')->pluck('n', 'course_id');

        $done = LessonCompletion::whereIn('course_id', $courseIds)->whereIn('user_id', $userIds)
            ->selectRaw('user_id, course_id, count(*) as n')->groupBy('user_id', 'course_id')->get();

        $out = [];
        foreach ($done as $row) {
            $total = (int) ($totals[$row->course_id] ?? 0);
            $out[$row->user_id.':'.$row->course_id] = $total ? (int) min(100, round($row->n / $total * 100)) : 0;
        }

        return $out;
    }

    private function row(Request $request, Enrollment $e, array $progress): array
    {
        return [
            'id' => $e->id,
            'status' => $e->status,
            'student' => $e->student?->name ?? 'Deleted account',
            'email' => $e->student?->email,
            'user_id' => $e->user_id,
            'course' => $e->course?->title ?? 'Deleted course',
            'course_id' => $e->course_id,
            'course_url' => $e->course ? route('courses.show', $e->course->slug) : null,
            'amount' => (float) $e->amount,
            'amount_label' => (float) $e->amount > 0 ? Lms::money($e->amount) : 'Free',
            'payment_method' => $e->payment_method,
            'transaction_id' => $e->transaction_id,
            'note' => $e->note,
            'progress' => $progress[$e->user_id.':'.$e->course_id] ?? 0,
            'completed' => $e->completed_at !== null,
            'date' => ($e->enrolled_at ?? $e->created_at)?->format('d M Y'),
            'requested' => $e->created_at?->format('d M Y, H:i'),
            'can' => [
                'update' => $request->user()->can('update', $e),
                'delete' => $request->user()->can('delete', $e),
            ],
        ];
    }
}
