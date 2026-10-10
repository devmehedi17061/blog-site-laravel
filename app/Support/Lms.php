<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Notifications\StudentNotice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The learning side's shared rules: money, Settings → LMS, and how a student
 * comes to be on a course. Kept in one place so the public checkout and the
 * admin "Enroll a student" button cannot drift apart.
 */
class Lms
{
    public static function setting(string $key): mixed
    {
        return app(SiteSettings::class)->get('lms.'.$key);
    }

    public static function money(float|int|string|null $amount): string
    {
        $amount = (float) $amount;
        $decimals = floor($amount) == $amount ? 0 : 2;

        return self::setting('currency').number_format($amount, $decimals);
    }

    /** @return array<int, string> */
    public static function paymentMethods(): array
    {
        return collect(preg_split('/\R/', (string) self::setting('payment_methods')))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    public static function studentRole(): ?Role
    {
        return Role::where('slug', Role::STUDENT)->first();
    }

    /**
     * Puts a student on a course, or updates the place they already have.
     * A free course, or a paid one with auto-approve on, opens at once;
     * otherwise the place waits as Pending until an admin approves it.
     */
    public static function enroll(User $student, Course $course, array $payment = [], ?bool $active = null): Enrollment
    {
        $active ??= $course->isFree() || (bool) self::setting('auto_approve');

        $enrollment = Enrollment::firstOrNew(['user_id' => $student->id, 'course_id' => $course->id]);
        $wasActive = $enrollment->exists && $enrollment->isActive();

        $enrollment->fill([
            'status' => $wasActive || $active ? 'Active' : 'Pending',
            'amount' => $payment['amount'] ?? $course->currentPrice(),
            'payment_method' => $payment['payment_method'] ?? $enrollment->payment_method,
            'transaction_id' => $payment['transaction_id'] ?? $enrollment->transaction_id,
            'note' => $payment['note'] ?? $enrollment->note,
        ]);

        if ($enrollment->status === 'Active' && ! $enrollment->enrolled_at) {
            $enrollment->enrolled_at = now();
        }

        $enrollment->save();

        ActivityLog::record(
            $enrollment->status === 'Active' ? 'Enrolled' : 'Requested enrollment',
            $student->name.' → '.$course->title,
            $enrollment
        );

        return $enrollment;
    }

    /** A new learner account, for the public sign-up and the admin's "Enroll". */
    public static function createStudent(string $name, string $email, ?string $password = null): User
    {
        return User::create([
            'name' => $name,
            'email' => Str::lower($email),
            'password' => $password ?? Str::password(16),
            'role_id' => self::studentRole()?->id,
            'status' => 'Active',
        ]);
    }

    // ── payments ───────────────────────────────────────────────────────────

    /**
     * A payment the student reports at the checkout. It is recorded as
     * Pending, and the enrollment waits with it — unless auto-approve is on,
     * when the course opens at once and the payment still waits for an
     * admin to check it (a rejection then closes the course again).
     */
    public static function submitPayment(User $student, Course $course, array $data, ?UploadedFile $proof = null, ?Coupon $coupon = null): Payment
    {
        $price = self::priceFor($course, $coupon);

        $enrollment = self::enroll($student, $course, [
            'amount' => $data['amount'] ?? $price['total'],
            'payment_method' => $data['method'],
            'transaction_id' => $data['transaction_id'],
            'note' => $data['note'] ?? null,
        ]);

        // Another payment for this course still waiting is replaced: the
        // student is correcting what they sent, not paying twice. Its
        // coupon use goes with it.
        $replaced = Payment::where('enrollment_id', $enrollment->id)->where('status', 'Pending')->get();
        foreach ($replaced as $old) {
            CouponRedemption::where('payment_id', $old->id)->delete();
            self::deleteProof($old);
            $old->delete();
        }

        $payment = Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'enrollment_id' => $enrollment->id,
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'discount' => $price['discount'],
            'amount' => $data['amount'] ?? $price['total'],
            'expected_amount' => $price['total'],
            'method' => $data['method'],
            'sender_number' => $data['sender_number'] ?? null,
            'transaction_id' => trim($data['transaction_id']),
            'proof_path' => $proof?->store('payment-proofs/'.$student->id, 'local'),
            'note' => $data['note'] ?? null,
            'status' => 'Pending',
        ]);

