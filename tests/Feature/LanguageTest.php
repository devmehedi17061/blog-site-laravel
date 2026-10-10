<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SitePart;
use App\Models\User;
use App\Notifications\StudentNotice;
use App\Support\Lms;
use App\Support\SitePartRenderer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function student(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role_id' => Role::where('slug', 'student')->value('id'), 'status' => 'Active']);
    }

    public function test_every_translated_key_has_a_bangla_line(): void
    {
        $bn = json_decode(file_get_contents(lang_path('bn.json')), true, flags: JSON_THROW_ON_ERROR);
        $missing = [];

        foreach (['resources/views/frontend', 'app/Http/Controllers/Site', 'app/Models', 'app/Notifications'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir)));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                preg_match_all("/(?:__|trans_choice)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file->getPathname()), $found);
                foreach ($found[1] as $key) {
                    $key = str_replace("\\'", "'", $key);
                    if (! array_key_exists($key, $bn)) {
                        $missing[] = $key;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Add these to lang/bn.json');
    }

    public function test_the_switch_changes_the_language_and_only_goes_back_on_site(): void
    {
        $this->get('/courses')->assertSee('Learn something new');

        $this->from('/courses')->get('/language/bn')->assertRedirect('/courses');
        $this->get('/courses')->assertSee('নতুন কিছু শিখুন')->assertSee('lang="bn"', false);

        $this->from('https://evil.example/')->get('/language/en')->assertRedirect(route('home'));
        $this->get('/courses')->assertSee('Learn something new');

        $this->get('/language/fr')->assertNotFound();
    }

    public function test_a_signed_in_student_keeps_their_language(): void
    {
        $student = $this->student();

        $this->actingAs($student)->get('/language/bn');
        $this->assertSame('bn', $student->fresh()->locale);

        // A new visit with no session choice still opens in Bangla.
        $this->flushSession();
        $this->actingAs($student->fresh())->get('/my-courses')->assertOk()->assertSee('আমার কোর্স');
    }

    public function test_every_student_panel_page_renders_in_bangla(): void
    {
        $student = $this->student(['locale' => 'bn']);

        foreach (['/my-courses', '/my-courses/all', '/my-payments', '/my-certificates', '/my-wishlist', '/notifications', '/my-account'] as $page) {
            $this->actingAs($student)->get($page)->assertOk()->assertSee('স্টুডেন্ট প্যানেল');
        }
    }

    public function test_notices_are_worded_in_the_readers_language(): void
    {
        Notification::fake();
        $course = Course::create(['title' => 'Laravel', 'slug' => 'laravel', 'status' => 'Published', 'published_at' => now()->subDay(), 'price' => 500]);
        $student = $this->student(['locale' => 'bn']);
        $payment = Payment::create(['user_id' => $student->id, 'course_id' => $course->id, 'amount' => 500, 'method' => 'bKash', 'transaction_id' => 'TX1', 'status' => 'Pending']);
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'super-admin')->value('id'), 'status' => 'Active']);

        Lms::verifyPayment($payment, $admin);

        Notification::assertSentTo($student, StudentNotice::class, function (StudentNotice $notice, array $channels, User $notifiable) {
            // Stored as a template with its values…
            $this->assertSame('Payment verified — :course is open', $notice->toArray($notifiable)['title']);
            $this->assertSame('Laravel', $notice->params['course']);

            // …and the email goes out in the student's language.
            app()->setLocale($notifiable->preferredLocale());
            $this->assertSame('পেমেন্ট যাচাই হয়েছে — Laravel খুলে গেছে', $notice->toMail($notifiable)->subject);

            return true;
        });
    }

    public function test_the_panel_shows_a_stored_notice_in_the_current_language(): void
    {
        $student = $this->student();
        $student->notify(new StudentNotice('You are enrolled in :course', 'The course is open in your student panel — start whenever you are ready.', null, 'success', email: false, params: ['course' => 'Laravel']));

        $this->actingAs($student)->get('/notifications')->assertSee('You are enrolled in Laravel');
        $this->actingAs($student)->get('/language/bn');
        $this->actingAs($student)->get('/notifications')->assertSee('আপনি Laravel-এ ভর্তি হয়েছেন');
    }

    public function test_footer_text_from_the_section_library_follows_the_language(): void
    {
        app()->setLocale('bn');
        $html = app(SitePartRenderer::class)->render(new SitePart([
            'kind' => 'footer',
            'html' => '<h3>{{ heading }}</h3>{{#links}}<a href="{{ url }}">{{ label }}</a>{{/links}}',
            'fields' => [
                ['key' => 'heading', 'type' => 'text'],
                ['key' => 'links', 'type' => 'group', 'fields' => [['key' => 'label', 'type' => 'text'], ['key' => 'url', 'type' => 'url']]],
            ],
            'values' => ['heading' => 'Quick links', 'links' => [['label' => 'Home', 'url' => '/']]],
        ]), [])['html'];

        $this->assertStringContainsString('দরকারি লিংক', $html);
        $this->assertStringContainsString('<a href="/">হোম</a>', $html);
    }
}
