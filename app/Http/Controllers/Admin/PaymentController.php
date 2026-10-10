<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Support\CsvExport;
use App\Support\Lms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * LMS → Payments: what students reported, with what helps to check it — the
 * screenshot, whether the amount matches, whether the transaction ID was
 * used before — and Verify / Reject. Uses the enrollments permissions.
 */
class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Enrollment::class);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string'],
            'method' => ['nullable', 'string', 'max:60'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $payments = $this->filtered($request, $data)
            ->paginate((int) ($data['per_page'] ?? 10))
            ->withQueryString();

        return response()->json([
            'data' => $payments->getCollection()->map(fn (Payment $p) => $this->row($request, $p))->values(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'from' => $payments->firstItem() ?? 0,
                'to' => $payments->lastItem() ?? 0,
                'total' => $payments->total(),
            ],
            'summary' => collect(Payment::STATUSES)->mapWithKeys(fn ($s) => [$s => $this->scoped($request)->where('status', $s)->count()]),
            'verified_total' => Lms::money((float) $this->scoped($request)->where('status', 'Verified')->sum('amount')),
            'methods' => Lms::paymentMethods(),
            'currency' => Lms::setting('currency'),
        ]);
    }

    public function verify(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizePayment($request, $payment);
        abort_if($payment->isVerified(), 422, 'This payment is already verified.');

        Lms::verifyPayment($payment, $request->user());

        return response()->json([
            'message' => 'Verified — '.$payment->course?->title.' is open for '.$payment->student?->name.'. They have been notified.',
            'data' => $this->row($request, $payment->fresh(['student', 'course', 'reviewer'])),
        ]);
    }

    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizePayment($request, $payment);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], [
            'reason.required' => 'Tell the student why, so they can fix it.',
        ]);

        Lms::rejectPayment($payment, $request->user(), $data['reason']);

        return response()->json([
            'message' => 'Rejected — '.$payment->student?->name.' has been told why and can send it again.',
            'data' => $this->row($request, $payment->fresh(['student', 'course', 'reviewer'])),
        ]);
    }

    /**
     * "Verify selected": the ticked payments that are still waiting, each
     * the same as pressing Verify on it (course opens, student notified).
     */
    public function bulkVerify(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('enrollments.update'), 403);
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['integer']]);

        $payments = $this->scoped($request)->whereIn('id', $data['ids'])->where('status', 'Pending')->get();
        foreach ($payments as $payment) {
            Lms::verifyPayment($payment, $request->user());
        }

        return response()->json([
            'message' => $payments->count() === 1 ? '1 payment verified.' : $payments->count().' payments verified — each student has been notified.',
        ]);
    }

    /** The list as on screen (same filters), as a CSV file. */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Enrollment::class);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string'],
            'method' => ['nullable', 'string', 'max:60'],
        ]);

        $rows = $this->filtered($request, $data)->lazy(200)->map(fn (Payment $p) => [
            $p->invoiceNumber(), $p->created_at?->format('Y-m-d H:i'), $p->status,
            $p->student?->name, $p->student?->email, $p->student?->phone, $p->course?->title,
            (float) $p->amount, (float) $p->expected_amount, (float) $p->discount, $p->coupon_code,
            $p->method, $p->sender_number, $p->transaction_id,
            $p->reviewer?->name, $p->reviewed_at?->format('Y-m-d H:i'), $p->rejection_reason,
        ]);

        return CsvExport::download('payments', [
            'Invoice', 'Sent', 'Status', 'Student', 'Email', 'Phone', 'Course',
            'Amount', 'Course price', 'Coupon discount', 'Coupon', 'Method', 'Paid from', 'Transaction ID',
            'Checked by', 'Checked at', 'Rejection reason',
        ], $rows);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    /** Search, status and method — shared by the list and its CSV. */
    private function filtered(Request $request, array $data): Builder
    {
        $search = trim((string) ($data['search'] ?? ''));

        return $this->scoped($request)
            ->with(['student:id,name,email,phone', 'course:id,title,slug,user_id', 'reviewer:id,name'])
            ->status($data['status'] ?? null)
            ->when($data['method'] ?? null, fn (Builder $q, $m) => $q->where('method', $m))
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                    ->orWhere('sender_number', 'like', "%{$search}%")
                    ->orWhereHas('student', fn (Builder $s) => $s->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            }))
            // Waiting ones first, oldest first among them: first come, first checked.
            ->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'Pending' THEN created_at END ASC")
            ->latest();
    }

    private function authorizePayment(Request $request, Payment $payment): void
    {
        abort_unless($request->user()->hasPermission('enrollments.update'), 403);
        abort_unless($request->user()->role?->scope !== 'Own content' || $payment->course?->user_id === $request->user()->id, 403);
    }

    /** An "Own content" role only sees payments for its own courses. */
    private function scoped(Request $request): Builder
    {
        return Payment::query()->when(
            $request->user()->role?->scope === 'Own content',
            fn (Builder $q) => $q->whereHas('course', fn (Builder $c) => $c->where('user_id', $request->user()->id))
        );
    }

    private function row(Request $request, Payment $p): array
    {
        $duplicates = $p->duplicates();

        return [
            'id' => $p->id,
            'invoice' => $p->invoiceNumber(),
            'status' => $p->status,
            'student' => $p->student?->name ?? 'Deleted account',
            'email' => $p->student?->email,
            'phone' => $p->student?->phone,
            'course' => $p->course?->title ?? 'Deleted course',
            'course_url' => $p->course ? route('courses.show', $p->course->slug) : null,
            'amount' => (float) $p->amount,
            'amount_label' => Lms::money($p->amount),
            'expected_label' => Lms::money($p->expected_amount),
            'short' => $p->isShort(),
            'method' => $p->method,
            'sender_number' => $p->sender_number,
            'transaction_id' => $p->transaction_id,
            'note' => $p->note,
            'proof_url' => $p->proof_path ? route('payments.proof', $p) : null,
            'proof_is_pdf' => $p->proof_path && str_ends_with(strtolower($p->proof_path), '.pdf'),
            // Same transaction ID on another payment that was not rejected.
            'duplicates' => $duplicates->map(fn (Payment $d) => [
                'invoice' => $d->invoiceNumber(),
                'student' => $d->student?->name,
                'course' => $d->course?->title,
                'status' => $d->status,
            ])->values(),
            'rejection_reason' => $p->rejection_reason,
            'reviewer' => $p->reviewer?->name,
            'reviewed_at' => $p->reviewed_at?->format('d M Y, H:i'),
            'submitted' => $p->created_at?->format('d M Y, H:i'),
            'submitted_ago' => $p->created_at?->diffForHumans(),
            'receipt_url' => $p->isVerified() ? route('student.receipt', $p) : null,
            'can' => ['review' => $request->user()->hasPermission('enrollments.update')],
        ];
    }
}
