<?php

namespace Database\Seeders;

use App\Actions\Menu\SaveMenu;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Page;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sample courses, an instructor, two students with progress, and the LMS
 * menus. Safe to run again: courses are matched by slug, accounts by email,
 * and an existing curriculum is never replaced.
 */
class LmsSeeder extends Seeder
{
    public function run(): void
    {
        $instructorRole = Role::where('slug', 'instructor')->first();
        $studentRole = Role::where('slug', Role::STUDENT)->first();

        $rafiq = User::updateOrCreate(['email' => 'rafiq@example.com'], [
            'name' => 'Rafiq Hasan', 'password' => 'password', 'role_id' => $instructorRole?->id,
            'status' => 'Active', 'email_verified_at' => now(), 'last_seen_at' => now()->subHours(2),
        ]);
        $mira = User::where('email', 'mira@example.com')->first() ?? $rafiq;

        $sadia = User::updateOrCreate(['email' => 'student@example.com'], [
            'name' => 'Sadia Akter', 'password' => 'password', 'role_id' => $studentRole?->id,
            'status' => 'Active', 'email_verified_at' => now(), 'last_seen_at' => now()->subMinutes(30),
        ]);
        $karim = User::updateOrCreate(['email' => 'karim@example.com'], [
            'name' => 'Karim Uddin', 'password' => 'password', 'role_id' => $studentRole?->id,
            'status' => 'Active', 'email_verified_at' => now(), 'last_seen_at' => now()->subDays(2),
        ]);

        $categories = [];
        foreach (['Web Development', 'Design', 'Business', 'Data Science'] as $name) {
            $categories[$name] = Category::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }

        $courses = [
            [
                'title' => 'Laravel from Scratch',
                'subtitle' => 'Build and ship a real web application with PHP and Laravel — routing, databases, authentication and deployment.',
                'category' => 'Web Development', 'instructor' => $rafiq, 'level' => 'Beginner',
                'price' => 2500, 'sale_price' => 1500, 'featured' => true,
                'outcomes' => ['Set up a Laravel project the professional way', 'Design a database with migrations and Eloquent', 'Add sign-in, roles and permissions', 'Deploy to shared hosting or a VPS'],
                'requirements' => ['Basic HTML and CSS', 'A computer that can run PHP 8.2 (XAMPP is fine)'],
                'sections' => [
                    'Getting started' => [['Welcome to the course', 5, true], ['Installing PHP, Composer and Laravel', 12, true], ['How a request becomes a page', 9, false]],
                    'Data and Eloquent' => [['Migrations and the schema', 14, false], ['Models and relationships', 18, false], ['Seeding sample data', 8, false]],
                    'Users and security' => [['Sign in and sign out', 16, false], ['Roles and permissions', 15, false]],
                    'Going live' => [['Deploying to cPanel', 11, false], ['Next steps', 4, false]],
                ],
            ],
            [
                'title' => 'Tailwind CSS in Practice',
                'subtitle' => 'Design clean, responsive interfaces fast — without writing a stylesheet from scratch.',
                'category' => 'Web Development', 'instructor' => $mira, 'level' => 'Intermediate',
                'price' => 0, 'sale_price' => null, 'featured' => false,
                'outcomes' => ['Think in utility classes', 'Build responsive layouts with grid and flex', 'Create a small design system with theme tokens'],
                'requirements' => ['Comfortable with HTML'],
                'sections' => [
                    'Foundations' => [['Why utility-first?', 6, true], ['Spacing, colour and type', 13, false]],
                    'Layouts' => [['Flexbox patterns', 15, false], ['Grid and responsive design', 17, false]],
                ],
            ],
            [
                'title' => 'UI Design Fundamentals',
                'subtitle' => 'Hierarchy, spacing, colour and type: the rules behind interfaces that feel effortless.',
                'category' => 'Design', 'instructor' => $mira, 'level' => 'Beginner',
                'price' => 1800, 'sale_price' => null, 'featured' => true,
                'outcomes' => ['Create clear visual hierarchy', 'Choose and pair typefaces', 'Build accessible colour palettes'],
                'requirements' => ['No experience needed'],
                'sections' => [
                    'Seeing like a designer' => [['What makes a design work', 8, true], ['Hierarchy and contrast', 12, false]],
                    'The toolkit' => [['Typography', 14, false], ['Colour', 13, false], ['Spacing and grids', 11, false]],
                ],
            ],
            [
                'title' => 'Freelancing for Developers',
                'subtitle' => 'Find clients, price your work and run a one-person business that lasts.',
                'category' => 'Business', 'instructor' => $rafiq, 'level' => 'All levels',
                'price' => 1200, 'sale_price' => 999, 'featured' => false,
                'outcomes' => ['Write proposals that win work', 'Price projects with confidence', 'Keep clients coming back'],
                'requirements' => ['A skill you can sell'],
                'sections' => [
                    'Getting clients' => [['Your first client', 10, true], ['Writing a proposal', 12, false]],
                    'Running the business' => [['Pricing and contracts', 14, false], ['Getting paid', 8, false]],
                ],
            ],
            [
                'title' => 'Python for Data Analysis',
                'subtitle' => 'Clean, explore and chart real data with Python, pandas and Jupyter.',
                'category' => 'Data Science', 'instructor' => $rafiq, 'level' => 'Intermediate',
                'price' => 3000, 'sale_price' => null, 'featured' => false, 'draft' => true,
                'outcomes' => ['Load and clean messy data', 'Summarise data with pandas', 'Draw clear charts'],
                'requirements' => ['Some programming experience'],
                'sections' => [
                    'Setup' => [['Installing Python and Jupyter', 10, true]],
                    'pandas' => [['DataFrames', 16, false], ['Grouping and joining', 18, false]],
                ],
            ],
        ];

        $made = [];

        foreach ($courses as $i => $c) {
            $course = Course::withTrashed()->updateOrCreate(['slug' => Str::slug($c['title'])], [
                'title' => $c['title'],
                'subtitle' => $c['subtitle'],
                'description' => '<p>'.e($c['subtitle']).'</p><p>Every lesson is short and practical, and builds on the one before. Work through them in order, mark each one done as you go, and you will finish with a certificate and a skill you can use straight away.</p><h3>Who this course is for</h3><p>Anyone who wants to learn by doing. Replace this text from <strong>LMS → Courses</strong> in the admin panel.</p>',
                'outcomes' => $c['outcomes'],
                'requirements' => $c['requirements'],
                'level' => $c['level'],
                'language' => 'English',
                'price' => $c['price'],
                'sale_price' => $c['sale_price'],
                'category_id' => $categories[$c['category']]->id,
                'user_id' => $c['instructor']->id,
                'status' => ($c['draft'] ?? false) ? 'Draft' : 'Published',
                'published_at' => ($c['draft'] ?? false) ? null : now()->subDays(30 - $i * 5),
                'is_featured' => $c['featured'],
                'deleted_at' => null,
            ]);
            $made[$c['title']] = $course;

            if ($course->sections()->exists()) {
                continue;
            }

            $s = 0;
            foreach ($c['sections'] as $sectionTitle => $lessons) {
                $section = CourseSection::create(['course_id' => $course->id, 'title' => $sectionTitle, 'position' => $s++]);

                foreach ($lessons as $l => [$title, $minutes, $preview]) {
                    Lesson::create([
                        'course_id' => $course->id,
                        'course_section_id' => $section->id,
                        'title' => $title,
                        'type' => 'article',
                        'body' => '<p>This is the lesson <strong>'.e($title).'</strong>.</p><p>Add a YouTube or Vimeo link to make it a video lesson, write the notes here, and attach a file for students to download — all from the course\'s <strong>Curriculum</strong> tab in the admin panel.</p><ul><li>Key idea one</li><li>Key idea two</li><li>A short exercise to try</li></ul>',
                        'duration_minutes' => $minutes,
                        'is_preview' => $preview,
                        'position' => $l,
                    ]);
                }
            }
        }

        // Sadia: half-way through Laravel, finished Tailwind, waiting on UI Design.
        $this->enrol($sadia, $made['Laravel from Scratch'], 'Active', 1500, 'bKash', 'BK8N7A6XK2', 5);
        $this->enrol($sadia, $made['Tailwind CSS in Practice'], 'Active', 0, 'Free', null, 99);
        $this->enrol($sadia, $made['UI Design Fundamentals'], 'Pending', 1800, 'Nagad', 'NG55T2Q9RW', 0);
        $this->enrol($karim, $made['Laravel from Scratch'], 'Active', 1500, 'Bank transfer', 'TRX-20931', 2);
        $this->enrol($karim, $made['Freelancing for Developers'], 'Pending', 999, 'bKash', 'BK1Z4C7M0P', 0);

        CourseReview::updateOrCreate(['user_id' => $sadia->id, 'course_id' => $made['Tailwind CSS in Practice']->id], [
            'rating' => 5, 'comment' => 'Short, clear lessons — I finally understand responsive grids.',
        ]);
        CourseReview::updateOrCreate(['user_id' => $sadia->id, 'course_id' => $made['Laravel from Scratch']->id], [
            'rating' => 5, 'comment' => 'Exactly the pace I needed. The deployment section alone was worth it.',
        ]);
        CourseReview::updateOrCreate(['user_id' => $karim->id, 'course_id' => $made['Laravel from Scratch']->id], [
            'rating' => 4, 'comment' => 'Very practical. Would love a section on testing.',
        ]);

        $this->menus();

        // The demo's sections-built "Home" page would hide the course
        // homepage; the LMS demo opens on the courses instead.
        if (Page::homeId() && Page::whereKey(Page::homeId())->value('slug') === '/home') {
            Page::setHome(null);
        }
    }

