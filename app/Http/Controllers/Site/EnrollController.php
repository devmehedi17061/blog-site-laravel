<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Course;
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
 *
 * A coupon is kept in the session per course while the student checks
 * out, and checked again on every request — a coupon that expired or ran
 * out in the meantime simply stops applying.
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
            ->with('status', __('You are enrolled. Welcome to :course!', ['course' => $course->title]));
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

        // A promo link: /courses/x/checkout?coupon=EID50
        if ($request->filled('coupon')) {
            self::applyFromLink($request, $course);

            return redirect()->route('courses.checkout', $course->slug);
        }

        $payments = Payment::where('user_id', $request->user()->id)->where('course_id', $course->id)->latest()->get();
        $coupon = self::appliedCoupon($request, $course);

        return view('frontend.courses.checkout', [
            'course' => $course->load('thumbnail', 'instructor:id,name'),
            'enrollment' => $enrollment,
            'pending' => $payments->firstWhere('status', 'Pending'),
            'rejected' => $payments->first()?->status === 'Rejected' ? $payments->first() : null,
            'methods' => Lms::paymentMethods(),
            'instructions' => (string) Lms::setting('payment_instructions'),
            'coupon' => $coupon,
            'price' => Lms::priceFor($course, $coupon),
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
        $coupon = self::appliedCoupon($request, $course);
        $price = Lms::priceFor($course, $coupon);

        // A coupon that takes the whole price off: nothing to pay or check.
        if ($coupon && $price['total'] <= 0) {
            Lms::enrollWithFreeCoupon($user, $course, $coupon);
            $request->session()->forget(self::sessionKey($course));

            return redirect()->route('learn.show', $course->slug)
                ->with('status', __('Coupon :code applied — the course is yours. Welcome!', ['code' => $coupon->code]));
        }

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
                    $fail(__('This transaction ID has already been used. Check it, or contact us if you think this is a mistake.'));
                }
            }],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'proof.mimes' => __('Upload the screenshot as JPG, PNG, WebP or PDF.'),
            'proof.max' => __('The screenshot must be 5 MB or smaller.'),
        ], [
            'transaction_id' => 'transaction ID',
            'sender_number' => 'number you paid from',
            'method' => 'payment method',
        ]);

        $payment = Lms::submitPayment($user, $course, $data, $request->file('proof'), $coupon);
        $request->session()->forget(self::sessionKey($course));

        return $payment->enrollment?->isActive()
            ? redirect()->route('learn.show', $course->slug)->with('status', __('Payment received — the course is open. We will verify the payment shortly.'))
            : redirect()->route('student.payments')->with('status', __('Thanks! Your payment (:invoice) is waiting for verification — we will notify you as soon as it is checked.', ['invoice' => $payment->invoiceNumber()]));
    }

    // ── coupons ────────────────────────────────────────────────────────────

    public function applyCoupon(Request $request, string $slug): RedirectResponse
    {
        $course = CourseController::findForViewer($request, $slug);
        $request->validate(['code' => ['required', 'string', 'max:40']], ['code.required' => __('Type a coupon code.')]);

        $coupon = Coupon::findByCode($request->input('code'));
        $problem = $coupon ? $coupon->problemFor($request->user(), $course) : __('That coupon code does not exist.');

        if ($problem) {
            return back()->withErrors(['code' => $problem])->withInput();
        }

        $request->session()->put(self::sessionKey($course), $coupon->code);

        return back()->with('status', __('Coupon :code applied — :discount.', ['code' => $coupon->code, 'discount' => $coupon->label()]));
    }

    public function removeCoupon(Request $request, string $slug): RedirectResponse
    {
        $course = CourseController::findForViewer($request, $slug);
        // Empty, not forgotten: a catalogue-wide coupon must not come back.
        $request->session()->put(self::sessionKey($course), '');

        return back()->with('status', __('Coupon removed.'));
    }

    /**
     * The coupon this visitor applied to this course, if it still applies —
     * or one from a catalogue promo link (/courses?coupon=X) that is valid
     * for this course.
     */
    public static function appliedCoupon(Request $request, Course $course): ?Coupon
    {
        if (! $request->session()->has(self::sessionKey($course)) && ($any = $request->session()->get('coupons.any'))) {
            $coupon = Coupon::findByCode($any);

            return $coupon && ! $coupon->problemFor($request->user(), $course) ? $coupon : null;
        }

        $code = $request->session()->get(self::sessionKey($course));
        $coupon = $code ? Coupon::findByCode($code) : null;

        if ($code && (! $coupon || $coupon->problemFor($request->user(), $course))) {
            $request->session()->forget(self::sessionKey($course));

            return null;
        }

        return $coupon;
    }

    /** ?coupon=CODE on a course page or the checkout: applied when it is valid. */
    public static function applyFromLink(Request $request, Course $course): void
    {
        $coupon = Coupon::findByCode($request->query('coupon'));
        $problem = $coupon ? $coupon->problemFor($request->user(), $course) : __('That coupon code does not exist.');

        if ($problem) {
            $request->session()->flash('coupon_error', $problem);

            return;
        }

        $request->session()->put(self::sessionKey($course), $coupon->code);
        $request->session()->flash('status', __('Coupon :code applied — :discount.', ['code' => $coupon->code, 'discount' => $coupon->label()]));
    }

    private static function sessionKey(Course $course): string
    {
        return 'coupons.'.$course->id;
    }
}
