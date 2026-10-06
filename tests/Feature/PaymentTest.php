<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Support\SiteSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'status' => 'Active']);
    }

    private function course(array $attributes = []): Course
    {
        $course = Course::create($attributes + [
            'title' => 'Paid course', 'slug' => 'paid-'.uniqid(),
            'status' => 'Published', 'published_at' => now()->subDay(), 'price' => 1500,
        ]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'S']);
        Lesson::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'title' => 'L1', 'type' => 'article']);

        return $course;
    }

    private function pay(User $student, Course $course, array $overrides = [])
    {
        return $this->actingAs($student)->post(route('courses.pay', $course->slug), $overrides + [
            'method' => 'bKash', 'amount' => 1500, 'sender_number' => '01711111111', 'transaction_id' => 'BK123456',
        ]);
    }

    public function test_a_payment_with_a_screenshot_waits_for_verification(): void
    {
        $student = $this->user('student');
        $course = $this->course();

        $this->pay($student, $course, ['proof' => UploadedFile::fake()->image('bkash.png')])
            ->assertRedirect(route('student.payments'));

        $payment = Payment::first();
        $this->assertSame('Pending', $payment->status);
        $this->assertSame('01711111111', $payment->sender_number);
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertNull($student->activeEnrollmentFor($course));

        // The student sees it; nobody else can open the screenshot.
        $this->actingAs($student)->get(route('student.payments'))->assertOk()->assertSee('BK123456')->assertSee('Being verified');
        $this->actingAs($student)->get(route('payments.proof', $payment))->assertOk();
        $this->actingAs($this->user('student'))->get(route('payments.proof', $payment))->assertForbidden();
        $this->actingAs($this->user('super-admin'))->get(route('payments.proof', $payment))->assertOk();
    }

    public function test_a_transaction_id_cannot_be_used_twice(): void
    {
        $course = $this->course();
        $this->pay($this->user('student'), $course);

        $this->pay($this->user('student'), $course)->assertSessionHasErrors('transaction_id');
        $this->pay($this->user('student'), $this->course())->assertSessionHasErrors('transaction_id');
        $this->assertSame(1, Payment::count());
    }

    public function test_resending_a_pending_payment_replaces_it(): void
    {
        $student = $this->user('student');
        $course = $this->course();

        $this->pay($student, $course, ['transaction_id' => 'WRONG1']);
        $this->pay($student, $course, ['transaction_id' => 'RIGHT1'])->assertRedirect();

        $this->assertSame(['RIGHT1'], Payment::pluck('transaction_id')->all());
    }

    public function test_verifying_opens_the_course_and_tells_the_student(): void
    {
        $student = $this->user('student');
        $course = $this->course();
        $this->pay($student, $course);
        $payment = Payment::first();

        $this->actingAs($this->user('super-admin'))->postJson("/admin/api/payments/{$payment->id}/verify")->assertOk();

        $this->assertSame('Verified', $payment->fresh()->status);
        $this->assertNotNull($student->activeEnrollmentFor($course));
        $this->assertStringContainsString('Payment verified', $student->notifications()->first()->data['title']);

        // A receipt is there once verified.
        $this->actingAs($student)->get(route('student.receipt', $payment))->assertOk()->assertSee($payment->invoiceNumber());
    }

    public function test_rejecting_keeps_the_course_closed_and_lets_the_student_send_again(): void
    {
        $student = $this->user('student');
        $course = $this->course();
        $this->pay($student, $course);
        $payment = Payment::first();
        $admin = $this->user('super-admin');

        $this->actingAs($admin)->postJson("/admin/api/payments/{$payment->id}/reject", [])->assertJsonValidationErrors('reason');
        $this->actingAs($admin)->postJson("/admin/api/payments/{$payment->id}/reject", ['reason' => 'TXN not found'])->assertOk();

        $this->assertSame('Rejected', $payment->fresh()->status);
        $this->assertSame('Cancelled', Enrollment::first()->status);
        $this->assertNull($student->activeEnrollmentFor($course));
        $this->actingAs($student)->get(route('student.dashboard'))->assertSee('TXN not found');

        // The rejected transaction ID is free again, and a new payment goes back in the queue.
        $this->pay($student, $course)->assertRedirect(route('student.payments'));
        $this->assertSame(1, Payment::where('status', 'Pending')->count());
        $this->assertSame('Pending', Enrollment::first()->status);
    }

    public function test_with_auto_approve_a_rejection_closes_the_course_again(): void
    {
        app(SiteSettings::class)->save('lms', ['auto_approve' => true]);
        $student = $this->user('student');
        $course = $this->course();

        $this->pay($student, $course)->assertRedirect(route('learn.show', $course->slug));
        $this->assertNotNull($student->activeEnrollmentFor($course));

        $payment = Payment::first();
        $this->assertSame('Pending', $payment->status);
        $this->actingAs($this->user('super-admin'))->postJson("/admin/api/payments/{$payment->id}/reject", ['reason' => 'Fake'])->assertOk();
        $this->assertNull($student->activeEnrollmentFor($course));
    }

    public function test_the_payments_screen_flags_duplicates_and_short_payments(): void
    {
        $course = $this->course();
        $a = Payment::create(['user_id' => $this->user('student')->id, 'course_id' => $course->id, 'amount' => 1500, 'expected_amount' => 1500, 'method' => 'bKash', 'transaction_id' => 'SAME', 'status' => 'Verified']);
        Payment::create(['user_id' => $this->user('student')->id, 'course_id' => $course->id, 'amount' => 500, 'expected_amount' => 1500, 'method' => 'bKash', 'transaction_id' => 'SAME', 'status' => 'Pending']);

        $row = $this->actingAs($this->user('super-admin'))->getJson('/admin/api/payments?status=Pending')->assertOk()->json('data.0');

        $this->assertTrue($row['short']);
        $this->assertSame($a->invoiceNumber(), $row['duplicates'][0]['invoice']);
    }

    public function test_approving_an_enrollment_verifies_its_payment(): void
    {
        $student = $this->user('student');
        $course = $this->course();
        $this->pay($student, $course);

        $this->actingAs($this->user('super-admin'))->putJson('/admin/api/enrollments/'.Enrollment::first()->id, ['status' => 'Active'])->assertOk();

        $this->assertSame('Verified', Payment::first()->status);
    }

    public function test_instructors_only_review_payments_for_their_own_courses(): void
    {
        $instructor = $this->user('instructor');
        $theirs = $this->course(['user_id' => $instructor->id]);
        $other = $this->course();
        $this->pay($this->user('student'), $theirs, ['transaction_id' => 'A1']);
        $this->pay($this->user('student'), $other, ['transaction_id' => 'B2']);

        $rows = $this->actingAs($instructor)->getJson('/admin/api/payments')->assertOk()->json('data');
        $this->assertSame(['A1'], array_column($rows, 'transaction_id'));

        // Instructors may look, not decide (no enrollments.update).
        $this->actingAs($instructor)->postJson('/admin/api/payments/'.Payment::where('transaction_id', 'A1')->value('id').'/verify')->assertForbidden();
    }

    public function test_every_student_panel_page_renders(): void
    {
        $student = $this->user('student');
        $course = $this->course();
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'Active', 'completed_at' => now()]);
        $this->pay($student, $this->course(), ['transaction_id' => 'P1']);

        foreach (['student.dashboard', 'student.courses', 'student.payments', 'student.certificates', 'student.notifications', 'student.account'] as $name) {
            $this->actingAs($student)->get(route($name))->assertOk();
        }
        foreach (['progress', 'completed', 'pending'] as $tab) {
            $this->actingAs($student)->get(route('student.courses', ['tab' => $tab]))->assertOk();
        }
    }
}
