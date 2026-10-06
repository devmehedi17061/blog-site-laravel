<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Support\Lms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Joining a course. Free courses open straight away; a paid one goes
 * through the checkout, where the student says how they paid and the
 * place waits as Pending until an admin confirms it (or opens at once
 * when Settings → LMS → auto-approve is on).
 */
class EnrollController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $course = CourseController::findForViewer($request, $slug);
        $user = $request->user();

        if ($user->activeEnrollmentFor($course)) {
            return redirect()->route('learn.show', $course->slug);
        }

        if (! $course->isFree()) {
            return redirect()->route('courses.checkout', $course->slug);
        }

        Lms::enroll($user, $course, ['amount' => 0, 'payment_method' => 'Free']);

        return redirect()->route('learn.show', $course->slug)
            ->with('status', 'You are enrolled. Welcome to '.$course->title.'!');
    }

    public function checkout(Request $request, string $slug): View|RedirectResponse
    {
        $course = CourseController::findForViewer($request, $slug);
        $enrollment = Enrollment::where('user_id', $request->user()->id)->where('course_id', $course->id)->first();

        if ($enrollment?->isActive() && ! $enrollment->payments()->where('status', 'Pending')->exists()) {
            return redirect()->route('learn.show', $course->slug);
        }

        if ($course->isFree()) {
            return redirect()->route('courses.show', $course->slug);
        }

        $payments = Payment::where('user_id', $request->user()->id)->where('course_id', $course->id)->latest()->get();

        return view('frontend.courses.checkout', [
            'course' => $course->load('thumbnail', 'instructor:id,name'),
            'enrollment' => $enrollment,
            'pending' => $payments->firstWhere('status', 'Pending'),
            'rejected' => $payments->first()?->status === 'Rejected' ? $payments->first() : null,
            'methods' => Lms::paymentMethods(),
            'instructions' => (string) Lms::setting('payment_instructions'),
        ]);
    }

    /**
     * The student reports a payment. A transaction ID already claimed by a
     * payment that was not rejected is refused here, so one payment cannot
     * buy two courses — a resubmission of this student's own pending
     * payment for this course is the exception (it replaces the old one).
     */
    public function pay(Request $request, string $slug): RedirectResponse
    {
        $course = CourseController::findForViewer($request, $slug);
        abort_if($course->isFree(), 404);

        $user = $request->user();
        $methods = Lms::paymentMethods();

        $data = $request->validate([
            'method' => ['required', 'string', 'max:60', $methods ? Rule::in($methods) : 'string'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'sender_number' => ['required', 'string', 'max:40'],
            'transaction_id' => ['required', 'string', 'max:120', function ($attribute, $value, $fail) use ($request, $user, $course) {
                $taken = Payment::where('method', $request->input('method'))
                    ->where('transaction_id', trim((string) $value))
                    ->where('status', '!=', 'Rejected')
                    ->where(fn ($q) => $q->where('user_id', '!=', $user->id)->orWhere('course_id', '!=', $course->id)->orWhere('status', 'Verified'))
                    ->exists();
                if ($taken) {
                    $fail('This transaction ID has already been used. Check it, or contact us if you think this is a mistake.');
                }
            }],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'proof.mimes' => 'Upload the screenshot as JPG, PNG, WebP or PDF.',
            'proof.max' => 'The screenshot must be 5 MB or smaller.',
        ], [
            'transaction_id' => 'transaction ID',
            'sender_number' => 'number you paid from',
            'method' => 'payment method',
        ]);

        $payment = Lms::submitPayment($user, $course, $data, $request->file('proof'));

        return $payment->enrollment?->isActive()
            ? redirect()->route('learn.show', $course->slug)->with('status', 'Payment received — the course is open. We will verify the payment shortly.')
            : redirect()->route('student.payments')->with('status', 'Thanks! Your payment ('.$payment->invoiceNumber().') is waiting for verification — we will notify you as soon as it is checked.');
    }
}
