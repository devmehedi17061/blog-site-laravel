<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Payment;
use App\Models\User;
use App\Support\Lms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The student panel: overview, courses, payments, certificates,
 * notifications and the account. The sidebar's counts come from
 * App\View\Composers\StudentPanelComposer.
 */
class StudentDashboardController extends Controller
{
    // ── overview ───────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $user = $request->user();
        $enrollments = $this->enrollments($user);
        $progress = $this->progress($user, $enrollments);
        $active = $enrollments->where('status', 'Active');

        // "Continue where you left off": the course touched last and not finished.
        $continue = $active->whereNull('completed_at')
            ->sortByDesc(fn (Enrollment $e) => $e->last_activity_at?->timestamp ?? 0)
            ->first();

        $completions = LessonCompletion::where('user_id', $user->id)->get(['lesson_id', 'course_id', 'created_at']);
        $minutes = (int) Lesson::whereIn('id', $completions->pluck('lesson_id'))->sum('duration_minutes');

        return view('frontend.student.dashboard', [
            'user' => $user,
            'continue' => $continue,
            'progress' => $progress,
            'inProgress' => $active->whereNull('completed_at')->sortByDesc(fn ($e) => $e->last_activity_at?->timestamp ?? 0)->values(),
            'stats' => [
                'in_progress' => $active->whereNull('completed_at')->count(),
                'completed' => $active->whereNotNull('completed_at')->count(),
                'lessons' => $completions->count(),
                'hours' => $minutes >= 60 ? round($minutes / 60, 1).'h' : $minutes.'m',
                'streak' => $this->streak($completions->pluck('created_at')),
            ],
            'week' => $this->week($completions->pluck('created_at')),
            'pendingPayments' => Payment::where('user_id', $user->id)->where('status', 'Pending')->with('course:id,title,slug')->latest()->get(),
            'rejectedPayments' => $this->openRejections($user),
            'notifications' => $user->notifications()->latest()->limit(4)->get(),
            'recommended' => Course::published()
                ->whereNotIn('id', $enrollments->pluck('course_id'))
                ->with(['instructor:id,name', 'category:id,name,slug', 'thumbnail'])
                ->withCount('lessons')
                ->orderByDesc('is_featured')->latest('published_at')->limit(3)->get(),
        ]);
    }

    // ── my courses ─────────────────────────────────────────────────────────

    public function courses(Request $request): View
    {
        $user = $request->user();
        $enrollments = $this->enrollments($user);
        $tab = in_array($request->query('tab'), ['progress', 'completed', 'pending'], true) ? $request->query('tab') : 'progress';

        $groups = [
            'progress' => $enrollments->where('status', 'Active')->whereNull('completed_at')->values(),
            'completed' => $enrollments->where('status', 'Active')->whereNotNull('completed_at')->values(),
            'pending' => $enrollments->where('status', 'Pending')->values(),
        ];

        return view('frontend.student.courses', [
            'tab' => $tab,
            'groups' => $groups,
            'progress' => $this->progress($user, $enrollments),
        ]);
    }

    // ── payments ───────────────────────────────────────────────────────────

    public function payments(Request $request): View
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->with(['course:id,title,slug', 'enrollment:id,status'])
            ->latest()->get();

        return view('frontend.student.payments', [
            'payments' => $payments,
            'totalPaid' => (float) $payments->where('status', 'Verified')->sum('amount'),
            'rejectedOpen' => $this->openRejections($request->user())->pluck('id')->all(),
        ]);
    }

    public function receipt(Request $request, Payment $payment): View
    {
        abort_unless($payment->user_id === $request->user()->id || $request->user()->hasPermission('enrollments.view'), 403);
        abort_unless($payment->isVerified(), 404);

        return view('frontend.student.receipt', ['payment' => $payment->load(['student', 'course', 'reviewer:id,name'])]);
    }

    /** The payment screenshot: for its student and for staff who handle enrollments. */
    public function proof(Request $request, Payment $payment): StreamedResponse
    {
        $user = $request->user();
        $staff = $user->hasPermission('enrollments.view')
            && ($user->role?->scope !== 'Own content' || $payment->course?->user_id === $user->id);

        abort_unless($payment->user_id === $user->id || $staff, 403);
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path, null, [
            'Cache-Control' => 'private, max-age=600',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    // ── certificates ───────────────────────────────────────────────────────

    public function certificates(Request $request): View
    {
        return view('frontend.student.certificates', [
            'enrollments' => Enrollment::where('user_id', $request->user()->id)
                ->where('status', 'Active')->whereNotNull('completed_at')
                ->whereHas('course')
                ->with(['course' => fn ($q) => $q->with(['thumbnail', 'instructor:id,name'])])
                ->latest('completed_at')->get(),
            'enabled' => (bool) Lms::setting('certificates'),
        ]);
    }

    // ── notifications ──────────────────────────────────────────────────────

    public function notifications(Request $request): View
    {
        return view('frontend.student.notifications', [
            'notifications' => $request->user()->notifications()->latest()->paginate(15),
        ]);
    }

    /** Opens a notification's link, marking it read on the way. */
    public function openNotification(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('student.notifications'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }

    // ── account ────────────────────────────────────────────────────────────

    public function account(Request $request): View
    {
        return view('frontend.student.account', ['user' => $request->user()]);
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'That is not your current password.',
            'current_password.required_with' => 'Enter your current password to set a new one.',
        ]);

        $user->fill(['name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return back()->with('status', 'Your account is saved.');
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function enrollments(User $user): Collection
    {
        return Enrollment::where('user_id', $user->id)
            ->whereIn('status', ['Active', 'Pending'])
            ->whereHas('course')
            ->with(['course' => fn ($q) => $q->with(['thumbnail', 'instructor:id,name'])->withCount('lessons'), 'lastLesson:id,title'])
            ->latest('updated_at')
            ->get();
    }

    /** @return array<int, int> enrollment id => percent */
    private function progress(User $user, Collection $enrollments): array
    {
        $courseIds = $enrollments->pluck('course_id')->all();
        $totals = Lesson::whereIn('course_id', $courseIds)->selectRaw('course_id, count(*) as n')->groupBy('course_id')->pluck('n', 'course_id');
        $done = LessonCompletion::where('user_id', $user->id)->whereIn('course_id', $courseIds)
            ->selectRaw('course_id, count(*) as n')->groupBy('course_id')->pluck('n', 'course_id');

        return $enrollments->mapWithKeys(fn (Enrollment $e) => [
            $e->id => ($totals[$e->course_id] ?? 0) ? (int) min(100, round(($done[$e->course_id] ?? 0) / $totals[$e->course_id] * 100)) : 0,
        ])->all();
    }

    /** Days in a row, up to today or yesterday, on which a lesson was finished. */
    private function streak(Collection $times): int
    {
        $days = $times->map(fn ($t) => Carbon::parse($t)->toDateString())->unique()->flip();
        $day = $days->has(today()->toDateString()) ? today() : today()->subDay();
        $streak = 0;

        while ($days->has($day->toDateString())) {
            $streak++;
            $day = $day->subDay();
        }

        return $streak;
    }

    /** Lessons finished on each of the last seven days, oldest first. */
    private function week(Collection $times): array
    {
        $counts = $times->countBy(fn ($t) => Carbon::parse($t)->toDateString());

        return collect(range(6, 0))->map(fn ($ago) => [
            'label' => today()->subDays($ago)->format('D'),
            'date' => today()->subDays($ago)->format('d M'),
            'count' => (int) ($counts[today()->subDays($ago)->toDateString()] ?? 0),
            'today' => $ago === 0,
        ])->all();
    }

    /** Rejected payments the student still has to act on (no newer payment, course not open). */
    private function openRejections(User $user): Collection
    {
        return Payment::where('user_id', $user->id)->latest()->with(['course:id,title,slug', 'enrollment:id,status'])->get()
            ->groupBy('course_id')
            ->map->first()
            ->filter(fn (Payment $p) => $p->status === 'Rejected' && $p->enrollment?->status !== 'Active')
            ->values();
    }
}