        if ($coupon && $price['discount'] > 0) {
            CouponRedemption::create([
                'coupon_id' => $coupon->id, 'user_id' => $student->id, 'course_id' => $course->id,
                'payment_id' => $payment->id, 'discount' => $price['discount'],
            ]);
        }

        return $payment;
    }

    /**
     * What a course costs with a coupon: the price, the money off, the total.
     *
     * @return array{price: float, discount: float, total: float}
     */
    public static function priceFor(Course $course, ?Coupon $coupon = null): array
    {
        $price = $course->currentPrice();
        $discount = $coupon ? $coupon->discountFor($price) : 0.0;

        return ['price' => $price, 'discount' => $discount, 'total' => round(max(0, $price - $discount), 2)];
    }

    /**
     * A coupon that takes the whole price off: no payment to check, the
     * course opens now and the use is counted.
     */
    public static function enrollWithFreeCoupon(User $student, Course $course, Coupon $coupon): Enrollment
    {
        $price = self::priceFor($course, $coupon);

        $enrollment = self::enroll($student, $course, [
            'amount' => 0,
            'payment_method' => 'Coupon '.$coupon->code,
        ], active: true);

        CouponRedemption::create([
            'coupon_id' => $coupon->id, 'user_id' => $student->id, 'course_id' => $course->id,
            'discount' => $price['discount'],
        ]);

        return $enrollment;
    }

    /** The payment checks out: the course opens and the student is told. */
    public static function verifyPayment(Payment $payment, User $reviewer): void
    {
        $payment->update(['status' => 'Verified', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'rejection_reason' => null]);

        $enrollment = $payment->enrollment ?? Enrollment::firstOrNew(['user_id' => $payment->user_id, 'course_id' => $payment->course_id]);
        $enrollment->fill([
            'status' => 'Active',
            'amount' => $payment->amount,
            'payment_method' => $payment->method,
            'transaction_id' => $payment->transaction_id,
        ]);
        $enrollment->enrolled_at ??= now();
        $enrollment->save();
        $payment->enrollment()->associate($enrollment)->save();

        ActivityLog::record('Verified payment', $payment->invoiceNumber().' · '.$payment->student?->name.' → '.$payment->course?->title, $payment);

        self::notify($payment->student, new StudentNotice(
            'Payment verified — :course is open',
            'We checked your payment of :amount (:method, :transaction). The course is open — enjoy learning!',
            $payment->course ? route('learn.show', $payment->course->slug) : null,
            'success',
            'Start learning',
            params: ['course' => $payment->course?->title, 'amount' => self::money($payment->amount), 'method' => $payment->method, 'transaction' => $payment->transaction_id],
        ));
    }

    /**
     * The payment does not check out. The course closes (or never opens),
     * and the student sees why and can send a corrected payment.
     */
    public static function rejectPayment(Payment $payment, User $reviewer, string $reason): void
    {
        $payment->update(['status' => 'Rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'rejection_reason' => $reason]);

        // A rejected payment gives its coupon use back.
        CouponRedemption::where('payment_id', $payment->id)->delete();

        // A place that only exists because of this payment goes with it. One
        // paid another way (or verified before) is left alone.
        $enrollment = $payment->enrollment;
        $otherwisePaid = $enrollment && Payment::where('enrollment_id', $enrollment->id)->where('status', 'Verified')->exists();
        if ($enrollment && ! $otherwisePaid && $enrollment->status !== 'Cancelled') {
            $enrollment->update(['status' => 'Cancelled']);
        }

        ActivityLog::record('Rejected payment', $payment->invoiceNumber().' · '.$reason, $payment);

        self::notify($payment->student, new StudentNotice(
            'Payment not verified — :course',
            'We could not verify your payment (:method, :transaction): :reason You can send the payment details again.',
            $payment->course ? route('courses.checkout', $payment->course->slug) : null,
            'warn',
            'Send again',
            params: ['course' => $payment->course?->title, 'method' => $payment->method, 'transaction' => $payment->transaction_id, 'reason' => $reason],
        ));
    }

    public static function deleteProof(Payment $payment): void
    {
        if ($payment->proof_path) {
            Storage::disk('local')->delete($payment->proof_path);
        }
    }

    /**
     * Notifies without ever failing the action behind it: the notice is
     * stored first, and a mail server that is down only gets reported.
     */
    public static function notify(?User $user, StudentNotice $notice): void
    {
        if (! $user) {
            return;
        }

        try {
            $user->notify($notice);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
