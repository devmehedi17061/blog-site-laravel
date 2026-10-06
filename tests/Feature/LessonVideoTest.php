<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('lessons');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'status' => 'Active']);
    }

    private function course(): Course
    {
        return Course::create([
            'title' => 'Video course', 'slug' => 'video-course-'.uniqid(),
            'status' => 'Published', 'published_at' => now()->subDay(), 'price' => 100,
        ]);
    }

    /** Enough of an MP4 for finfo to call it video/mp4. */
    private function mp4Bytes(int $padding = 300): string
    {
        return "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", $padding);
    }

    /** Sends $bytes in $pieces chunks, as the editor does; returns the last answer. */
    private function upload(User $as, Course $course, string $bytes, string $name = 'lesson.mp4', int $pieces = 3)
    {
        $size = (int) ceil(strlen($bytes) / $pieces);
        $response = null;

        for ($i = 0; $i < $pieces; $i++) {
            $response = $this->actingAs($as)->post("/admin/api/courses/{$course->id}/lesson-video", [
                'upload_id' => 'abcdefghijklmnop1234',
                'index' => $i,
                'total' => $pieces,
                'name' => $name,
                'chunk' => UploadedFile::fake()->createWithContent("{$i}.part", substr($bytes, $i * $size, $size)),
            ], ['Accept' => 'application/json']);
        }

        return $response;
    }

    private function lessonWithVideo(Course $course, string $path, bool $preview = false): Lesson
    {
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'S']);

        return Lesson::create([
            'course_id' => $course->id, 'course_section_id' => $section->id, 'title' => 'Watch me',
            'type' => 'video', 'video_path' => $path, 'video_name' => 'lesson.mp4', 'is_preview' => $preview,
        ]);
    }

    public function test_a_video_uploaded_in_chunks_is_put_back_together(): void
    {
        $admin = $this->user('super-admin');
        $course = $this->course();
        $bytes = $this->mp4Bytes();

        $response = $this->upload($admin, $course, $bytes)->assertOk()->assertJsonPath('done', true);

        $path = $response->json('video.path');
        $this->assertMatchesRegularExpression('~^'.$course->id.'/[A-Za-z0-9]{40}\.mp4$~', $path);
        $this->assertSame($bytes, Storage::disk('lessons')->get($path));
        $this->assertSame(strlen($bytes), $response->json('video.size'));
        // The pieces are gone once joined.
        $this->assertEmpty(Storage::disk('lessons')->allFiles('chunks'));
    }

    public function test_files_that_are_not_videos_are_refused(): void
    {
        $admin = $this->user('super-admin');
        $course = $this->course();

        $this->upload($admin, $course, 'hello', 'notes.txt', 1)->assertStatus(422);
        $this->upload($admin, $course, str_repeat('<?php echo 1; ?>', 50), 'evil.mp4', 1)->assertStatus(422);
        $this->assertEmpty(Storage::disk('lessons')->files((string) $course->id));
    }

    public function test_only_people_who_can_edit_the_course_may_upload(): void
    {
        $instructor = $this->user('instructor');
        $course = $this->course(); // someone else's

        $this->upload($instructor, $course, $this->mp4Bytes(), 'x.mp4', 1)->assertForbidden();
    }

    public function test_a_lesson_can_only_use_a_video_its_own_course_uploaded(): void
    {
        $admin = $this->user('super-admin');
        $course = $this->course();
        $other = $this->course();
        $theirs = $this->upload($admin, $other, $this->mp4Bytes())->json('video.path');
        $mine = $this->upload($admin, $course, $this->mp4Bytes())->json('video.path');

        $lesson = fn (string $path) => ['sections' => [['title' => 'S', 'lessons' => [
            ['title' => 'Video', 'type' => 'video', 'video_path' => $path, 'video_name' => 'a.mp4'],
        ]]]];

        $this->actingAs($admin)->putJson("/admin/api/courses/{$course->id}/curriculum", $lesson($theirs))
            ->assertJsonValidationErrors('sections.0.lessons.0.video_path');
        $this->actingAs($admin)->putJson("/admin/api/courses/{$course->id}/curriculum", $lesson('../../.env'))
            ->assertJsonValidationErrors('sections.0.lessons.0.video_path');

        $this->actingAs($admin)->putJson("/admin/api/courses/{$course->id}/curriculum", $lesson($mine))->assertOk();
        $this->assertSame($mine, Lesson::where('course_id', $course->id)->value('video_path'));
    }

    public function test_only_enrolled_students_can_stream_the_video(): void
    {
        $course = $this->course();
        $path = $this->upload($this->user('super-admin'), $course, $this->mp4Bytes())->json('video.path');
        $lesson = $this->lessonWithVideo($course, $path);
        $url = route('learn.video', [$course->slug, $lesson->id]);

        // The upload signed the admin in; the rest is about everyone else.
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertForbidden();
        $student = $this->user('student');
        $this->actingAs($student)->get($url)->assertForbidden();

        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'Pending']);
        $this->actingAs($student)->get($url)->assertForbidden();

        Enrollment::where('user_id', $student->id)->update(['status' => 'Active']);
        $this->actingAs($student)->get($url)->assertOk()->assertHeader('Content-Type', 'video/mp4');

        // The lesson page plays it from that address.
        $this->actingAs($student)->get(route('learn.show', [$course->slug, $lesson->id]))->assertSee($url, false);
    }

    public function test_a_video_further_on_waits_for_the_lessons_before_it(): void
    {
        $course = $this->course();
        $path = $this->upload($this->user('super-admin'), $course, $this->mp4Bytes())->json('video.path');
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'S']);
        $first = Lesson::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'title' => 'Read first', 'type' => 'article', 'position' => 0]);
        $video = Lesson::create(['course_id' => $course->id, 'course_section_id' => $section->id, 'title' => 'Then watch', 'type' => 'video', 'video_path' => $path, 'position' => 1]);

        $student = $this->user('student');
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'Active']);
        $url = route('learn.video', [$course->slug, $video->id]);

        $this->actingAs($student)->get($url)->assertForbidden();

        $this->actingAs($student)->post(route('learn.complete', [$course->slug, $first->id]));
        $this->actingAs($student)->get($url)->assertOk();
    }

    public function test_a_free_preview_video_streams_to_anyone(): void
    {
        $course = $this->course();
        $path = $this->upload($this->user('super-admin'), $course, $this->mp4Bytes())->json('video.path');
        $lesson = $this->lessonWithVideo($course, $path, preview: true);
        $this->app['auth']->forgetGuards();

        $this->get(route('learn.video', [$course->slug, $lesson->id]))->assertOk();
    }

    public function test_the_player_can_seek(): void
    {
        $course = $this->course();
        $path = $this->upload($this->user('super-admin'), $course, $this->mp4Bytes(1000))->json('video.path');
        $lesson = $this->lessonWithVideo($course, $path, preview: true);

        $response = $this->get(route('learn.video', [$course->slug, $lesson->id]), ['Range' => 'bytes=100-199']);

        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 100-199/'.strlen($this->mp4Bytes(1000)));
    }

    public function test_removing_or_replacing_a_video_deletes_the_old_file(): void
    {
        $admin = $this->user('super-admin');
        $course = $this->course();
        $first = $this->upload($admin, $course, $this->mp4Bytes())->json('video.path');

        $tree = $this->actingAs($admin)->putJson("/admin/api/courses/{$course->id}/curriculum", ['sections' => [['title' => 'S', 'lessons' => [
            ['title' => 'Video', 'type' => 'video', 'video_path' => $first, 'video_name' => 'a.mp4'],
        ]]]])->json('data');

        $second = $this->upload($admin, $course, $this->mp4Bytes(400))->json('video.path');
        $tree[0]['lessons'][0]['video_path'] = $second;
        $this->actingAs($admin)->putJson("/admin/api/courses/{$course->id}/curriculum", ['sections' => $tree])->assertOk();

        Storage::disk('lessons')->assertMissing($first);
        Storage::disk('lessons')->assertExists($second);

        // Switching the lesson to a link drops the upload too.
        $tree[0]['lessons'][0]['video_path'] = null;
        $tree[0]['lessons'][0]['video_url'] = 'https://youtu.be/dQw4w9WgXcQ';
        $this->actingAs($admin)->putJson("/admin/api/courses/{$course->id}/curriculum", ['sections' => $tree])->assertOk();
        Storage::disk('lessons')->assertMissing($second);
    }

    public function test_deleting_a_course_for_good_deletes_its_videos(): void
    {
        $admin = $this->user('super-admin');
        $course = $this->course();
        $path = $this->upload($admin, $course, $this->mp4Bytes())->json('video.path');

        $course->delete();
        Storage::disk('lessons')->assertExists($path); // still restorable from the Trash

        $course->forceDelete();
        Storage::disk('lessons')->assertMissing($path);
    }
}