    private function enrol(User $student, Course $course, string $status, float $amount, string $method, ?string $txn, int $lessonsDone): void
    {
        $enrollment = Enrollment::updateOrCreate(['user_id' => $student->id, 'course_id' => $course->id], [
            'status' => $status,
            'amount' => $amount,
            'payment_method' => $method,
            'transaction_id' => $txn,
            'enrolled_at' => $status === 'Active' ? now()->subDays(rand(3, 20)) : null,
        ]);

        // The payment behind a paid place, for LMS → Payments.
        if ($amount > 0 && $txn) {
            Payment::updateOrCreate(['method' => $method, 'transaction_id' => $txn], [
                'user_id' => $student->id,
                'course_id' => $course->id,
                'enrollment_id' => $enrollment->id,
                'amount' => $amount,
                'expected_amount' => $course->currentPrice(),
                'sender_number' => '01'.random_int(300000000, 999999999),
                'status' => $status === 'Active' ? 'Verified' : 'Pending',
                'reviewed_at' => $status === 'Active' ? now()->subDays(2) : null,
            ]);
        }

        if ($status !== 'Active') {
            return;
        }

        foreach ($course->orderedLessons()->take($lessonsDone) as $lesson) {
            LessonCompletion::firstOrCreate(['user_id' => $student->id, 'lesson_id' => $lesson->id], ['course_id' => $course->id]);
        }

        $enrollment->syncCompletion();
    }

