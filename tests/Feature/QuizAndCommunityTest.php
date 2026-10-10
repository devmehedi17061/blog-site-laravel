<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAndCommunityTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private Lesson $quiz;

    private Lesson $after;

    private QuizQuestion $single;

    private QuizQuestion $multiple;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->course = Course::create(['title' => 'C', 'slug' => 'c', 'status' => 'Published', 'published_at' => now()->subDay(), 'price' => 0]);
        $section = CourseSection::create(['course_id' => $this->course->id, 'title' => 'S']);
        $this->quiz = Lesson::create(['course_id' => $this->course->id, 'course_section_id' => $section->id, 'title' => 'Quiz', 'type' => 'quiz', 'pass_mark' => 100, 'max_attempts' => 2, 'position' => 0]);
        $this->after = Lesson::create(['course_id' => $this->course->id, 'course_section_id' => $section->id, 'title' => 'After', 'type' => 'article', 'position' => 1]);

        $this->single = QuizQuestion::create(['lesson_id' => $this->quiz->id, 'question' => '2 + 2?', 'type' => 'single', 'position' => 0,
            'options' => [['text' => '3', 'correct' => false], ['text' => '4', 'correct' => true]]]);
        $this->multiple = QuizQuestion::create(['lesson_id' => $this->quiz->id, 'question' => 'Even numbers?', 'type' => 'multiple', 'position' => 1,
            'options' => [['text' => '2', 'correct' => true], ['text' => '3', 'correct' => false], ['text' => '4', 'correct' => true]]]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'status' => 'Active']);
    }

    private function student(): User
    {
        $student = $this->user('student');
        Enrollment::create(['user_id' => $student->id, 'course_id' => $this->course->id, 'status' => 'Active']);

        return $student;
    }

    private function take(User $student, array $single, array $multiple)
    {
        return $this->actingAs($student)->post(route('learn.quiz', [$this->course->slug, $this->quiz->id]), [
            'answers' => [$this->single->id => $single, $this->multiple->id => $multiple],
        ]);
    }

    // ── quizzes ───────────────────────────────────────────────────────────

    public function test_a_multiple_choice_answer_is_right_only_with_exactly_the_right_options(): void
    {
        $this->assertTrue($this->multiple->isRight([0, 2]));
        $this->assertTrue($this->multiple->isRight(['2', '0']));
        $this->assertFalse($this->multiple->isRight([0]));
        $this->assertFalse($this->multiple->isRight([0, 1, 2]));
    }

    public function test_failing_keeps_the_next_lesson_locked_and_passing_opens_it(): void
    {
        $student = $this->student();

        $this->take($student, [0], [0, 2])->assertRedirect();
        $attempt = QuizAttempt::first();
        $this->assertSame(50, $attempt->score);
        $this->assertFalse($attempt->passed);
        $this->actingAs($student)->get(route('learn.show', [$this->course->slug, $this->after->id]))
            ->assertRedirect(route('learn.show', [$this->course->slug, $this->quiz->id]));

        $this->take($student, [1], [2, 0]);
        $this->assertTrue(QuizAttempt::latest('id')->first()->passed);
        $this->assertTrue(LessonCompletion::where('lesson_id', $this->quiz->id)->exists());
        $this->actingAs($student)->get(route('learn.show', [$this->course->slug, $this->after->id]))->assertOk();
    }

    public function test_the_attempt_limit_holds_and_then_answers_are_revealed(): void
    {
        $student = $this->student();
        $this->take($student, [0], [0]);
        $this->take($student, [0], [0]);
        $this->take($student, [1], [0, 2])->assertSessionHas('status', 'You have used all 2 attempts on this quiz.');

        $this->assertSame(2, QuizAttempt::count());
        $this->actingAs($student)->get(route('learn.show', [$this->course->slug, $this->quiz->id]))
            ->assertSee('Right answer')->assertSee('You have used all your tries');
    }

    public function test_a_quiz_cannot_be_ticked_off_without_passing(): void
    {
        $student = $this->student();

        $this->actingAs($student)->post(route('learn.complete', [$this->course->slug, $this->quiz->id]))->assertForbidden();
        $this->assertFalse(LessonCompletion::exists());
    }

    public function test_unanswered_questions_are_sent_back(): void
    {
        $student = $this->student();

        $this->actingAs($student)->post(route('learn.quiz', [$this->course->slug, $this->quiz->id]), ['answers' => [$this->single->id => [1]]])
            ->assertSessionHas('quiz_error');
        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_staff_can_try_a_quiz_without_it_being_recorded(): void
    {
        $this->take($this->user('super-admin'), [1], [0, 2])->assertSessionHas('quiz_preview');
        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_quizzes_are_built_in_the_curriculum(): void
    {
        $admin = $this->user('super-admin');
        $tree = $this->actingAs($admin)->getJson("/admin/api/courses/{$this->course->id}/curriculum")->json('data');
        $this->assertCount(2, $tree[0]['lessons'][0]['questions']);

        // A single-answer question with two right answers is refused.
        $tree[0]['lessons'][0]['questions'][0]['options'][0]['correct'] = true;
        $this->actingAs($admin)->putJson("/admin/api/courses/{$this->course->id}/curriculum", ['sections' => $tree])
            ->assertJsonValidationErrors('sections.0.lessons.0.questions.0.options');

        $tree[0]['lessons'][0]['questions'][0]['options'][0]['correct'] = false;
        $tree[0]['lessons'][0]['questions'][] = ['question' => 'True?', 'type' => 'true_false', 'options' => [['text' => 'True', 'correct' => true], ['text' => 'False', 'correct' => false]]];
        $this->actingAs($admin)->putJson("/admin/api/courses/{$this->course->id}/curriculum", ['sections' => $tree])->assertOk();

        $this->assertSame(3, $this->quiz->questions()->count());
        $this->assertSame($this->single->id, $this->quiz->questions()->first()->id);
    }

    // ── Q&A ───────────────────────────────────────────────────────────────

    public function test_students_ask_and_are_notified_of_the_instructors_reply(): void
    {
        $student = $this->student();
        LessonCompletion::create(['user_id' => $student->id, 'lesson_id' => $this->quiz->id, 'course_id' => $this->course->id]);

        $this->actingAs($student)->post(route('discussions.store', [$this->course->slug, $this->after->id]), ['body' => 'How does this work?'])->assertRedirect();
        $question = Discussion::first();

        $this->actingAs($this->user('student'))->post(route('discussions.store', [$this->course->slug, $this->after->id]), ['body' => 'Me too'])->assertForbidden();

        $this->actingAs($this->user('super-admin'))->postJson("/admin/api/discussions/{$question->id}/reply", ['body' => 'Like this.'])->assertOk();

        $this->assertTrue($question->fresh()->is_resolved);
        $this->assertStringContainsString('replied', $student->notifications()->first()->data['title']);
        $this->actingAs($student)->get(route('learn.show', [$this->course->slug, $this->after->id]))->assertSee('Like this.')->assertSee('Instructor');
    }

    public function test_the_admin_q_and_a_lists_open_questions_first(): void
    {
        $student = $this->student();
        Discussion::create(['course_id' => $this->course->id, 'lesson_id' => $this->quiz->id, 'user_id' => $student->id, 'body' => 'Old, answered', 'is_resolved' => true]);
        Discussion::create(['course_id' => $this->course->id, 'lesson_id' => $this->quiz->id, 'user_id' => $student->id, 'body' => 'Still open']);

        $payload = $this->actingAs($this->user('super-admin'))->getJson('/admin/api/discussions?status=All')->assertOk()->json();

        $this->assertSame('Still open', $payload['data'][0]['body']);
        $this->assertSame(1, $payload['summary']['Open']);
    }

    // ── announcements, wishlist, certificate check ────────────────────────

    public function test_announcements_reach_every_student_on_the_course(): void
    {
        $students = [$this->student(), $this->student()];

        $this->actingAs($this->user('super-admin'))->postJson("/admin/api/courses/{$this->course->id}/announcements", [
            'title' => 'New lesson', 'body' => 'Go and see it.',
        ])->assertCreated()->assertJsonPath('message', 'Announcement sent to 2 students.');

        foreach ($students as $student) {
            $this->assertSame(1, $student->notifications()->count());
        }
        $this->actingAs($students[0])->get(route('courses.show', $this->course->slug))->assertSee('Go and see it.');
    }

    public function test_the_wishlist_toggles(): void
    {
        $student = $this->user('student');

        $this->actingAs($student)->post(route('wishlist.toggle', $this->course->slug))->assertRedirect();
        $this->assertSame(1, $student->wishlist()->count());
        $this->actingAs($student)->get(route('student.wishlist'))->assertOk()->assertSee($this->course->title);

        $this->actingAs($student)->post(route('wishlist.toggle', $this->course->slug));
        $this->assertSame(0, $student->wishlist()->count());
    }

    public function test_certificates_can_be_verified_but_not_guessed(): void
    {
        $student = $this->student();
        $enrollment = Enrollment::where('user_id', $student->id)->first();
        $enrollment->update(['completed_at' => now()]);
        $serial = $enrollment->certificateSerial();

        $this->get(route('certificates.verify', ['serial' => strtolower($serial)]))->assertOk()->assertSee('Genuine certificate')->assertSee($student->name);

        // The id and date alone are not enough.
        $guess = preg_replace('/-[A-Z0-9]+$/', '-AAAAAA', $serial);
        $this->get(route('certificates.verify', ['serial' => $guess]))->assertOk()->assertSee('No certificate with this number')->assertDontSee($student->name);
    }
}
