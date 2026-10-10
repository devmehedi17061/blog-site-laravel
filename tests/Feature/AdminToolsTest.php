<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminToolsTest extends TestCase
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

    private function course(): Course
    {
        $course = Course::create(['title' => 'Original', 'slug' => 'original-'.uniqid(), 'status' => 'Published', 'published_at' => now(), 'price' => 1000, 'is_featured' => true]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'S']);
        Lesson::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'title' => 'Read', 'type' => 'article']);
        $quiz = Lesson::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'title' => 'Quiz', 'type' => 'quiz', 'position' => 1]);
        QuizQuestion::create(['lesson_id' => $quiz->id, 'question' => 'Q?', 'type' => 'single', 'options' => [['text' => 'A', 'correct' => true], ['text' => 'B', 'correct' => false]]]);

        return $course;
    }

    private function payment(Course $course, array $attributes = []): Payment
    {
        $student = $this->user('student');

        return Payment::create($attributes + [
            'user_id' => $student->id, 'course_id' => $course->id, 'amount' => 1000, 'expected_amount' => 1000,
            'method' => 'bKash', 'transaction_id' => 'TX'.uniqid(), 'status' => 'Pending',
        ]);
    }

    public function test_a_course_is_duplicated_as_a_draft_with_its_curriculum_and_quizzes(): void
    {
        $course = $this->course();

        $id = $this->actingAs($this->user('super-admin'))->postJson("/admin/api/courses/{$course->id}/duplicate")->assertCreated()->json('id');

        $copy = Course::find($id);
        $this->assertSame('Draft', $copy->status);
        $this->assertFalse($copy->is_featured);
        $this->assertNotSame($course->slug, $copy->slug);
        $this->assertSame(2, $copy->lessons()->count());
        $this->assertSame(1, $copy->lessons()->where('type', 'quiz')->first()->questions()->count());
    }

    public function test_several_payments_are_verified_at_once(): void
    {
        $course = $this->course();
        $a = $this->payment($course);
        $b = $this->payment($course);
        $done = $this->payment($course, ['status' => 'Rejected']);

        $this->actingAs($this->user('super-admin'))->postJson('/admin/api/payments/bulk-verify', ['ids' => [$a->id, $b->id, $done->id]])
            ->assertOk()->assertJsonPath('message', '2 payments verified — each student has been notified.');

        $this->assertSame('Verified', $a->fresh()->status);
        $this->assertSame('Rejected', $done->fresh()->status);
        $this->assertNotNull($a->student->activeEnrollmentFor($course));
    }

    public function test_payments_and_students_download_as_csv(): void
    {
        $course = $this->course();
        $this->payment($course, ['transaction_id' => '=HYPERLINK("x")']);
        $admin = $this->user('super-admin');

        $csv = $this->actingAs($admin)->get('/admin/api/payments/export?status=Pending')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Transaction ID', $csv);
        // A formula in a cell is defused, so opening the file in Excel runs nothing.
        $this->assertStringContainsString("'=HYPERLINK", $csv);

        $this->actingAs($admin)->get('/admin/api/students/export')->assertOk();
    }

    public function test_reports_add_up(): void
    {
        $course = $this->course();
        $this->payment($course, ['status' => 'Verified', 'amount' => 800]);
        $quiz = $course->lessons()->where('type', 'quiz')->first();
        $question = $quiz->questions()->first();
        $student = $this->user('student');
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'Active']);
        QuizAttempt::create(['user_id' => $student->id, 'lesson_id' => $quiz->id, 'score' => 0, 'earned' => 0, 'possible' => 1, 'passed' => false, 'answers' => [$question->id => [1]]]);
        QuizAttempt::create(['user_id' => $student->id, 'lesson_id' => $quiz->id, 'score' => 100, 'earned' => 1, 'possible' => 1, 'passed' => true, 'answers' => [$question->id => [0]]]);

        $report = $this->actingAs($this->user('super-admin'))->getJson('/admin/api/reports')->assertOk()->json();

        $this->assertCount(12, $report['months']);
        $this->assertSame(800.0, (float) end($report['months'])['revenue']);
        $this->assertSame(50, $report['quizzes']['hardest'][0]['wrong']);
        $this->assertSame(50, $report['quizzes']['summary'][0]['pass_rate']);

        // An instructor only sees their own courses.
        $this->assertSame([], $this->actingAs($this->user('instructor'))->getJson('/admin/api/reports')->json('courses'));
    }
}
