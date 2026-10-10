<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'status' => 'Active']);
    }

    private function course(float $price = 1000): Course
    {
        $course = Course::create([
            'title' => 'Course', 'slug' => 'c-'.uniqid(), 'status' => 'Published',
            'published_at' => now()->subDay(), 'price' => $price,
        ]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'S']);
        Lesson::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'title' => 'L', 'type' => 'article']);

        return $course;
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::create($attributes + ['code' => 'SAVE20', 'type' => 'percent', 'value' => 20, 'max_uses_per_user' => 1, 'is_active' => true]);
    }

    private function pay(User $student, Course $course, array $overrides = [])
    {
        return $this->actingAs($student)->post(route('courses.pay', $course->slug), $overrides + [
            'method' => 'bKash', 'amount' => 800, 'sender_number' => '01700000000', 'transaction_id' => 'TX'.uniqid(),
        ]);
    }

    public function test_percent_and_fixed_discounts_never_go_below_zero(): void
    {
        $this->assertEquals(200, $this->coupon()->discountFor(1000));
        $this->assertEquals(300, $this->coupon(['code' => 'F', 'type' => 'fixed', 'value' => 300])->discountFor(1000));
        $this->assertEquals(100, $this->coupon(['code' => 'BIG', 'type' => 'fixed', 'value' => 5000])->discountFor(100));
    }

    public function test_a_coupon_applied_at_checkout_lowers_what_is_expected(): void
    {
        $student = $this->user('student');
        $course = $this->course();
        $coupon = $this->coupon();

        $this->actingAs($student)->post(route('courses.coupon.apply', $course->slug), ['code' => ' save20 '])->assertSessionHasNoErrors();
        $this->actingAs($student)->get(route('courses.checkout', $course->slug))->assertOk()->assertSee('SAVE20')->assertSee('800');

        $this->pay($student, $course)->assertRedirect(route('student.payments'));

        $payment = Payment::first();
        $this->assertSame($coupon->id, $payment->coupon_id);
        $this->assertEquals(200, $payment->discount);
        $this->assertEquals(800, $payment->expected_amount);
        $this->assertSame(1, CouponRedemption::count());
    }

    public function test_invalid_coupons_are_refused_with_a_reason(): void
    {
        $student = $this->user('student');
        $course = $this->course();
        $apply = fn (string $code) => $this->actingAs($student)->post(route('courses.coupon.apply', $course->slug), ['code' => $code]);

        $apply('NOPE')->assertSessionHasErrors(['code' => 'That coupon code does not exist.']);

        $this->coupon(['code' => 'OLD', 'expires_at' => now()->subDay()]);
        $apply('OLD')->assertSessionHasErrors(['code' => 'This coupon has expired.']);

        $this->coupon(['code' => 'SOON', 'starts_at' => now()->addWeek()]);
        $apply('SOON')->assertSessionHasErrors('code');

        $this->coupon(['code' => 'OFF', 'is_active' => false]);
        $apply('OFF')->assertSessionHasErrors(['code' => 'This coupon is not active.']);

        $this->coupon(['code' => 'BIGONLY', 'min_amount' => 5000]);
        $apply('BIGONLY')->assertSessionHasErrors('code');

        $other = $this->coupon(['code' => 'OTHER']);
        $other->courses()->sync([$this->course()->id]);
        $apply('OTHER')->assertSessionHasErrors(['code' => 'This coupon is not for this course.']);
    }

    public function test_usage_limits_count_real_uses_and_a_rejection_gives_the_use_back(): void
    {
        $coupon = $this->coupon(['max_uses' => 1]);
        $course = $this->course();
        $first = $this->user('student');

        $this->actingAs($first)->post(route('courses.coupon.apply', $course->slug), ['code' => 'SAVE20']);
        $this->pay($first, $course);

        $second = $this->user('student');
        $this->actingAs($second)->post(route('courses.coupon.apply', $course->slug), ['code' => 'SAVE20'])
            ->assertSessionHasErrors(['code' => 'This coupon has been fully used.']);

        // The first payment is rejected: its use comes back.
        $this->actingAs($this->user('super-admin'))->postJson('/admin/api/payments/'.Payment::first()->id.'/reject', ['reason' => 'Wrong TXN'])->assertOk();
        $this->assertSame(0, $coupon->redemptions()->count());
        $this->actingAs($second)->post(route('courses.coupon.apply', $course->slug), ['code' => 'SAVE20'])->assertSessionHasNoErrors();
    }

    public function test_resending_a_pending_payment_does_not_use_the_coupon_twice(): void
    {
        $coupon = $this->coupon(['max_uses_per_user' => 1]);
        $student = $this->user('student');
        $course = $this->course();

        $this->actingAs($student)->post(route('courses.coupon.apply', $course->slug), ['code' => 'SAVE20']);
        $this->pay($student, $course, ['transaction_id' => 'WRONG']);
        $this->actingAs($student)->post(route('courses.coupon.apply', $course->slug), ['code' => 'SAVE20'])->assertSessionHasNoErrors();
        $this->pay($student, $course, ['transaction_id' => 'RIGHT']);

        $this->assertSame(1, $coupon->redemptions()->count());
        $this->assertSame('RIGHT', Payment::first()->transaction_id);
    }

    public function test_a_full_discount_opens_the_course_without_a_payment(): void
    {
        $this->coupon(['code' => 'FREE', 'value' => 100]);
        $student = $this->user('student');
        $course = $this->course();

        $this->actingAs($student)->post(route('courses.coupon.apply', $course->slug), ['code' => 'FREE']);
        $this->actingAs($student)->get(route('courses.checkout', $course->slug))->assertSee('Get the course for free');
        $this->actingAs($student)->post(route('courses.pay', $course->slug))->assertRedirect(route('learn.show', $course->slug));

        $this->assertNotNull($student->activeEnrollmentFor($course));
        $this->assertSame(0, Payment::count());
        $this->assertSame(1, CouponRedemption::count());
    }

    public function test_a_promo_link_applies_the_coupon(): void
    {
        $this->coupon();
        $course = $this->course();
        $student = $this->user('student');

        $this->actingAs($student)->get('/courses/'.$course->slug.'?coupon=save20')->assertRedirect(route('courses.show', $course->slug));
        $this->actingAs($student)->get(route('courses.show', $course->slug))->assertSee('SAVE20');

        // A catalogue link works on any course it is valid for.
        $other = $this->course(2000);
        $this->actingAs($student)->get('/courses?coupon=SAVE20')->assertRedirect();
        $this->actingAs($student)->get(route('courses.checkout', $other->slug))->assertSee('1,600');
    }

    public function test_admins_manage_coupons(): void
    {
        $admin = $this->user('super-admin');
        $course = $this->course();

        $id = $this->actingAs($admin)->postJson('/admin/api/coupons', [
            'code' => 'eid-50', 'type' => 'percent', 'value' => 50, 'max_uses' => '', 'max_uses_per_user' => 1,
            'min_amount' => '', 'starts_at' => '', 'expires_at' => '', 'is_active' => true, 'course_ids' => [$course->id],
        ])->assertCreated()->json('data.id');

        $this->assertSame('EID-50', Coupon::find($id)->code);
        $this->assertSame([$course->id], Coupon::find($id)->courses()->pluck('courses.id')->all());

        $this->actingAs($admin)->postJson('/admin/api/coupons', ['code' => 'X', 'type' => 'percent', 'value' => 150, 'max_uses_per_user' => 1, 'is_active' => true])
            ->assertJsonValidationErrors(['code', 'value']);

        $this->actingAs($admin)->getJson('/admin/api/coupons')->assertOk()->assertJsonPath('data.0.code', 'EID-50');
        $this->actingAs($admin)->deleteJson("/admin/api/coupons/{$id}")->assertOk();

        // Instructors cannot hand out discounts.
        $this->actingAs($this->user('instructor'))->getJson('/admin/api/coupons')->assertForbidden();
    }
}
