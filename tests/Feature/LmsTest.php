<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Role;
use App\Models\User;
use App\Support\SiteSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', $role)->value('id'),
            'status' => 'Active',
        ]);
    }

    /** A published course with one section of $lessons lessons; the first is a free preview. */
    private function course(array $attributes = [], int $lessons = 3): Course
    {
        $course = Course::create($attributes + [
            'title' => 'Test course',
            'slug' => 'test-course-'.uniqid(),
            'status' => 'Published',
            'published_at' => now()->subDay(),
            'price' => 0,
        ]);

        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Part one', 'position' => 0]);

        for ($i = 0; $i < $lessons; $i++) {
            Lesson::create([
                'course_id' => $course->id, 'course_section_id' => $section->id,
                'title' => 'Lesson '.($i + 1), 'type' => 'article', 'position' => $i, 'is_preview' => $i === 0,
            ]);
        }

        return $course;
    }

    public function test_a_course_page_carries_valid_structured_data(): void
    {
        $course = $this->course(['title' => 'Structured']);

        $html = $this->get(route('courses.show', $course->slug))->assertOk()->getContent();
        preg_match('#<script type="application/ld\+json">\s*(.*?)\s*</script>#s', $html, $match);
        $data = json_decode($match[1] ?? '', true);

        // Blade reads a bare @context as its own directive; the key must survive.
        $this->assertSame('https://schema.org', $data['@context'] ?? null);
        $this->assertSame('Course', $data['@type']);
    }

    // ── access ─────────────────────────────────────────────────────────────

    public function test_students_are_kept_out_of_the_admin_panel(): void
    {
        $student = $this->user('student');

        $this->actingAs($student)->get('/admin')->assertRedirect(route('student.dashboard'));
        $this->actingAs($student)->getJson('/admin/api/dashboard')->assertForbidden();
    }

    public function test_the_admin_sign_in_refuses_student_accounts(): void
    {
        $student = $this->user('student');

        $this->post('/admin/login', ['email' => $student->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_visitor_can_register_and_lands_on_their_dashboard(): void
    {
        $this->post('/register', [
            'name' => 'New Student', 'email' => 'new@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertTrue(User::where('email', 'new@example.com')->first()->isStudent());
    }

    public function test_registration_can_be_closed(): void
    {
        app(SiteSettings::class)->save('lms', ['allow_registration' => false]);

        $this->get('/register')->assertRedirect(route('student.login'));
        $this->post('/register', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertForbidden();
    }

    public function test_sign_in_only_follows_redirects_to_this_site(): void
    {
        $student = $this->user('student');

        $this->get('/login?redirect=//evil.example/steal');
        $this->post('/login', ['email' => $student->email, 'password' => 'password'])
            ->assertRedirect(route('student.dashboard'));
    }

    // ── the catalogue and the player ───────────────────────────────────────

    public function test_drafts_are_hidden_from_visitors(): void
    {
        $draft = $this->course(['status' => 'Draft', 'published_at' => null]);

        $this->get('/courses/'.$draft->slug)->assertNotFound();
        $this->get('/courses')->assertOk()->assertDontSee($draft->title);
    }

    public function test_preview_lessons_are_open_but_the_rest_need_enrollment(): void
    {
        $course = $this->course();
        [$preview, $locked] = $course->orderedLessons()->all();

        $this->get(route('learn.show', [$course->slug, $preview->id]))->assertOk()->assertSee($preview->title);
        $this->get(route('learn.show', [$course->slug, $locked->id]))->assertRedirect(route('student.login'));

        $student = $this->user('student');
        $this->actingAs($student)->get(route('learn.show', [$course->slug, $locked->id]))
            ->assertRedirect(route('courses.show', $course->slug));
    }

    public function test_a_free_course_opens_with_one_click(): void
    {
        $course = $this->course();
        $student = $this->user('student');

        $this->actingAs($student)->post(route('courses.enroll', $course->slug))
            ->assertRedirect(route('learn.show', $course->slug));

        $this->assertTrue($student->activeEnrollmentFor($course) !== null);
    }

    public function test_a_paid_course_waits_for_approval_unless_auto_approve_is_on(): void
    {
        $course = $this->course(['price' => 1500]);
        $student = $this->user('student');

        $this->actingAs($student)->post(route('courses.enroll', $course->slug))
            ->assertRedirect(route('courses.checkout', $course->slug));

        $this->actingAs($student)->post(route('courses.pay', $course->slug), [
            'method' => 'bKash', 'amount' => 1500, 'sender_number' => '01711111111', 'transaction_id' => 'TX1',
        ])->assertRedirect(route('student.payments'));

        $enrollment = Enrollment::where('user_id', $student->id)->first();
        $this->assertSame('Pending', $enrollment->status);
        $this->assertSame('TX1', $enrollment->transaction_id);
        $this->assertEquals(1500, (float) $enrollment->amount);

        // With auto-approve, the same checkout opens the course at once.
        app(SiteSettings::class)->save('lms', ['auto_approve' => true]);
        $other = $this->user('student');
        $this->actingAs($other)->post(route('courses.pay', $course->slug), [
            'method' => 'bKash', 'amount' => 1500, 'sender_number' => '01722222222', 'transaction_id' => 'TX2',
        ])->assertRedirect(route('learn.show', $course->slug));
        $this->assertNotNull($other->activeEnrollmentFor($course));
    }

    public function test_finishing_every_lesson_completes_the_course_and_unlocks_the_certificate(): void
    {
        $course = $this->course([], 2);
        $student = $this->user('student');
        $enrollment = Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'Active', 'enrolled_at' => now()]);

        $this->actingAs($student)->get(route('student.certificate', $enrollment))->assertRedirect(route('student.dashboard'));

        foreach ($course->orderedLessons() as $lesson) {
            $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->id]))->assertRedirect();
        }

        $this->assertNotNull($enrollment->fresh()->completed_at);
        $this->actingAs($student)->get(route('student.certificate', $enrollment))->assertOk()->assertSee($student->name);

        // Once earned, kept: un-ticking a lesson or adding one does not take it away.
        $lesson = $course->orderedLessons()->first();
        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $lesson->id]), ['undo' => 1]);
        $this->assertNotNull($enrollment->fresh()->completed_at);

        Lesson::create(['course_id' => $course->id, 'course_section_id' => $lesson->course_section_id, 'title' => 'New', 'type' => 'article']);
        $enrollment->fresh()->syncCompletion();
        $this->assertNotNull($enrollment->fresh()->completed_at);
    }

    public function test_lessons_open_in_order(): void
    {
        $course = $this->course([], 3);
        [$first, $second, $third] = $course->orderedLessons()->all();
        $student = $this->user('student');
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'Active']);

        // Nothing done yet: the second and third wait, and send the student back to the first.
        $this->actingAs($student)->get(route('learn.show', [$course->slug, $third->id]))
            ->assertRedirect(route('learn.show', [$course->slug, $first->id]));
        $this->actingAs($student)->get(route('learn.show', [$course->slug, $first->id]))
            ->assertOk()->assertSee('complete this lesson to unlock');

        // A locked lesson cannot be ticked off by posting the form directly.
        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $third->id]));
        $this->assertFalse(LessonCompletion::where('lesson_id', $third->id)->exists());

        // Finishing the first opens the second — and only the second.
        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $first->id]), ['next' => $second->id])
            ->assertRedirect(route('learn.show', [$course->slug, $second->id]));
        $this->actingAs($student)->get(route('learn.show', [$course->slug, $second->id]))->assertOk();
        $this->actingAs($student)->get(route('learn.show', [$course->slug, $third->id]))
            ->assertRedirect(route('learn.show', [$course->slug, $second->id]));
    }

    public function test_lesson_order_can_be_switched_off_and_never_holds_back_staff(): void
    {
        $course = $this->course([], 3);
        $last = $course->orderedLessons()->last();
        $url = route('learn.show', [$course->slug, $last->id]);

        $this->actingAs($this->user('super-admin'))->get($url)->assertOk();

        $student = $this->user('student');
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'Active']);
        $this->actingAs($student)->get($url)->assertRedirect();

        app(SiteSettings::class)->save('lms', ['sequential' => false]);
        $this->actingAs($student)->get($url)->assertOk();
    }

    public function test_a_watched_video_is_marked_done_in_the_background(): void
    {
        $course = $this->course([], 2);
        [$first, $second] = $course->orderedLessons()->all();
        $student = $this->user('student');
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'Active']);

        // The player knows where to report, and where to go next once done.
        $this->actingAs($student)->get(route('learn.show', [$course->slug, $first->id]))
            ->assertSee('data-track="'.route('learn.complete', [$course->slug, $first->id]).'"', false)
            ->assertSee('data-next-after="'.route('learn.show', [$course->slug, $second->id]).'"', false);

        $this->actingAs($student)->postJson(route('learn.complete', [$course->slug, $first->id]))
            ->assertOk()
            ->assertJson(['done' => true, 'progress' => 50, 'done_count' => 1, 'total' => 2, 'course_complete' => false]);

        $this->actingAs($student)->postJson(route('learn.complete', [$course->slug, $second->id]))
            ->assertJson(['progress' => 100, 'just_finished' => true]);
    }

    public function test_new_students_get_a_getting_started_checklist(): void
    {
        $student = $this->user('student');

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertSee('Getting started')->assertSee('1 of 5 done')->assertSee('Join your first course');
    }

    public function test_nobody_else_can_open_a_students_certificate(): void
    {
        $course = $this->course([], 1);
        $owner = $this->user('student');
        $enrollment = Enrollment::create(['user_id' => $owner->id, 'course_id' => $course->id, 'status' => 'Active', 'completed_at' => now()]);

        $this->actingAs($this->user('student'))->get(route('student.certificate', $enrollment))->assertForbidden();
    }

    public function test_only_enrolled_students_can_review(): void
    {
        $course = $this->course();
        $student = $this->user('student');

        $this->actingAs($student)->post(route('courses.review', $course->slug), ['rating' => 5])->assertForbidden();

        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'Active']);
        $this->actingAs($student)->post(route('courses.review', $course->slug), ['rating' => 5, 'comment' => 'Great'])->assertRedirect();
        $this->assertEquals(5.0, $course->averageRating());
    }

    // ── the admin API ──────────────────────────────────────────────────────

    public function test_an_admin_can_build_a_course_and_its_curriculum(): void
    {
        $admin = $this->user('super-admin');

        $id = $this->actingAs($admin)->postJson('/admin/api/courses', [
            'title' => 'Brand new course', 'status' => 'Draft', 'price' => 500, 'sale_price' => 300,
            'outcomes' => ['One', '', 'Two'],
        ])->assertCreated()->json('data.id');

        $course = Course::find($id);
        $this->assertSame('brand-new-course', $course->slug);
        $this->assertSame(['One', 'Two'], $course->outcomes);
        $this->assertEquals(300, $course->currentPrice());

        $tree = $this->actingAs($admin)->putJson("/admin/api/courses/{$id}/curriculum", ['sections' => [
            ['title' => 'Intro', 'lessons' => [
                ['title' => 'Hello', 'type' => 'video', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'duration_minutes' => 3, 'is_preview' => true],
                ['title' => 'Notes', 'type' => 'article', 'body' => '<p>Read me</p>'],
            ]],
        ]])->assertOk()->json('data');

        $this->assertCount(2, $tree[0]['lessons']);
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&modestbranding=1&iv_load_policy=3&playsinline=1&color=white',
            Lesson::where('title', 'Hello')->first()->embedUrl()
        );

        // Saving again keeps lesson ids, so student progress survives edits.
        $helloId = $tree[0]['lessons'][0]['id'];
        $tree[0]['lessons'] = [$tree[0]['lessons'][0]];
        $again = $this->actingAs($admin)->putJson("/admin/api/courses/{$id}/curriculum", ['sections' => $tree])->assertOk()->json('data');
        $this->assertSame($helloId, $again[0]['lessons'][0]['id']);
        $this->assertSame(1, Lesson::where('course_id', $id)->count());
    }

    public function test_a_sale_price_must_be_below_the_price(): void
    {
        $this->actingAs($this->user('super-admin'))->postJson('/admin/api/courses', [
            'title' => 'X', 'status' => 'Draft', 'price' => 100, 'sale_price' => 200,
        ])->assertJsonValidationErrors('sale_price');
    }

    public function test_the_curriculum_cannot_take_another_courses_lessons(): void
    {
        $admin = $this->user('super-admin');
        $mine = $this->course([], 0);
        $theirs = $this->course([], 1);
        $foreign = $theirs->lessons()->first();

        $this->actingAs($admin)->putJson("/admin/api/courses/{$mine->id}/curriculum", ['sections' => [
            ['title' => 'S', 'lessons' => [['id' => $foreign->id, 'title' => 'Hijack', 'type' => 'article']]],
        ]])->assertOk();

        $this->assertSame($theirs->id, $foreign->fresh()->course_id);
        $this->assertSame('Lesson 1', $foreign->fresh()->title);
    }

    public function test_instructors_edit_only_their_own_courses(): void
    {
        $instructor = $this->user('instructor');
        $own = $this->course(['user_id' => $instructor->id]);
        $other = $this->course();

        $this->actingAs($instructor)->putJson("/admin/api/courses/{$own->id}", ['title' => 'Mine'])->assertOk();
        $this->actingAs($instructor)->putJson("/admin/api/courses/{$other->id}", ['title' => 'Not mine'])->assertForbidden();
        $this->actingAs($instructor)->deleteJson("/admin/api/courses/{$own->id}")->assertForbidden();
    }

    public function test_instructors_only_see_enrollments_on_their_courses(): void
    {
        $instructor = $this->user('instructor');
        $own = $this->course(['user_id' => $instructor->id]);
        $other = $this->course();
        Enrollment::create(['user_id' => $this->user('student')->id, 'course_id' => $own->id, 'status' => 'Active']);
        Enrollment::create(['user_id' => $this->user('student')->id, 'course_id' => $other->id, 'status' => 'Active']);

        $rows = $this->actingAs($instructor)->getJson('/admin/api/enrollments')->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame($own->id, $rows[0]['course_id']);
    }

    public function test_an_admin_can_enroll_a_new_person_and_approve_a_pending_one(): void
    {
        $admin = $this->user('super-admin');
        $course = $this->course(['price' => 900]);

        $response = $this->actingAs($admin)->postJson('/admin/api/enrollments', [
            'course_id' => $course->id, 'email' => 'walkin@example.com', 'name' => 'Walk In', 'amount' => 900, 'payment_method' => 'Cash',
        ])->assertCreated();

        $this->assertNotEmpty($response->json('password'));
        $walkIn = User::where('email', 'walkin@example.com')->first();
        $this->assertTrue($walkIn->isStudent());
        $this->assertNotNull($walkIn->activeEnrollmentFor($course));

        $pending = Enrollment::create(['user_id' => $this->user('student')->id, 'course_id' => $course->id, 'status' => 'Pending']);
        $this->actingAs($admin)->putJson("/admin/api/enrollments/{$pending->id}", ['status' => 'Active'])->assertOk();
        $this->assertNotNull($pending->fresh()->enrolled_at);
    }

    public function test_lms_settings_save(): void
    {
        $this->actingAs($this->user('super-admin'))->putJson('/admin/api/settings/lms', [
            'currency' => '$', 'allow_registration' => true, 'auto_approve' => false,
            'payment_methods' => "Card\nCash", 'payment_instructions' => 'Pay us', 'max_video_mb' => 500, 'sequential' => true, 'certificates' => true,
            'reviews' => false, 'certificate_text' => 'finished', 'certificate_signer' => '',
        ])->assertOk();

        $this->assertSame('$', app(SiteSettings::class)->get('lms.currency'));
        $this->assertFalse((bool) app(SiteSettings::class)->get('lms.reviews'));
    }

    public function test_courses_go_to_the_trash_and_come_back(): void
    {
        $admin = $this->user('super-admin');
        $course = $this->course();

        $this->actingAs($admin)->deleteJson("/admin/api/courses/{$course->id}")->assertOk();
        $this->get('/courses/'.$course->slug)->assertNotFound();

        $this->actingAs($admin)->getJson('/admin/api/trash?type=courses')->assertOk()->assertJsonPath('data.0.id', $course->id);
        $this->actingAs($admin)->postJson('/admin/api/trash/courses/restore', ['ids' => [$course->id]])->assertOk();
        $this->assertNull($course->fresh()->deleted_at);
    }

    public function test_every_lms_admin_screen_answers(): void
    {
        $admin = $this->user('super-admin');
        $course = $this->course();

        foreach (['/admin/api/courses', '/admin/api/courses/options', "/admin/api/courses/{$course->id}",
            "/admin/api/courses/{$course->id}/curriculum", '/admin/api/enrollments', '/admin/api/enrollments/options',
            '/admin/api/students', '/admin/api/dashboard', '/admin/api/settings'] as $url) {
            $this->actingAs($admin)->getJson($url)->assertOk();
        }
    }

    public function test_public_pages_render(): void
    {
        $course = $this->course(['price' => 100]);
        $student = $this->user('student');
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'Active']);

        $this->get('/')->assertOk()->assertSee($course->title);
        $this->get('/courses?level=Beginner&price=paid&sort=popular')->assertOk();
        $this->get('/courses/'.$course->slug)->assertOk()->assertSee('Course content');
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->actingAs($student)->get('/my-courses')->assertOk()->assertSee($course->title);
        $this->actingAs($student)->get('/my-account')->assertOk();
        $this->actingAs($student)->get(route('learn.show', $course->slug))->assertOk();
    }
}