    /** Replaces the blog demo's menus (which link to draft pages) with the school's. */
    private function menus(): void
    {
        $save = app(SaveMenu::class);

        $save->handle('Header', [
            ['label' => 'Home', 'url' => '/', 'icon' => 'home', 'depth' => 0],
            ['label' => 'Courses', 'url' => '/courses', 'icon' => 'book', 'depth' => 0],
            ['label' => 'Blog', 'url' => '/blog', 'depth' => 0],
            ['label' => 'About', 'url' => '/about', 'depth' => 0],
            ['label' => 'Contact', 'url' => '/contact', 'icon' => 'mail', 'depth' => 0],
            ['label' => 'My courses', 'url' => '/my-courses', 'icon' => 'user', 'visibility' => 'Authenticated', 'depth' => 0],
            ['label' => 'Sign in', 'url' => '/login', 'icon' => 'lock', 'visibility' => 'Guests', 'depth' => 0],
        ]);

        $save->handle('Footer', [
            ['label' => 'Courses', 'url' => '/courses', 'depth' => 0],
            ['label' => 'Blog', 'url' => '/blog', 'depth' => 0],
            ['label' => 'Contact', 'url' => '/contact', 'depth' => 0],
            ['label' => 'Create an account', 'url' => '/register', 'visibility' => 'Guests', 'depth' => 0],
            ['label' => 'My courses', 'url' => '/my-courses', 'visibility' => 'Authenticated', 'depth' => 0],
        ]);
    }
}
